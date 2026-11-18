import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Icon, IconName } from '../../ui/icon';

/** Page d'attente pour les fonctionnalités planifiées (recherche, favoris, messagerie). */
@Component({
  selector: 'app-coming-soon-page',
  imports: [RouterLink, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="container soon" aria-labelledby="soon-title">
      <span class="soon__icon"><app-icon [name]="icon()" [size]="32" /></span>
      <h1 id="soon-title">{{ heading() }}</h1>
      <p class="muted">{{ text() }}</p>
      @if (query()) {
        <p class="soon__query">Votre recherche : <strong>« {{ query() }} »</strong></p>
      }
      <a class="button button--primary" routerLink="/">Retour à l'accueil</a>
    </section>
  `,
  styles: `
    .soon {
      display: grid;
      justify-items: center;
      gap: 1rem;
      max-width: 560px;
      padding-block: clamp(3rem, 10vw, 6rem);
      text-align: center;
    }
    .soon__icon {
      display: grid;
      place-items: center;
      width: 72px;
      height: 72px;
      border-radius: 22px;
      background: var(--orange-100);
      color: var(--orange-700);
    }
    .soon__query {
      padding: 0.5rem 1rem;
      border-radius: var(--radius-pill);
      background: var(--sand-100);
    }
  `,
})
export class ComingSoonPage {
  /** Données de route (withComponentInputBinding). */
  readonly heading = input('Bientôt disponible');
  readonly text = input('Cette fonctionnalité est en cours de construction.');
  readonly icon = input<IconName>('sparkles');

  private readonly params = inject(ActivatedRoute).snapshot.queryParamMap;
  protected readonly query = computed(() => this.params.get('q'));
}
