import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormControl, FormGroup, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiError, fieldError, toApiError } from '../../core/api/api-errors';
import { safeRedirect } from '../../core/auth/auth.guard';
import { AuthService } from '../../core/auth/auth.service';
import { AuthShell } from './auth-shell';

/** Même règle que l'API : 10 caractères minimum, au moins une lettre et un chiffre. */
export function passwordStrength(control: AbstractControl): ValidationErrors | null {
  const value = String(control.value ?? '');
  return value.length >= 10 && /[A-Za-z]/.test(value) && /\d/.test(value) ? null : { weak: true };
}

function passwordsMatch(group: AbstractControl): ValidationErrors | null {
  const password = group.get('password')?.value;
  const confirmation = group.get('password_confirmation')?.value;
  return password === confirmation ? null : { mismatch: true };
}

type Field = 'name' | 'email' | 'password' | 'password_confirmation';

@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, AuthShell],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-auth-shell heading="Créer un compte" subtitle="Gratuit, et prêt en moins d'une minute.">
      <form class="fields" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        @if (error(); as err) {
          <div class="alert" role="alert">{{ err.message }}</div>
        }

        <div class="field" [class.field--invalid]="message('name')">
          <label for="name">Nom ou pseudo</label>
          <input id="name" formControlName="name" autocomplete="nickname" placeholder="Visible sur vos annonces" />
          @if (message('name'); as msg) { <p class="field__error" role="alert">{{ msg }}</p> }
        </div>

        <div class="field" [class.field--invalid]="message('email')">
          <label for="email">Adresse e-mail</label>
          <input id="email" type="email" formControlName="email" autocomplete="email" placeholder="vous@exemple.fr" />
          @if (message('email'); as msg) { <p class="field__error" role="alert">{{ msg }}</p> }
        </div>

        <div class="field" [class.field--invalid]="message('password')">
          <label for="password">Mot de passe</label>
          <input id="password" type="password" formControlName="password" autocomplete="new-password" aria-describedby="password-hint" />
          <p id="password-hint" class="field__hint">10 caractères minimum, avec au moins une lettre et un chiffre.</p>
          @if (message('password'); as msg) { <p class="field__error" role="alert">{{ msg }}</p> }
        </div>

        <div class="field" [class.field--invalid]="message('password_confirmation')">
          <label for="password_confirmation">Confirmer le mot de passe</label>
          <input id="password_confirmation" type="password" formControlName="password_confirmation" autocomplete="new-password" />
          @if (message('password_confirmation'); as msg) { <p class="field__error" role="alert">{{ msg }}</p> }
        </div>

        <button type="submit" class="button button--primary button--block button--lg" [disabled]="loading()">
          {{ loading() ? 'Création…' : 'Créer mon compte' }}
        </button>
        <p class="switch-link muted">
          Déjà inscrit ? <a routerLink="/connexion" [queryParams]="redirectParams()">Se connecter</a>
        </p>
      </form>
    </app-auth-shell>
  `,
  styles: `
    .switch-link {
      text-align: center;
    }
    .switch-link a {
      font-weight: 650;
    }
  `,
})
export class RegisterPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly redirect = inject(ActivatedRoute).snapshot.queryParamMap.get('redirect');

  readonly form = new FormGroup(
    {
      name: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(100)] }),
      email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
      password: new FormControl('', { nonNullable: true, validators: [passwordStrength] }),
      password_confirmation: new FormControl('', { nonNullable: true }),
    },
    { validators: passwordsMatch },
  );

  readonly loading = signal(false);
  readonly error = signal<ApiError | null>(null);
  protected readonly redirectParams = computed(() => (this.redirect ? { redirect: this.redirect } : {}));

  private static readonly CLIENT_MESSAGES: Record<Field, string> = {
    name: 'Indiquez un nom ou un pseudo.',
    email: 'Saisissez une adresse e-mail valide.',
    password: '10 caractères minimum, avec au moins une lettre et un chiffre.',
    password_confirmation: 'Les mots de passe ne correspondent pas.',
  };

  protected message(field: Field): string | null {
    const control = this.form.controls[field];
    if (control.touched) {
      const invalid = field === 'password_confirmation' ? this.form.hasError('mismatch') : control.invalid;
      if (invalid) return RegisterPage.CLIENT_MESSAGES[field];
    }
    return fieldError(this.error()?.errors ?? {}, field);
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid || this.loading()) return;

    this.loading.set(true);
    this.error.set(null);
    this.auth.register(this.form.getRawValue()).subscribe({
      next: () => void this.router.navigateByUrl(safeRedirect(this.redirect)),
      error: (error: unknown) => {
        this.loading.set(false);
        this.error.set(toApiError(error));
      },
    });
  }
}
