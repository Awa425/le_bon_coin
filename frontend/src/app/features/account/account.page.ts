import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { Icon } from '../../ui/icon';

@Component({
  selector: 'app-account-page',
  imports: [RouterLink, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="container page" aria-labelledby="account-title">
      <div class="card account">
        <span class="account__avatar" aria-hidden="true">{{ initial() }}</span>
        <div class="account__body">
          <h1 id="account-title">{{ auth.user()?.name ?? 'Mon compte' }}</h1>
          <p class="muted">{{ auth.user()?.email }}</p>
        </div>
        <div class="account__actions">
          <a class="button button--primary" routerLink="/deposer">
            <app-icon name="plus" [size]="20" [stroke]="2.25" /> Déposer une annonce
          </a>
          <button type="button" class="button button--secondary" (click)="logout()">
            <app-icon name="logout" [size]="20" /> Se déconnecter
          </button>
        </div>
      </div>
      <p class="muted small note">Le suivi de vos annonces (brouillons, en modération, actives…) arrive avec le tableau de bord vendeur.</p>
    </section>
  `,
  styles: `
    .page {
      padding-block: clamp(2rem, 5vw, 3.5rem);
      max-width: 760px;
    }
    .account {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 1.25rem;
      align-items: center;
    }
    .account__avatar {
      display: grid;
      place-items: center;
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: var(--orange-100);
      color: var(--orange-700);
      font-family: var(--font-display);
      font-size: 1.75rem;
      font-weight: 800;
    }
    .account__body {
      min-width: 0;
      overflow-wrap: anywhere;
    }
    .account__actions {
      grid-column: 1 / -1;
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .note {
      margin-top: 1rem;
    }
  `,
})
export class AccountPage {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected initial(): string {
    return (this.auth.user()?.name ?? '?').trim().charAt(0).toUpperCase();
  }

  protected logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/'));
  }
}
