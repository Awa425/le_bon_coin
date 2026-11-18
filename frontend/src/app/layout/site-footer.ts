import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Logo } from '../ui/logo';

@Component({
  selector: 'app-site-footer',
  imports: [RouterLink, Logo],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <footer class="footer">
      <div class="container footer__inner">
        <div class="footer__brand">
          <app-logo />
          <p class="muted small">Les petites annonces près de chez vous, en toute confiance.</p>
        </div>
        <nav aria-label="Liens utiles" class="footer__links">
          <a routerLink="/deposer">Déposer une annonce</a>
          <a routerLink="/recherche">Rechercher</a>
          <a routerLink="/compte">Mon compte</a>
        </nav>
      </div>
      <div class="container footer__legal small muted">© {{ year }} la Plateforme · Nom et identité visuelle provisoires</div>
    </footer>
  `,
  styles: `
    .footer {
      margin-top: auto;
      padding-block: 2.5rem 1.5rem;
      border-top: 1px solid var(--sand-200);
      background: var(--sand-50);
    }
    .footer__inner {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 1.5rem;
    }
    .footer__brand {
      display: grid;
      gap: 0.75rem;
      max-width: 22rem;
    }
    .footer__links {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem 1.5rem;
      align-items: center;
    }
    .footer__links a {
      color: var(--ink-900);
      font-weight: 500;
      text-decoration: none;
    }
    .footer__links a:hover {
      color: var(--orange-700);
      text-decoration: underline;
    }
    .footer__legal {
      margin-top: 2rem;
      padding-top: 1.25rem;
      border-top: 1px solid var(--sand-200);
    }
  `,
})
export class SiteFooter {
  protected readonly year = new Date().getFullYear();
}
