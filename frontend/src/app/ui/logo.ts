import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

/** Logo provisoire « la Plateforme » (nom de marque à confirmer, cahier des charges §7.3). */
@Component({
  selector: 'app-logo',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a routerLink="/" class="logo" aria-label="la Plateforme, accueil">
      <svg class="logo__mark" viewBox="0 0 36 36" width="36" height="36" aria-hidden="true" focusable="false">
        <rect width="36" height="36" rx="10" fill="#E8590C" />
        <path
          d="M11 10.5h7.5a6 6 0 0 1 0 12H15v4.5"
          fill="none"
          stroke="#fff"
          stroke-width="3.2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="18.5" cy="16.5" r="1.9" fill="#fff" />
      </svg>
      <span class="logo__text">la <strong>Plateforme</strong></span>
    </a>
  `,
  styles: `
    .logo {
      display: inline-flex;
      align-items: center;
      gap: 0.625rem;
      color: var(--ink-900);
      text-decoration: none;
      flex-shrink: 0;
    }
    .logo__text {
      font-family: var(--font-display);
      font-size: 1.375rem;
      font-weight: 600;
      letter-spacing: -0.03em;
      line-height: 1;
    }
    .logo__text strong {
      font-weight: 800;
      color: var(--orange-500);
    }
    @media (max-width: 519px) {
      .logo__text {
        display: none;
      }
    }
  `,
})
export class Logo {}
