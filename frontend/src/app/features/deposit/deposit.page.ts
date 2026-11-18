import { ChangeDetectionStrategy, Component, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable, catchError, of, switchMap } from 'rxjs';
import { CategoryDetail } from '../../catalog/category.models';
import { CategoryService, pathTo } from '../../catalog/category.service';
import { ApiError, ValidationErrors, fieldError, toApiError } from '../../core/api/api-errors';
import { LISTING_TYPES, Listing, ListingDraftPayload, ListingType } from '../../listings/listing.models';
import { ListingService } from '../../listings/listing.service';
import { AttributeFields } from './attribute-fields';
import { AttributeGroup, attributeValues, buildAttributeGroup } from './attribute-form';
import { CategoryPicker } from './category-picker';
import { Icon } from '../../ui/icon';
import { PhotoManager, PhotoSummary } from './photos/photo-manager';

const TIPS: Record<DepositStep, string> = {
  1: 'Un titre précis (marque, modèle, taille) est la première chose que lisent les acheteurs.',
  2: 'Prenez vos photos à la lumière du jour, sous plusieurs angles. La première sera la vignette de votre annonce.',
  3: 'Plus vous renseignez de caractéristiques, plus votre annonce ressort dans les filtres de recherche.',
  4: 'Regardez le prix d’articles similaires : une annonce au juste prix part en moyenne bien plus vite.',
  5: 'Votre position sert à vous proposer aux acheteurs proches. Elle n’est jamais affichée précisément.',
};

export type DepositStep = 1 | 2 | 3 | 4 | 5;
export const LAST_STEP: DepositStep = 5;

/** Les 5 étapes du tunnel (cahier des charges §3.B). */
export const DEPOSIT_STEPS: { step: DepositStep; label: string }[] = [
  { step: 1, label: 'Annonce' },
  { step: 2, label: 'Photos' },
  { step: 3, label: 'Caractéristiques' },
  { step: 4, label: 'Prix' },
  { step: 5, label: 'Localisation' },
];

/** Étape à laquelle renvoyer l'utilisateur pour une erreur serveur donnée. */
export function stepForField(field: string): DepositStep {
  if (field.startsWith('photo')) return 2;
  if (field.startsWith('attribute_values') || field === 'description') return 3;
  if (field === 'price' || field === 'hide_phone') return 4;
  if (field.startsWith('location')) return 5;
  return 1;
}

/**
 * Tunnel de dépôt (DEP-01, DEP-04) : sauvegarde du brouillon à chaque étape, photos envoyées directement
 * au stockage, formulaire de caractéristiques généré depuis le schéma de la catégorie, puis soumission.
 */
@Component({
  selector: 'app-deposit-page',
  imports: [ReactiveFormsModule, RouterLink, CategoryPicker, AttributeFields, Icon, PhotoManager],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './deposit.page.html',
  styleUrl: './deposit.page.scss',
})
export class DepositPage {
  private readonly categories = inject(CategoryService);
  private readonly listings = inject(ListingService);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  protected readonly steps = DEPOSIT_STEPS;
  protected readonly lastStep = LAST_STEP;
  protected readonly types = LISTING_TYPES;

  protected readonly treeError = signal(false);
  protected readonly tree = toSignal(
    this.categories.tree().pipe(
      catchError(() => {
        this.treeError.set(true);
        return of([]);
      }),
    ),
    { initialValue: [] },
  );

  readonly step = signal<DepositStep>(1);
  readonly draft = signal<Listing | null>(null);
  readonly submitted = signal<Listing | null>(null);
  readonly saving = signal(false);
  readonly error = signal<ApiError | null>(null);
  protected readonly locating = signal(false);
  protected readonly locationMessage = signal<string | null>(null);

  readonly categoryId = signal<string | null>(null);
  readonly category = signal<CategoryDetail | null>(null);
  readonly attributes = signal<AttributeGroup>(buildAttributeGroup([]));

  readonly basics = new FormGroup({
    type: new FormControl<ListingType>('sale', { nonNullable: true }),
    title: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(3), Validators.maxLength(100)],
    }),
  });

  readonly details = new FormGroup({
    description: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(10), Validators.maxLength(4000)],
    }),
  });

  readonly pricing = new FormGroup({
    price: new FormControl<number | null>(null, { validators: [Validators.min(0), Validators.max(9_999_999_999)] }),
    hide_phone: new FormControl(true, { nonNullable: true }),
  });

  readonly location = new FormGroup({
    lat: new FormControl<number | null>(null, { validators: [Validators.required, Validators.min(-90), Validators.max(90)] }),
    lng: new FormControl<number | null>(null, {
      validators: [Validators.required, Validators.min(-180), Validators.max(180)],
    }),
  });

  protected readonly serverErrors = computed<ValidationErrors>(() => this.error()?.errors ?? {});
  protected readonly tip = computed(() => TIPS[this.step()]);
  protected readonly categoryTouched = signal(false);
  protected readonly categoryPath = computed(() => {
    const id = this.categoryId();
    return id ? pathTo(this.tree(), id).map((n) => n.name).join(' › ') : '';
  });
  private readonly typeValue = toSignal(this.basics.controls.type.valueChanges, {
    initialValue: this.basics.controls.type.value,
  });
  /** Prix obligatoire pour une vente ou une location uniquement (même règle que l'API). */
  protected readonly priceRequired = computed(
    () => LISTING_TYPES.find((t) => t.value === this.typeValue())?.priced ?? true,
  );

  /** État des photos remonté par le gestionnaire (nombre prêtes, envois en cours). */
  readonly photoSummary = signal<PhotoSummary>({ ready: 0, busy: false });
  protected readonly photoError = signal<string | null>(null);

  protected fieldError(field: string): string | null {
    return fieldError(this.serverErrors(), field);
  }

  protected showError(control: FormControl<unknown>): boolean {
    return control.invalid && (control.touched || control.dirty);
  }

  goTo(step: DepositStep): void {
    if (step < this.step()) {
      this.step.set(step);
    }
  }

  /** Valide l'étape courante, sauvegarde le brouillon, puis passe à la suivante (ou publie à la dernière). */
  next(): void {
    if (this.saving()) return;

    switch (this.step()) {
      case 1:
        return this.saveBasics();
      case 2:
        return this.checkPhotos();
      case 3:
        return this.saveDetails();
      case 4:
        return this.savePricing();
      case 5:
        return this.publish();
    }
  }

  /** Les photos sont enregistrées au fil de l'eau : on vérifie seulement qu'on peut poursuivre. */
  private checkPhotos(): void {
    const { ready, busy } = this.photoSummary();
    if (busy) {
      this.photoError.set('Patientez : des photos sont encore en cours d’envoi.');
      return;
    }
    if (ready === 0 && this.priceRequired()) {
      this.photoError.set('Ajoutez au moins une photo.');
      return;
    }
    this.photoError.set(null);
    this.error.set(null);
    this.step.set(3);
  }

  private saveBasics(): void {
    this.categoryTouched.set(true);
    this.basics.markAllAsTouched();
    const categoryId = this.categoryId();
    if (this.basics.invalid || !categoryId) return;

    const slug = pathTo(this.tree(), categoryId).at(-1)?.slug;
    if (!slug) return;

    const payload: ListingDraftPayload = { ...this.basics.getRawValue(), category_id: categoryId };
    const categoryChanged = this.category()?.id !== categoryId;

    this.run(
      this.save(payload).pipe(
        switchMap((listing) => (categoryChanged ? this.loadCategory(slug, listing) : of(listing))),
      ),
      2,
    );
  }

  private saveDetails(): void {
    const schema = this.category()?.attributes ?? [];
    this.details.markAllAsTouched();
    this.attributes().markAllAsTouched();
    if (this.details.invalid || this.attributes().invalid) return;

    this.run(
      this.save({
        description: this.details.controls.description.value,
        attribute_values: attributeValues(this.attributes(), schema),
      }),
      4,
    );
  }

  private savePricing(): void {
    this.pricing.markAllAsTouched();
    // Efface une erreur « obligatoire » posée précédemment (le type d'annonce a pu changer depuis).
    this.pricing.controls.price.updateValueAndValidity();
    const price = this.pricing.controls.price.value;
    if (this.pricing.invalid) return;
    if (this.priceRequired() && (price === null || (price as unknown) === '')) {
      this.pricing.controls.price.setErrors({ required: true });
      return;
    }

    this.run(this.save({ price: price === null ? null : Number(price), hide_phone: this.pricing.controls.hide_phone.value }), 5);
  }

  private publish(): void {
    this.location.markAllAsTouched();
    const { lat, lng } = this.location.getRawValue();
    if (this.location.invalid || lat === null || lng === null) return;

    const id = this.draft()?.id;
    if (!id) return;

    this.saving.set(true);
    this.error.set(null);
    this.save({ location: { lat: Number(lat), lng: Number(lng) } })
      .pipe(switchMap(() => this.listings.submit(id)))
      .subscribe({
        next: (listing) => {
          this.saving.set(false);
          this.draft.set(listing);
          this.submitted.set(listing);
        },
        error: (error: unknown) => this.fail(error),
      });
  }

  /** « Autour de moi » : position du navigateur (le géocodage ville / code postal arrivera avec RECH-04). */
  useMyPosition(): void {
    if (!this.isBrowser || !('geolocation' in navigator)) {
      this.locationMessage.set("La géolocalisation n'est pas disponible sur cet appareil.");
      return;
    }

    this.locating.set(true);
    this.locationMessage.set(null);
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        this.location.setValue({
          lat: Math.round(coords.latitude * 1e6) / 1e6,
          lng: Math.round(coords.longitude * 1e6) / 1e6,
        });
        this.locating.set(false);
        this.locationMessage.set('Position récupérée.');
      },
      () => {
        this.locating.set(false);
        this.locationMessage.set('Position refusée ou indisponible : saisissez les coordonnées.');
      },
      { enableHighAccuracy: false, timeout: 10_000 },
    );
  }

  private save(payload: ListingDraftPayload): Observable<Listing> {
    const draft = this.draft();
    return draft ? this.listings.updateDraft(draft.id, payload) : this.listings.createDraft(payload);
  }

  private loadCategory(slug: string, listing: Listing): Observable<Listing> {
    return this.categories.detail(slug).pipe(
      switchMap((category) => {
        this.category.set(category);
        this.attributes.set(buildAttributeGroup(category.attributes, listing.attribute_values));
        return of(listing);
      }),
    );
  }

  private run(request: Observable<Listing>, nextStep: DepositStep): void {
    this.saving.set(true);
    this.error.set(null);
    request.subscribe({
      next: (listing) => {
        this.draft.set(listing);
        this.saving.set(false);
        this.step.set(nextStep);
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  private fail(error: unknown): void {
    const apiError = toApiError(error);
    this.saving.set(false);
    this.error.set(apiError);

    const fields = Object.keys(apiError.errors);
    if (fields.length > 0) {
      this.step.set(Math.min(...fields.map(stepForField)) as DepositStep);
    }
  }
}
