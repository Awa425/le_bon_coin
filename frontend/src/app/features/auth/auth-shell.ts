import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { Icon } from '../../ui/icon';

/** Cadre commun des pages de connexion / inscription : panneau de marque + formulaire. */
@Component({
  selector: 'app-auth-shell',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="container shell">
      <aside class="shell__brand" aria-hidden="true">
        <p class="shell__kicker">la Plateforme</p>
        <p class="shell__pitch">Vendez ce qui ne vous sert plus, trouvez ce qui vous manque.</p>
        <ul class="shell__points">
          <li><app-icon name="sparkles" [size]="20" /> Dépôt gratuit en 2 minutes</li>
          <li><app-icon name="shield" [size]="20" /> Annonces vérifiées par notre équipe</li>
          <li><app-icon name="message" [size]="20" /> Échanges via la messagerie intégrée</li>
        </ul>
      </aside>
      <section class="shell__panel" [attr.aria-labelledby]="titleId">
        <h1 [id]="titleId">{{ heading() }}</h1>
        @if (subtitle()) {
          <p class="muted shell__subtitle">{{ subtitle() }}</p>
        }
        <ng-content />
      </section>
    </div>
  `,
  styles: `
    .shell {
      display: grid;
      gap: 2rem;
      padding-block: clamp(1.5rem, 5vw, 3.5rem);
      max-width: 1040px;
    }
    .shell__brand {
      display: none;
      position: relative;
      overflow: hidden;
      padding: 2.5rem;
      border-radius: var(--radius-lg);
      color: var(--white);
      background:
        radial-gradient(420px 260px at 110% -10%, rgb(255 255 255 / 0.22), transparent 60%),
        radial-gradient(360px 260px at -20% 120%, rgb(255 211 181 / 0.35), transparent 60%),
        linear-gradient(150deg, var(--orange-500), var(--orange-700));
    }
    .shell__kicker {
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 1.125rem;
      letter-spacing: -0.02em;
      opacity: 0.9;
    }
    .shell__pitch {
      margin-top: 1.5rem;
      font-family: var(--font-display);
      font-size: 2rem;
      font-weight: 700;
      line-height: 1.12;
      letter-spacing: -0.03em;
    }
    .shell__points {
      display: grid;
      gap: 0.875rem;
      margin: 2.5rem 0 0;
      padding: 0;
      list-style: none;
      font-weight: 500;
    }
    .shell__points li {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .shell__panel {
      width: 100%;
      max-width: 460px;
      margin-inline: auto;
      align-self: center;
    }
    .shell__subtitle {
      margin-top: 0.5rem;
      margin-bottom: 1.75rem;
    }
    @media (min-width: 900px) {
      .shell {
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: stretch;
      }
      .shell__brand {
        display: block;
      }
    }
  `,
})
export class AuthShell {
  readonly heading = input.required<string>();
  readonly subtitle = input<string | null>(null);
  protected readonly titleId = `auth-title-${Math.random().toString(36).slice(2, 8)}`;
}
