import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  OnInit,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { Subscription } from 'rxjs';
import { toApiError } from '../../../core/api/api-errors';
import { Photo } from '../../../listings/listing.models';
import { PHOTO_LIMITS, PhotoService } from '../../../listings/photo.service';
import { Icon } from '../../../ui/icon';
import { CropEditor } from './crop-editor';
import { isFullImage } from './crop-geometry';
import { CropRect, ImagePreparer } from './image-preparer';

export type PhotoItemState = 'preparing' | 'uploading' | 'ready' | 'failed';

export interface PhotoItem {
  key: string;
  state: PhotoItemState;
  photo: Photo | null;
  /** Fichier d'origine conservé en mémoire pour un recadrage ultérieur. */
  source: Blob | null;
  preview: string | null;
  error: string | null;
}

export interface PhotoSummary {
  ready: number;
  busy: boolean;
}

let nextKey = 0;

/** DEP-04 : ajout, envoi direct, réordonnancement, photo principale, recadrage et suppression. */
@Component({
  selector: 'app-photo-manager',
  imports: [Icon, CropEditor],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styleUrl: './photo-manager.scss',
  templateUrl: './photo-manager.html',
})
export class PhotoManager implements OnInit {
  private readonly photos = inject(PhotoService);
  private readonly preparer = inject(ImagePreparer);
  private readonly subscriptions = new Map<string, Subscription>();

  readonly listingId = input.required<string>();
  readonly initialPhotos = input<Photo[]>([]);
  readonly max = input<number>(PHOTO_LIMITS.maxPerListing);
  readonly summaryChange = output<PhotoSummary>();

  readonly items = signal<PhotoItem[]>([]);
  readonly notice = signal<string | null>(null);
  readonly cropping = signal<{ key: string; src: string; width: number; height: number } | null>(null);
  protected dragKey: string | null = null;

  /** Les photos en échec ne comptent pas dans le quota (même règle que l'API). */
  readonly used = computed(() => this.items().filter((i) => i.state !== 'failed').length);
  readonly remaining = computed(() => Math.max(0, this.max() - this.used()));
  readonly summary = computed<PhotoSummary>(() => ({
    ready: this.items().filter((i) => i.state === 'ready').length,
    busy: this.items().some((i) => i.state === 'preparing' || i.state === 'uploading'),
  }));
  protected readonly accept = [...PHOTO_LIMITS.acceptedTypes, 'image/heic', 'image/heif'].join(',');

  constructor() {
    effect(() => this.summaryChange.emit(this.summary()));
    inject(DestroyRef).onDestroy(() => {
      this.subscriptions.forEach((s) => s.unsubscribe());
      this.items().forEach((i) => this.revoke(i));
    });
  }

  ngOnInit(): void {
    this.items.set(
      [...this.initialPhotos()]
        .sort((a, b) => a.position - b.position)
        .map((photo) => ({
          key: `p${nextKey++}`,
          state: photo.status === 'ready' ? 'ready' : photo.status === 'failed' ? 'failed' : 'uploading',
          photo,
          source: null,
          preview: null,
          error: photo.failure_reason,
        })),
    );

    // Reprise d'un brouillon : suivre les photos encore en traitement.
    for (const item of this.items()) {
      if (item.state === 'uploading' && item.photo?.status === 'processing') {
        this.track(item.key, this.photos.waitUntilProcessed(this.listingId(), item.photo));
      }
    }
  }

  /** URL d'affichage : déclinaison serveur si prête, sinon aperçu local. */
  imageFor(item: PhotoItem): string | null {
    return item.photo?.variants.card?.webp ?? item.preview;
  }

  onFiles(input: HTMLInputElement): void {
    const files = Array.from(input.files ?? []);
    input.value = '';
    this.addFiles(files);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    if (this.dragKey) return; // réordonnancement interne, pas un ajout
    this.addFiles(Array.from(event.dataTransfer?.files ?? []));
  }

  addFiles(files: File[]): void {
    this.notice.set(null);
    const images = files.filter((f) => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));

    if (images.length < files.length) {
      this.notice.set('Seules les images sont acceptées (JPEG, PNG, WebP ou HEIC).');
    }
    if (images.length > this.remaining()) {
      this.notice.set(`Maximum ${this.max()} photos : ${images.length - this.remaining()} photo(s) ignorée(s).`);
    }

    for (const file of images.slice(0, this.remaining())) {
      const item: PhotoItem = {
        key: `p${nextKey++}`,
        state: 'preparing',
        photo: null,
        source: file,
        preview: URL.createObjectURL(file),
        error: null,
      };
      this.items.update((items) => [...items, item]);
      void this.send(item.key, file);
    }
  }

  /** Prépare (orientation, recadrage, réduction, sans EXIF) puis envoie. */
  private async send(key: string, source: Blob, crop?: CropRect, replaces?: Photo): Promise<void> {
    let prepared: Blob;
    try {
      prepared = (await this.preparer.prepare(source, crop)).blob;
    } catch (error) {
      this.patch(key, { state: 'failed', error: error instanceof Error ? error.message : 'Image illisible.' });
      return;
    }

    if (prepared.size > PHOTO_LIMITS.maxBytes) {
      this.patch(key, { state: 'failed', error: 'La photo dépasse 15 Mo.' });
      return;
    }

    if (!this.items().some((i) => i.key === key)) return; // retirée entre-temps
    this.patch(key, { state: 'uploading' });
    this.track(
      key,
      this.photos.upload(this.listingId(), prepared, (photo) => this.patch(key, { photo })),
      replaces,
    );
  }

  private track(key: string, request: ReturnType<PhotoService['upload']>, replaces?: Photo): void {
    this.subscriptions.get(key)?.unsubscribe();
    this.subscriptions.set(
      key,
      request.subscribe({
        next: (photo) => {
          this.patch(key, {
            photo,
            state: photo.status === 'ready' ? 'ready' : 'failed',
            error: photo.status === 'ready' ? null : (photo.failure_reason ?? 'Le traitement a échoué.'),
          });
          if (photo.status === 'ready' && replaces) {
            this.photos.remove(this.listingId(), replaces.id).subscribe({ complete: () => this.syncOrder() });
          }
        },
        error: (error: unknown) => this.patch(key, { state: 'failed', error: toApiError(error).message }),
        complete: () => this.subscriptions.delete(key),
      }),
    );
  }

  move(key: string, offset: number): void {
    const items = [...this.items()];
    const from = items.findIndex((i) => i.key === key);
    const to = from + offset;
    if (from < 0 || to < 0 || to >= items.length) return;
    const [item] = items.splice(from, 1);
    items.splice(to, 0, item);
    this.items.set(items);
    this.syncOrder();
  }

  makeMain(key: string): void {
    const index = this.items().findIndex((i) => i.key === key);
    this.move(key, -index);
  }

  remove(item: PhotoItem): void {
    this.subscriptions.get(item.key)?.unsubscribe();
    this.subscriptions.delete(item.key);
    this.items.update((items) => items.filter((i) => i.key !== item.key));
    this.revoke(item);
    if (item.photo) {
      this.photos.remove(this.listingId(), item.photo.id).subscribe({
        error: (error: unknown) => this.notice.set(toApiError(error).message),
      });
    }
  }

  async openCrop(item: PhotoItem): Promise<void> {
    if (!item.source || !item.preview) return;
    try {
      const { width, height } = await this.preparer.dimensions(item.source);
      this.cropping.set({ key: item.key, src: item.preview, width, height });
    } catch {
      this.notice.set('Impossible de recadrer cette photo.');
    }
  }

  /** Recadrage : la nouvelle version remplace l'ancienne, à la même place. */
  applyCrop(rect: CropRect): void {
    const crop = this.cropping();
    this.cropping.set(null);
    const item = this.items().find((i) => i.key === crop?.key);
    if (!crop || !item?.source || isFullImage(rect, crop.width, crop.height)) return;

    const replaces = item.photo ?? undefined;
    this.patch(item.key, { state: 'preparing', photo: null, error: null });
    void this.send(item.key, item.source, rect, replaces);
  }

  protected onDragStart(event: DragEvent, key: string): void {
    this.dragKey = key;
    event.dataTransfer?.setData('text/plain', key);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
  }

  protected onDropOn(event: DragEvent, targetKey: string): void {
    event.preventDefault();
    event.stopPropagation();
    const key = this.dragKey;
    this.dragKey = null;
    if (!key || key === targetKey) return;
    const items = this.items();
    this.move(key, items.findIndex((i) => i.key === targetKey) - items.findIndex((i) => i.key === key));
  }

  protected onDragEnd(): void {
    this.dragKey = null;
  }

  /** Envoie le nouvel ordre (la première photo est la photo principale). */
  private syncOrder(): void {
    const ids = this.items()
      .map((i) => i.photo?.id)
      .filter((id): id is string => !!id);
    if (ids.length < 2) return;
    this.photos.reorder(this.listingId(), ids).subscribe({
      error: () => this.notice.set("L'ordre des photos n'a pas pu être enregistré. Réessayez."),
    });
  }

  private patch(key: string, changes: Partial<PhotoItem>): void {
    this.items.update((items) => items.map((i) => (i.key === key ? { ...i, ...changes } : i)));
  }

  private revoke(item: PhotoItem): void {
    if (item.preview) URL.revokeObjectURL(item.preview);
  }
}
