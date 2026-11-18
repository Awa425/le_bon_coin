import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CategoryNode } from '../../catalog/category.models';
import { queryAll, selectOption } from '../../../testing/dom';
import { CategoryPicker } from './category-picker';

const tree: CategoryNode[] = [
  {
    id: 'vehicules',
    name: 'Véhicules',
    slug: 'vehicules',
    description: null,
    children: [
      { id: 'voitures', name: 'Voitures', slug: 'voitures', description: null, children: [] },
      { id: 'motos', name: 'Motos', slug: 'motos', description: null, children: [] },
    ],
  },
  { id: 'services', name: 'Services', slug: 'services', description: null, children: [] },
];

@Component({
  imports: [CategoryPicker],
  template: `<app-category-picker [tree]="tree" [(value)]="value" />`,
})
class Host {
  tree = tree;
  value = signal<string | null>(null);
}

describe('CategoryPicker', () => {
  let fixture: ComponentFixture<Host>;

  beforeEach(async () => {
    fixture = TestBed.createComponent(Host);
    await fixture.whenStable();
  });

  it('affiche les sous-catégories en cascade et ne retient qu’une catégorie finale', async () => {
    expect(queryAll(fixture, 'select')).toHaveLength(1);

    await selectOption(fixture, '#category-level-0', 1); // Véhicules
    expect(queryAll(fixture, 'select')).toHaveLength(2);
    expect(fixture.componentInstance.value()).toBeNull();

    await selectOption(fixture, '#category-level-1', 2); // Motos
    expect(fixture.componentInstance.value()).toBe('motos');
  });

  it('accepte directement une racine sans sous-catégorie', async () => {
    await selectOption(fixture, '#category-level-0', 2); // Services

    expect(queryAll(fixture, 'select')).toHaveLength(1);
    expect(fixture.componentInstance.value()).toBe('services');
  });

  it('réinitialise la sélection quand on change de branche', async () => {
    await selectOption(fixture, '#category-level-0', 1);
    await selectOption(fixture, '#category-level-1', 1);
    expect(fixture.componentInstance.value()).toBe('voitures');

    await selectOption(fixture, '#category-level-0', 0); // « Choisir… »
    expect(fixture.componentInstance.value()).toBeNull();
    expect(queryAll(fixture, 'select')).toHaveLength(1);
  });

  it('reprend la sélection d’un brouillon existant', async () => {
    fixture.componentInstance.value.set('voitures');
    await fixture.whenStable();

    const selects = queryAll<HTMLSelectElement>(fixture, 'select');
    expect(selects).toHaveLength(2);
    expect(selects[0].value).toBe('vehicules');
    expect(selects[1].value).toBe('voitures');
  });
});
