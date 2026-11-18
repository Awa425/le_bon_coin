import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { API_BASE_URL } from '../core/api/api-config';
import { CategoryDetail, CategoryNode } from './category.models';

@Injectable({ providedIn: 'root' })
export class CategoryService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);

  /** L'arbre change rarement : une seule requête par session. */
  private readonly tree$ = this.http
    .get<{ data: CategoryNode[] }>(`${this.api}/categories`)
    .pipe(
      map(({ data }) => data),
      shareReplay({ bufferSize: 1, refCount: false }),
    );

  tree(): Observable<CategoryNode[]> {
    return this.tree$;
  }

  detail(slug: string): Observable<CategoryDetail> {
    return this.http
      .get<{ data: CategoryDetail }>(`${this.api}/categories/${encodeURIComponent(slug)}`)
      .pipe(map(({ data }) => data));
  }
}

/** Chemin (racine → cible) vers une catégorie de l'arbre, ou [] si absente. */
export function pathTo(tree: CategoryNode[], id: string): CategoryNode[] {
  for (const node of tree) {
    if (node.id === id) {
      return [node];
    }
    const sub = pathTo(node.children ?? [], id);
    if (sub.length > 0) {
      return [node, ...sub];
    }
  }
  return [];
}
