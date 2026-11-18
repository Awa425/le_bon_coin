import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { App } from './app';
import { API_BASE_URL } from './core/api/api-config';
import { query, text } from '../testing/dom';

describe('App (structure et en-tête)', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([]), { provide: API_BASE_URL, useValue: 'http://api.test' }],
    });
  });

  afterEach(() => TestBed.inject(HttpTestingController).verify());

  it('affiche le logo, la navigation, le dépôt et le pied de page', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();

    expect(query(fixture, 'a.skip-link').getAttribute('href')).toBe('#contenu');
    expect(query(fixture, 'main#contenu')).toBeTruthy();
    expect(query(fixture, '.logo').getAttribute('aria-label')).toBe('la Plateforme, accueil');
    expect(text(fixture)).toContain('Favoris');
    expect(text(fixture)).toContain('Messages');
    expect(text(fixture)).toContain('Mon compte');
    expect(text(fixture)).toContain('Déposer une annonce');
    expect(query(fixture, 'footer')).toBeTruthy();
  });

  it('envoie la recherche de l’en-tête vers la page de recherche', async () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();

    const input = query<HTMLInputElement>(fixture, '#header-search');
    input.value = '  vélo électrique ';
    input.dispatchEvent(new Event('input'));
    await fixture.whenStable();
    query(fixture, 'form[role="search"]').dispatchEvent(new Event('submit'));

    expect(navigate).toHaveBeenCalledWith(['/recherche'], { queryParams: { q: 'vélo électrique' } });
  });

  it('pointe « Mon compte » vers la connexion pour un visiteur', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();

    const account = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll('a.nav-item')).find((a) =>
      a.textContent?.includes('Mon compte'),
    );
    expect(account?.getAttribute('href')).toBe('/connexion');
  });
});
