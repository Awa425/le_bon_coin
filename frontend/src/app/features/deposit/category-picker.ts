import { ChangeDetectionStrategy, Component, computed, effect, input, model, signal, untracked } from '@angular/core';
import { CategoryNode } from '../../catalog/category.models';
import { pathTo } from '../../catalog/category.service';

/**
 * Sélection en cascade (3 niveaux maximum) jusqu'à une catégorie finale,
 * seule acceptée pour un dépôt d'annonce.
 */
@Component({
  selector: 'app-category-picker',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'fields' },
  template: `
    @for (level of levels(); track $index; let depth = $index) {
      <div class="field">
        <label [for]="'category-level-' + depth">
          {{ depth === 0 ? 'Catégorie' : 'Sous-catégorie' }} <span class="required" aria-hidden="true">*</span>
        </label>
        <select
          [id]="'category-level-' + depth"
          [value]="selectedIds()[depth] ?? ''"
          (change)="select(depth, $any($event.target).value)"
          [attr.aria-invalid]="invalid() && depth === levels().length - 1"
        >
          <option value="">Choisir…</option>
          @for (node of level; track node.id) {
            <option [value]="node.id" [selected]="node.id === selectedIds()[depth]">{{ node.name }}</option>
          }
        </select>
      </div>
    }
  `,
})
export class CategoryPicker {
  readonly tree = input.required<CategoryNode[]>();
  /** Identifiant de la catégorie finale retenue (null tant qu'aucune feuille n'est choisie). */
  readonly value = model<string | null>(null);
  readonly invalid = input(false);

  protected readonly selectedIds = signal<string[]>([]);

  /** Listes affichées : la racine, puis les enfants de chaque niveau choisi tant qu'il en a. */
  protected readonly levels = computed<CategoryNode[][]>(() => {
    const levels: CategoryNode[][] = [this.tree()];
    let options = this.tree();

    for (const id of this.selectedIds()) {
      const children = options.find((n) => n.id === id)?.children ?? [];
      if (children.length === 0) break;
      levels.push(children);
      options = children;
    }

    return levels;
  });

  constructor() {
    // Pré-sélection depuis une valeur existante (reprise d'un brouillon).
    effect(() => {
      const id = this.value();
      const tree = this.tree();
      const current = untracked(this.selectedIds);
      if (id && current[current.length - 1] !== id) {
        this.selectedIds.set(pathTo(tree, id).map((n) => n.id));
      }
    });
  }

  protected select(depth: number, id: string): void {
    const ids = [...this.selectedIds().slice(0, depth), ...(id ? [id] : [])];
    this.selectedIds.set(ids);

    const node = id ? (this.levels()[depth] ?? []).find((n) => n.id === id) : undefined;
    const isLeaf = node !== undefined && (node.children ?? []).length === 0;
    this.value.set(isLeaf ? node.id : null);
  }
}
