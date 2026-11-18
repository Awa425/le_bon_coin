import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AttributeDefinition } from '../../catalog/category.models';
import { ValidationErrors } from '../../core/api/api-errors';
import { query, queryAll, text, typeInto } from '../../../testing/dom';
import { AttributeFields } from './attribute-fields';
import { buildAttributeGroup } from './attribute-form';

const schema: AttributeDefinition[] = [
  { key: 'kilometrage', label: 'Kilométrage', type: 'number', unit: 'km', options: [], min: 0, max: 2000000, required: true, filterable: true },
  { key: 'carburant', label: 'Carburant', type: 'select', unit: null, options: ['essence', 'diesel'], required: true, filterable: true },
  { key: 'options', label: 'Options', type: 'multi_select', unit: null, options: ['gps', 'toit_ouvrant'], min: null, max: null, required: false, filterable: true },
  { key: 'premiere_main', label: 'Première main', type: 'boolean', unit: null, options: [], min: null, max: null, required: false, filterable: true },
] as AttributeDefinition[];

@Component({
  imports: [AttributeFields],
  template: `<app-attribute-fields [schema]="schema" [group]="group" [serverErrors]="errors()" />`,
})
class Host {
  schema = schema;
  group = buildAttributeGroup(schema);
  errors = signal<ValidationErrors>({});
}

describe('AttributeFields', () => {
  let fixture: ComponentFixture<Host>;

  beforeEach(async () => {
    fixture = TestBed.createComponent(Host);
    await fixture.whenStable();
  });

  it('génère le bon contrôle pour chaque type d’attribut', () => {
    expect(query<HTMLInputElement>(fixture, '#attr-kilometrage').type).toBe('number');
    expect(query<HTMLInputElement>(fixture, '#attr-kilometrage').getAttribute('min')).toBe('0');
    expect(queryAll(fixture, '#attr-carburant option').map((o) => o.textContent?.trim())).toEqual([
      'Choisir…',
      'Essence',
      'Diesel',
    ]);
    expect(queryAll(fixture, 'input[type="checkbox"]')).toHaveLength(2);
    expect(queryAll(fixture, 'input[type="radio"]')).toHaveLength(2);
    expect(text(fixture)).toContain('Kilométrage (km)');
    expect(text(fixture)).toContain('Toit ouvrant');
  });

  it('associe chaque libellé à son champ (accessibilité)', () => {
    expect(query(fixture, 'label[for="attr-kilometrage"]')).toBeTruthy();
    expect(query(fixture, 'label[for="attr-carburant"]')).toBeTruthy();
  });

  it('affiche une erreur après saisie invalide', async () => {
    await typeInto(fixture, '#attr-kilometrage', '-5');

    expect(text(fixture)).toContain('Kilométrage doit être supérieur ou égal à 0.');
    expect(query(fixture, '#attr-kilometrage').getAttribute('aria-invalid')).toBe('true');
  });

  it('met à jour la valeur d’un multi-choix via les cases à cocher', async () => {
    const [gps, toit] = queryAll<HTMLInputElement>(fixture, 'input[type="checkbox"]');
    gps.click();
    toit.click();
    gps.click();
    await fixture.whenStable();

    expect(fixture.componentInstance.group.controls['options'].value).toEqual(['toit_ouvrant']);
  });

  it('affiche les erreurs renvoyées par l’API', async () => {
    fixture.componentInstance.errors.set({ 'attribute_values.carburant': ['Le champ Carburant est obligatoire.'] });
    await fixture.whenStable();

    expect(text(fixture)).toContain('Le champ Carburant est obligatoire.');
  });
});
