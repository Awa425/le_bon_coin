import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  afterNextRender,
  computed,
  input,
  output,
  signal,
  viewChild,
} from '@angular/core';
import { Icon } from '../../../ui/icon';
import { ASPECTS, CropState, MAX_ZOOM, MIN_ZOOM, cropRect, initialCrop, normalize } from './crop-geometry';
import { CropRect } from './image-preparer';

/** Fenêtre de recadrage (DEP-04) : format, zoom, déplacement à la souris / au doigt / au clavier. */
@Component({
  selector: 'app-crop-editor',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '(document:keydown.escape)': 'cancelled.emit()' },
  styleUrl: './crop-editor.scss',
  template: `
    <div class="backdrop" (click)="cancelled.emit()"></div>
    <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="crop-title">
      <header class="dialog__head">
        <h2 id="crop-title">Recadrer la photo</h2>
        <button type="button" class="dialog__close" (click)="cancelled.emit()" aria-label="Fermer">✕</button>
      </header>

      <div
        #frame
        class="frame"
        [style.aspect-ratio]="frameAspect()"
        tabindex="0"
        role="application"
        aria-label="Zone de recadrage : utilisez les flèches pour déplacer l'image"
        aria-describedby="crop-help"
        (pointerdown)="startDrag($event)"
        (pointermove)="drag($event)"
        (pointerup)="endDrag()"
        (pointercancel)="endDrag()"
        (keydown)="nudge($event)"
      >
        <img
          [src]="src()"
          alt=""
          draggable="false"
          [style.width.%]="imageBox().width"
          [style.height.%]="imageBox().height"
          [style.left.%]="imageBox().left"
          [style.top.%]="imageBox().top"
        />
        <span class="frame__grid" aria-hidden="true"></span>
      </div>
      <p id="crop-help" class="muted small help">Glissez l'image pour la positionner. Flèches du clavier : déplacement fin.</p>

      <div class="controls">
        <div class="choices" role="radiogroup" aria-label="Format">
          @for (option of aspects; track option.label) {
            <label class="choice">
              <input type="radio" name="crop-aspect" [checked]="state().aspect === option.value" (change)="setAspect(option.value)" />
              <span>{{ option.label }}</span>
            </label>
          }
        </div>
        <label class="zoom">
          <span>Zoom</span>
          <input
            type="range"
            [min]="minZoom"
            [max]="maxZoom"
            step="0.05"
            [value]="state().zoom"
            (input)="setZoom($any($event.target).valueAsNumber)"
          />
        </label>
      </div>

      <footer class="dialog__actions">
        <button type="button" class="button button--secondary" (click)="cancelled.emit()">Annuler</button>
        <button #apply type="button" class="button button--primary" (click)="applied.emit(rect())">
          <app-icon name="check" [size]="18" [stroke]="2.5" /> Appliquer
        </button>
      </footer>
    </div>
  `,
})
export class CropEditor {
  /** URL locale de l'image (object URL). */
  readonly src = input.required<string>();
  /** Dimensions de l'image redressée. */
  readonly naturalWidth = input.required<number>();
  readonly naturalHeight = input.required<number>();

  readonly applied = output<CropRect>();
  readonly cancelled = output<void>();

  protected readonly aspects = ASPECTS;
  protected readonly minZoom = MIN_ZOOM;
  protected readonly maxZoom = MAX_ZOOM;

  private readonly frame = viewChild.required<ElementRef<HTMLElement>>('frame');
  private readonly applyButton = viewChild.required<ElementRef<HTMLButtonElement>>('apply');
  private readonly override = signal<CropState | null>(null);
  private dragFrom: { x: number; y: number; state: CropState } | null = null;

  readonly state = computed<CropState>(
    () => this.override() ?? initialCrop(this.naturalWidth(), this.naturalHeight()),
  );
  readonly rect = computed(() => cropRect(this.state(), this.naturalWidth(), this.naturalHeight()));
  protected readonly frameAspect = computed(() => `${this.rect().width} / ${this.rect().height}`);

  /** Position de l'image dans le cadre, en % du cadre (aucune mesure du DOM nécessaire). */
  protected readonly imageBox = computed(() => {
    const rect = this.rect();
    return {
      width: (this.naturalWidth() / rect.width) * 100,
      height: (this.naturalHeight() / rect.height) * 100,
      left: (-rect.x / rect.width) * 100,
      top: (-rect.y / rect.height) * 100,
    };
  });

  constructor() {
    afterNextRender(() => this.applyButton().nativeElement.focus());
  }

  setAspect(aspect: number | null): void {
    this.update({ ...this.state(), aspect, zoom: 1 });
  }

  setZoom(zoom: number): void {
    if (Number.isFinite(zoom)) this.update({ ...this.state(), zoom });
  }

  protected startDrag(event: PointerEvent): void {
    this.dragFrom = { x: event.clientX, y: event.clientY, state: this.state() };
    (event.target as HTMLElement).setPointerCapture?.(event.pointerId);
  }

  protected drag(event: PointerEvent): void {
    if (!this.dragFrom) return;
    const box = this.frame().nativeElement.getBoundingClientRect();
    const pixelsPerCss = box.width > 0 ? this.rect().width / box.width : 1;
    this.update({
      ...this.dragFrom.state,
      centerX: this.dragFrom.state.centerX - (event.clientX - this.dragFrom.x) * pixelsPerCss,
      centerY: this.dragFrom.state.centerY - (event.clientY - this.dragFrom.y) * pixelsPerCss,
    });
  }

  protected endDrag(): void {
    this.dragFrom = null;
  }

  nudge(event: KeyboardEvent): void {
    const step = this.rect().width * 0.05;
    const moves: Record<string, [number, number]> = {
      ArrowLeft: [-step, 0],
      ArrowRight: [step, 0],
      ArrowUp: [0, -step],
      ArrowDown: [0, step],
    };
    const move = moves[event.key];
    if (!move) return;
    event.preventDefault();
    const state = this.state();
    this.update({ ...state, centerX: state.centerX + move[0], centerY: state.centerY + move[1] });
  }

  private update(state: CropState): void {
    this.override.set(normalize(state, this.naturalWidth(), this.naturalHeight()));
  }
}
