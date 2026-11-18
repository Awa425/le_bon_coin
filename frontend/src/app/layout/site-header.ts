import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../core/auth/auth.service';
import { Icon } from '../ui/icon';
import { Logo } from '../ui/logo';

@Component({
  selector: 'app-site-header',
  imports: [RouterLink, RouterLinkActive, FormsModule, Icon, Logo],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styleUrl: './site-header.scss',
  template: `
    <header class="header">
      <div class="container header__inner">
        <app-logo />

        <form class="header__search" role="search" (submit)="$event.preventDefault(); search()">
          <app-icon name="search" [size]="20" />
          <label for="header-search" class="sr-only">Rechercher une annonce</label>
          <input
            id="header-search"
            type="search"
            name="q"
            [(ngModel)]="query"
            placeholder="Rechercher un vélo, un canapé, un T2…"
            autocomplete="off"
          />
        </form>

        <nav class="header__nav" aria-label="Navigation principale">
          <a class="nav-item" routerLink="/favoris" routerLinkActive="nav-item--active">
            <app-icon name="heart" [size]="22" />
            <span>Favoris</span>
          </a>
          <a class="nav-item" routerLink="/messages" routerLinkActive="nav-item--active">
            <app-icon name="message" [size]="22" />
            <span>Messages</span>
          </a>
          <a
            class="nav-item"
            [routerLink]="auth.isAuthenticated() ? '/compte' : '/connexion'"
            routerLinkActive="nav-item--active"
          >
            <app-icon name="user" [size]="22" />
            <span class="nav-item__label">{{ accountLabel() }}</span>
          </a>
          <a class="button button--primary header__cta" routerLink="/deposer">
            <app-icon name="plus" [size]="20" [stroke]="2.25" />
            <span class="header__cta-label">Déposer une annonce</span>
            <span class="sr-only header__cta-short">Déposer une annonce</span>
          </a>
        </nav>
      </div>
    </header>
  `,
})
export class SiteHeader {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  protected query = '';

  protected accountLabel(): string {
    const name = this.auth.user()?.name;
    return this.auth.isAuthenticated() && name ? name.split(' ')[0] : 'Mon compte';
  }

  protected search(): void {
    const q = this.query.trim();
    void this.router.navigate(['/recherche'], { queryParams: q ? { q } : {} });
  }
}
