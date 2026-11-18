import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Icon, IconName, categoryIcon } from './icon';
import { Logo } from './logo';

@Component({
  imports: [Icon],
  template: `<app-icon [name]="name()" [label]="label()" [size]="32" />`,
})
class IconHost {
  name = signal<IconName>('search');
  label = signal<string | null>(null);
}

describe('Icon', () => {
  it('rend un SVG décoratif masqué aux lecteurs d’écran', async () => {
    const fixture = TestBed.createComponent(IconHost);
    await fixture.whenStable();
    const svg = (fixture.nativeElement as HTMLElement).querySelector('svg')!;

    expect(svg.getAttribute('aria-hidden')).toBe('true');
    expect(svg.getAttribute('width')).toBe('32');
    expect(svg.querySelectorAll('circle, path').length).toBe(2);
  });

  it('expose un nom accessible quand l’icône porte seule le sens', async () => {
    const fixture = TestBed.createComponent(IconHost);
    fixture.componentInstance.label.set('Rechercher');
    fixture.componentInstance.name.set('heart');
    await fixture.whenStable();
    const svg = (fixture.nativeElement as HTMLElement).querySelector('svg')!;

    expect(svg.getAttribute('role')).toBe('img');
    expect(svg.getAttribute('aria-label')).toBe('Rechercher');
    expect(svg.getAttribute('aria-hidden')).toBeNull();
  });

  it('associe une icône à chaque catégorie racine du catalogue', () => {
    expect(categoryIcon('vehicules')).toBe('car');
    expect(categoryIcon('maison-jardin')).toBe('sofa');
    expect(categoryIcon('materiel-professionnel')).toBe('factory');
    expect(categoryIcon('nouvelle-categorie')).toBe('grid');
  });
});

describe('Logo', () => {
  it('renvoie vers l’accueil avec un nom accessible', async () => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    const fixture = TestBed.createComponent(Logo);
    await fixture.whenStable();
    const link = (fixture.nativeElement as HTMLElement).querySelector('a')!;

    expect(link.getAttribute('href')).toBe('/');
    expect(link.getAttribute('aria-label')).toBe('la Plateforme, accueil');
    expect(link.textContent).toContain('Plateforme');
  });
});
