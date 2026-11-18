import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiError, toApiError } from '../../core/api/api-errors';
import { safeRedirect } from '../../core/auth/auth.guard';
import { AuthService } from '../../core/auth/auth.service';
import { AuthShell } from './auth-shell';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, AuthShell],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-auth-shell heading="Bon retour parmi nous" subtitle="Connectez-vous pour déposer et gérer vos annonces.">
      <form class="fields" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        @if (error()) {
          <div class="alert" role="alert">{{ errorMessage() }}</div>
        }

        <div class="field" [class.field--invalid]="invalid('email')">
          <label for="email">Adresse e-mail</label>
          <input id="email" type="email" formControlName="email" autocomplete="email" placeholder="vous@exemple.fr" />
          @if (invalid('email')) {
            <p class="field__error" role="alert">Saisissez une adresse e-mail valide.</p>
          }
        </div>

        <div class="field" [class.field--invalid]="invalid('password')">
          <label for="password">Mot de passe</label>
          <input id="password" type="password" formControlName="password" autocomplete="current-password" />
          @if (invalid('password')) {
            <p class="field__error" role="alert">Saisissez votre mot de passe.</p>
          }
        </div>

        <button type="submit" class="button button--primary button--block button--lg" [disabled]="loading()">
          {{ loading() ? 'Connexion…' : 'Se connecter' }}
        </button>

        <p class="switch-link muted">
          Pas encore de compte ?
          <a routerLink="/inscription" [queryParams]="redirectParams()">Créer un compte</a>
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
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly redirect = inject(ActivatedRoute).snapshot.queryParamMap.get('redirect');

  readonly form = new FormGroup({
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });

  readonly loading = signal(false);
  readonly error = signal<ApiError | null>(null);
  protected readonly errorMessage = computed(() => {
    const err = this.error();
    return err?.errors['email']?.[0] ?? err?.message ?? '';
  });
  protected readonly redirectParams = computed(() => (this.redirect ? { redirect: this.redirect } : {}));

  protected invalid(name: 'email' | 'password'): boolean {
    const control = this.form.controls[name];
    return control.invalid && control.touched;
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid || this.loading()) return;

    this.loading.set(true);
    this.error.set(null);
    this.auth.login(this.form.getRawValue()).subscribe({
      next: () => void this.router.navigateByUrl(safeRedirect(this.redirect)),
      error: (error: unknown) => {
        this.loading.set(false);
        this.error.set(toApiError(error));
      },
    });
  }
}
