import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { FormControl } from '@angular/forms';
import { ActivatedRoute, Router, convertToParamMap, provideRouter } from '@angular/router';
import { API_BASE_URL } from '../../core/api/api-config';
import { text, typeInto } from '../../../testing/dom';
import { LoginPage } from './login.page';
import { RegisterPage, passwordStrength } from './register.page';

const API = 'http://api.test/v1';

function configure(redirect: string | null) {
  TestBed.configureTestingModule({
    providers: [
      provideHttpClient(),
      provideHttpClientTesting(),
      provideRouter([]),
      { provide: API_BASE_URL, useValue: API },
      { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap(redirect ? { redirect } : {}) } } },
    ],
  });
}

describe('LoginPage', () => {
  afterEach(() => TestBed.inject(HttpTestingController).verify());

  it('connecte l’utilisateur puis le renvoie vers la page demandée', async () => {
    configure('/deposer');
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const fixture = TestBed.createComponent(LoginPage);
    await fixture.whenStable();

    await typeInto(fixture, '#email', 'camille@example.fr');
    await typeInto(fixture, '#password', 'MotDePasse2026');
    (fixture.nativeElement as HTMLElement).querySelector('form')!.dispatchEvent(new Event('submit'));

    TestBed.inject(HttpTestingController)
      .expectOne(`${API}/auth/login`)
      .flush({ data: { id: 'u1', name: 'Camille', email: 'camille@example.fr' }, token: 't', token_type: 'Bearer' });

    expect(navigate).toHaveBeenCalledWith('/deposer');
  });

  it('affiche le message d’erreur de l’API', async () => {
    configure(null);
    const fixture = TestBed.createComponent(LoginPage);
    await fixture.whenStable();
    fixture.componentInstance.form.setValue({ email: 'camille@example.fr', password: 'faux' });
    fixture.componentInstance.submit();

    TestBed.inject(HttpTestingController)
      .expectOne(`${API}/auth/login`)
      .flush(
        { message: 'Identifiants invalides', errors: { email: ['Ces identifiants ne correspondent pas à nos enregistrements.'] } },
        { status: 422, statusText: 'Unprocessable Content' },
      );
    await fixture.whenStable();

    expect(text(fixture)).toContain('Ces identifiants ne correspondent pas à nos enregistrements.');
  });

  it('n’envoie rien si le formulaire est invalide', async () => {
    configure(null);
    const fixture = TestBed.createComponent(LoginPage);
    await fixture.whenStable();
    fixture.componentInstance.submit();
    await fixture.whenStable();

    TestBed.inject(HttpTestingController).expectNone(`${API}/auth/login`);
    expect(text(fixture)).toContain('Saisissez une adresse e-mail valide.');
  });
});

describe('RegisterPage', () => {
  beforeEach(() => configure(null));
  afterEach(() => TestBed.inject(HttpTestingController).verify());

  it('applique la même règle de mot de passe que l’API', () => {
    expect(passwordStrength(new FormControl('court1'))).toEqual({ weak: true });
    expect(passwordStrength(new FormControl('sanschiffres'))).toEqual({ weak: true });
    expect(passwordStrength(new FormControl('MotDePasse2026'))).toBeNull();
  });

  it('signale des mots de passe différents sans appeler l’API', async () => {
    const fixture = TestBed.createComponent(RegisterPage);
    await fixture.whenStable();
    fixture.componentInstance.form.setValue({
      name: 'Camille',
      email: 'camille@example.fr',
      password: 'MotDePasse2026',
      password_confirmation: 'Autre2026xx',
    });
    fixture.componentInstance.submit();
    await fixture.whenStable();

    TestBed.inject(HttpTestingController).expectNone(`${API}/auth/register`);
    expect(text(fixture)).toContain('Les mots de passe ne correspondent pas.');
  });

  it('affiche sous le champ l’erreur serveur (e-mail déjà utilisé)', async () => {
    const fixture = TestBed.createComponent(RegisterPage);
    await fixture.whenStable();
    fixture.componentInstance.form.setValue({
      name: 'Camille',
      email: 'pris@example.fr',
      password: 'MotDePasse2026',
      password_confirmation: 'MotDePasse2026',
    });
    fixture.componentInstance.submit();

    TestBed.inject(HttpTestingController)
      .expectOne(`${API}/auth/register`)
      .flush(
        { message: 'La valeur du champ adresse e-mail est déjà utilisée.', errors: { email: ['La valeur du champ adresse e-mail est déjà utilisée.'] } },
        { status: 422, statusText: 'Unprocessable Content' },
      );
    await fixture.whenStable();

    expect(text(fixture)).toContain('La valeur du champ adresse e-mail est déjà utilisée.');
  });
});
