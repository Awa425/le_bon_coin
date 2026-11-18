import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { CategoryDetail, CategoryNode } from '../../catalog/category.models';
import { API_BASE_URL } from '../../core/api/api-config';
import { Listing } from '../../listings/listing.models';
import { query, text } from '../../../testing/dom';
import { DepositPage, stepForField } from './deposit.page';

const API = 'http://api.test/v1';

const tree: CategoryNode[] = [
  {
    id: 'vehicules',
    name: 'Véhicules',
    slug: 'vehicules',
    description: null,
    children: [{ id: 'voitures', name: 'Voitures', slug: 'voitures', description: null, children: [] }],
  },
];

const voitures: CategoryDetail = {
  id: 'voitures',
  name: 'Voitures',
  slug: 'voitures',
  description: null,
  is_leaf: true,
  breadcrumb: [],
  attributes: [
    { key: 'kilometrage', label: 'Kilométrage', type: 'number', unit: 'km', options: [], min: 0, max: 2000000, required: true, filterable: true },
    { key: 'carburant', label: 'Carburant', type: 'select', unit: null, options: ['essence', 'diesel'], min: null, max: null, required: true, filterable: true },
  ],
};

const listing = (overrides: Partial<Listing> = {}): Listing => ({
  id: 'l1',
  slug: 'peugeot-208-abc',
  type: 'sale',
  status: 'draft',
  category_id: 'voitures',
  title: 'Peugeot 208',
  description: null,
  price: null,
  currency: 'EUR',
  attribute_values: {},
  hide_phone: true,
  published_at: null,
  expires_at: null,
  ...overrides,
});

describe('DepositPage (DEP-01)', () => {
  let fixture: ComponentFixture<DepositPage>;
  let page: DepositPage;
  let http: HttpTestingController;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: API_BASE_URL, useValue: API },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(DepositPage);
    page = fixture.componentInstance;
    await fixture.whenStable();
    http.expectOne(`${API}/categories`).flush({ data: tree });
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  /** Étape 1 complète : brouillon créé puis schéma de la catégorie chargé. */
  async function completeStepOne(): Promise<void> {
    page.categoryId.set('voitures');
    page.basics.setValue({ type: 'sale', title: 'Peugeot 208' });
    page.next();

    const create = http.expectOne({ method: 'POST', url: `${API}/listings` });
    expect(create.request.body).toEqual({ type: 'sale', title: 'Peugeot 208', category_id: 'voitures' });
    create.flush({ data: listing() });
    http.expectOne(`${API}/categories/voitures`).flush({ data: voitures });
    await fixture.whenStable();
  }

  it('bloque l’étape 1 tant qu’aucune catégorie finale n’est choisie', async () => {
    page.basics.setValue({ type: 'sale', title: 'Peugeot 208' });
    page.next();
    await fixture.whenStable();

    http.expectNone(`${API}/listings`);
    expect(page.step()).toBe(1);
    expect(text(fixture)).toContain("Choisissez une catégorie jusqu'au dernier niveau.");
  });

  it('déroule le tunnel complet jusqu’à la soumission en modération', async () => {
    await completeStepOne();
    expect(page.step()).toBe(2);
    expect(query(fixture, 'app-photo-manager')).toBeTruthy();

    page.photoSummary.set({ ready: 1, busy: false });
    page.next();
    await fixture.whenStable();
    expect(page.step()).toBe(3);
    expect(query(fixture, '#attr-kilometrage')).toBeTruthy();
    expect(text(fixture)).toContain('Véhicules › Voitures');

    page.attributes().patchValue({ kilometrage: '84000', carburant: 'essence' });
    page.details.setValue({ description: 'Entretien à jour, aucun frais à prévoir.' });
    page.next();
    const details = http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` });
    expect(details.request.body).toEqual({
      description: 'Entretien à jour, aucun frais à prévoir.',
      attribute_values: { kilometrage: 84000, carburant: 'essence' },
    });
    details.flush({ data: listing() });
    await fixture.whenStable();
    expect(page.step()).toBe(4);

    page.pricing.setValue({ price: 8990, hide_phone: true });
    page.next();
    const pricing = http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` });
    expect(pricing.request.body).toEqual({ price: 8990, hide_phone: true });
    pricing.flush({ data: listing() });
    await fixture.whenStable();
    expect(page.step()).toBe(5);

    page.location.setValue({ lat: 45.764, lng: 4.8357 });
    page.next();
    const location = http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` });
    expect(location.request.body).toEqual({ location: { lat: 45.764, lng: 4.8357 } });
    location.flush({ data: listing() });
    http.expectOne({ method: 'POST', url: `${API}/listings/l1/submit` }).flush({ data: listing({ status: 'pending_moderation' }) });
    await fixture.whenStable();

    expect(page.submitted()?.status).toBe('pending_moderation');
    expect(text(fixture)).toContain('Annonce envoyée en modération');
  });

  it('exige une photo prête pour une vente, et attend la fin des envois', async () => {
    await completeStepOne();

    page.next();
    await fixture.whenStable();
    expect(page.step()).toBe(2);
    expect(text(fixture)).toContain('Ajoutez au moins une photo.');

    page.photoSummary.set({ ready: 1, busy: true });
    page.next();
    await fixture.whenStable();
    expect(page.step()).toBe(2);
    expect(text(fixture)).toContain('Patientez : des photos sont encore en cours d’envoi.');

    page.photoSummary.set({ ready: 1, busy: false });
    page.next();
    expect(page.step()).toBe(3);
  });

  it('n’exige pas de photo pour une annonce « Je recherche »', async () => {
    await completeStepOne();
    page.basics.controls.type.setValue('wanted');

    page.next();

    expect(page.step()).toBe(3);
  });

  it('conserve le gestionnaire de photos lors des allers-retours entre étapes', async () => {
    await completeStepOne();
    const manager = query(fixture, 'app-photo-manager');
    page.photoSummary.set({ ready: 1, busy: false });
    page.next();
    await fixture.whenStable();

    expect(query(fixture, 'app-photo-manager')).toBe(manager);
    expect(query(fixture, '.photos-step').hidden).toBe(true);

    page.goTo(2);
    await fixture.whenStable();
    expect(query(fixture, 'app-photo-manager')).toBe(manager);
    expect(query(fixture, '.photos-step').hidden).toBe(false);
  });

  it('empêche de continuer si un attribut obligatoire manque', async () => {
    await completeStepOne();
    page.step.set(3);
    await fixture.whenStable();

    page.details.setValue({ description: 'Une description suffisante.' });
    page.next();
    await fixture.whenStable();

    http.expectNone({ method: 'PATCH', url: `${API}/listings/l1` });
    expect(text(fixture)).toContain('Kilométrage est obligatoire.');
  });

  it('exige un prix pour une vente mais pas pour une recherche', async () => {
    await completeStepOne();
    page.step.set(4);
    page.next();
    http.expectNone({ method: 'PATCH', url: `${API}/listings/l1` });
    expect(page.pricing.controls.price.hasError('required')).toBe(true);

    page.basics.controls.type.setValue('wanted');
    page.next();
    http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` }).flush({ data: listing({ type: 'wanted' }) });
    await fixture.whenStable();
    expect(page.step()).toBe(5);
  });

  it('renvoie à l’étape photos si l’API signale qu’il en manque', async () => {
    await completeStepOne();
    page.step.set(5);
    page.location.setValue({ lat: 48.85, lng: 2.35 });
    page.next();

    http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` }).flush({ data: listing() });
    http
      .expectOne(`${API}/listings/l1/submit`)
      .flush({ message: 'Ajoutez au moins une photo.', errors: { photos: ['Ajoutez au moins une photo.'] } }, { status: 422, statusText: 'Unprocessable Content' });
    await fixture.whenStable();

    expect(page.step()).toBe(2);
    expect(text(fixture)).toContain('Ajoutez au moins une photo.');
  });

  it('renvoie à l’étape concernée et affiche l’erreur serveur à la soumission', async () => {
    await completeStepOne();
    page.step.set(5);
    page.location.setValue({ lat: 48.85, lng: 2.35 });
    page.next();

    http.expectOne({ method: 'PATCH', url: `${API}/listings/l1` }).flush({ data: listing() });
    http.expectOne(`${API}/listings/l1/submit`).flush(
      {
        message: 'Le champ Kilométrage est obligatoire.',
        errors: { 'attribute_values.kilometrage': ['Le champ Kilométrage est obligatoire.'] },
      },
      { status: 422, statusText: 'Unprocessable Content' },
    );
    await fixture.whenStable();

    expect(page.step()).toBe(3);
    expect(page.submitted()).toBeNull();
    expect(text(fixture)).toContain('Le champ Kilométrage est obligatoire.');
  });
});

describe('stepForField', () => {
  it('associe chaque champ serveur à son étape du tunnel', () => {
    expect(stepForField('title')).toBe(1);
    expect(stepForField('category_id')).toBe(1);
    expect(stepForField('photos')).toBe(2);
    expect(stepForField('description')).toBe(3);
    expect(stepForField('attribute_values.kilometrage')).toBe(3);
    expect(stepForField('price')).toBe(4);
    expect(stepForField('location.lat')).toBe(5);
  });
});
