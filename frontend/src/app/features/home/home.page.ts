import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { CategoryNode } from '../../catalog/category.models';
import { CategoryService } from '../../catalog/category.service';
import { Icon, IconName, categoryIcon } from '../../ui/icon';

/** Rayons proposés par le cahier des charges (RECH-04). */
export const RADIUS_OPTIONS = [5, 10, 20, 50, 100, 200] as const;

/** Accueil rendu côté serveur (SEO) : recherche, catégories et promesse de la plateforme. */
@Component({
  selector: 'app-home-page',
  imports: [RouterLink, FormsModule, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styleUrl: './home.page.scss',
  templateUrl: './home.page.html',
})
export class HomePage {
  private readonly router = inject(Router);

  protected readonly radiusOptions = RADIUS_OPTIONS;
  protected readonly popular = ['Vélo', 'iPhone', 'Canapé', 'Appartement', 'Poussette'];
  protected readonly promises: { icon: IconName; title: string; text: string }[] = [
    {
      icon: 'sparkles',
      title: 'Déposez en 2 minutes',
      text: 'Un formulaire guidé, adapté à chaque catégorie. Gratuit pour les particuliers.',
    },
    {
      icon: 'shield',
      title: 'Achetez en confiance',
      text: 'Messagerie intégrée, profils vérifiés et modération de chaque annonce.',
    },
    {
      icon: 'truck',
      title: 'Près de chez vous… ou livré',
      text: 'Remise en main propre ou envoi en point relais, selon ce qui vous arrange.',
    },
  ];

  protected query = '';
  protected where = '';
  protected radius: number = 10;

  protected readonly categories = toSignal(
    inject(CategoryService)
      .tree()
      .pipe(catchError(() => of([] as CategoryNode[]))),
  );

  protected icon(category: CategoryNode): IconName {
    return categoryIcon(category.slug);
  }

  protected subcategories(category: CategoryNode): string {
    return (category.children ?? []).map((c) => c.name).join(', ');
  }

  protected pick(term: string): void {
    this.query = term;
    this.search();
  }

  protected search(): void {
    const queryParams: Record<string, string | number> = {};
    if (this.query.trim()) queryParams['q'] = this.query.trim();
    if (this.where.trim()) {
      queryParams['ou'] = this.where.trim();
      queryParams['rayon'] = this.radius;
    }
    void this.router.navigate(['/recherche'], { queryParams });
  }
}
