import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { CategoryNode } from '../../catalog/category.models';
import { API_BASE_URL } from '../../core/api/api-config';
import { query, queryAll, text } from '../../../testing/dom';
import { HomePage, RADIUS_OPTIONS } from './home.page';

const API = 'http://api.test/v1';

const tree: CategoryNode[] = [
  {
    id: '1',
    name: 'Véhicules',
    slug: 'vehicules',
    description: null,
    children: [
      { id: '1a', name: 'Voitures', slug: 'voitures', description: null },
      { id: '1b', name: 'Motos', slug: 'motos', description: null },
    ],
  },
  { id: '2', name: 'Immobilier', slug: 'immobilier', description: null, children: [] },
];

describe('HomePage', () => {
  let fixture: ComponentFixture<HomePage>;
  let http: HttpTestingController;
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([]), { provide: API_BASE_URL, useValue: API }],
    });
    http = TestBed.inject(HttpTestingController);
    navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    fixture = TestBed.createComponent(HomePage);
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  it('affiche des emplacements de chargement puis les catégories avec icône et sous-catégories', async () => {
    expect(queryAll(fixture, '.category--skeleton').length).toBeGreaterThan(0);

    http.expectOne(`${API}/categories`).flush({ data: tree });
    await fixture.whenStable();

    const cards = queryAll(fixture, 'a.category');
    expect(cards).toHaveLength(2);
    expect(cards[0].textContent).toContain('Véhicules');
    expect(cards[0].getAttribute('title')).toBe('Voitures, Motos');
    expect(cards[1].hasAttribute('title')).toBe(false);
    expect(cards[0].querySelector('svg')).toBeTruthy();
    expect(cards[0].getAttribute('href')).toBe('/recherche?categorie=vehicules');
  });

  it('affiche un message si l’API des catégories est indisponible', async () => {
    http.expectOne(`${API}/categories`).flush(null, { status: 500, statusText: 'Erreur' });
    await fixture.whenStable();

    expect(text(fixture)).toContain('Les catégories sont momentanément indisponibles.');
  });

  it('propose les rayons du cahier des charges', () => {
    http.expectOne(`${API}/categories`).flush({ data: [] });
    const options = queryAll<HTMLOptionElement>(fixture, '#hero-radius option').map((o) => o.textContent?.trim());

    expect(options).toEqual(RADIUS_OPTIONS.map((r) => `${r} km`));
  });

  it('lance une recherche à partir d’un terme populaire', async () => {
    http.expectOne(`${API}/categories`).flush({ data: [] });
    const chip = queryAll<HTMLButtonElement>(fixture, '.popular .chip').find((b) => b.textContent?.trim() === 'Canapé');
    chip!.click();

    expect(navigate).toHaveBeenCalledWith(['/recherche'], { queryParams: { q: 'Canapé' } });
  });

  it('transmet quoi, où et le rayon', async () => {
    http.expectOne(`${API}/categories`).flush({ data: [] });

    const q = query<HTMLInputElement>(fixture, '#hero-q');
    q.value = 'vélo';
    q.dispatchEvent(new Event('input'));
    const where = query<HTMLInputElement>(fixture, '#hero-where');
    where.value = 'Lyon';
    where.dispatchEvent(new Event('input'));
    const radius = query<HTMLSelectElement>(fixture, '#hero-radius');
    radius.selectedIndex = 3; // 50 km
    radius.dispatchEvent(new Event('change'));
    await fixture.whenStable();

    query(fixture, 'form.searchbar').dispatchEvent(new Event('submit'));

    expect(navigate).toHaveBeenCalledWith(['/recherche'], { queryParams: { q: 'vélo', ou: 'Lyon', rayon: 50 } });
  });
});
