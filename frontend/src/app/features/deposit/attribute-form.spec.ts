import { AttributeDefinition } from '../../catalog/category.models';
import { attributeErrorMessage, attributeValues, buildAttributeGroup } from './attribute-form';

const def = (overrides: Partial<AttributeDefinition>): AttributeDefinition => ({
  key: 'cle',
  label: 'Libellé',
  type: 'text',
  unit: null,
  options: [],
  min: null,
  max: null,
  required: false,
  filterable: true,
  ...overrides,
});

const schema: AttributeDefinition[] = [
  def({ key: 'kilometrage', label: 'Kilométrage', type: 'number', min: 0, max: 2_000_000, required: true }),
  def({ key: 'carburant', label: 'Carburant', type: 'select', options: ['essence', 'diesel'], required: true }),
  def({ key: 'options', label: 'Options', type: 'multi_select', options: ['gps', 'clim'] }),
  def({ key: 'premiere_main', label: 'Première main', type: 'boolean' }),
  def({ key: 'couleur', label: 'Couleur' }),
];

describe('buildAttributeGroup', () => {
  it('crée un contrôle par attribut, pré-rempli avec les valeurs du brouillon', () => {
    const group = buildAttributeGroup(schema, { carburant: 'diesel', options: ['gps'] });

    expect(Object.keys(group.controls)).toEqual(['kilometrage', 'carburant', 'options', 'premiere_main', 'couleur']);
    expect(group.controls['carburant'].value).toBe('diesel');
    expect(group.controls['options'].value).toEqual(['gps']);
    expect(group.controls['premiere_main'].value).toBeNull();
  });

  it('applique les règles du schéma : obligatoire, bornes, options', () => {
    const group = buildAttributeGroup(schema);
    expect(group.controls['kilometrage'].hasError('required')).toBe(true);
    expect(group.controls['carburant'].hasError('required')).toBe(true);
    expect(group.controls['options'].valid).toBe(true);

    group.controls['kilometrage'].setValue(-1);
    expect(group.controls['kilometrage'].hasError('min')).toBe(true);

    group.controls['carburant'].setValue('charbon');
    expect(group.controls['carburant'].hasError('option')).toBe(true);

    group.controls['options'].setValue(['gps', 'jacuzzi']);
    expect(group.controls['options'].hasError('option')).toBe(true);
  });

  it('exige au moins une case pour un multi-choix obligatoire', () => {
    const group = buildAttributeGroup([def({ key: 'equipements', type: 'multi_select', options: ['a'], required: true })]);

    expect(group.controls['equipements'].hasError('required')).toBe(true);
    group.controls['equipements'].setValue(['a']);
    expect(group.valid).toBe(true);
  });
});

describe('attributeValues', () => {
  it('convertit les nombres et retire les champs vides', () => {
    const group = buildAttributeGroup(schema);
    group.patchValue({ kilometrage: '84000', carburant: 'essence', options: [], premiere_main: false, couleur: '' });

    expect(attributeValues(group, schema)).toEqual({ kilometrage: 84000, carburant: 'essence', premiere_main: false });
  });

  it('accepte la virgule décimale française', () => {
    const group = buildAttributeGroup([def({ key: 'surface', type: 'number' })]);
    group.patchValue({ surface: '42,5' });

    expect(attributeValues(group, [def({ key: 'surface', type: 'number' })])).toEqual({ surface: 42.5 });
  });
});

describe('attributeErrorMessage', () => {
  it('produit des messages français avec le libellé', () => {
    const km = schema[0];
    expect(attributeErrorMessage(km, { required: true })).toBe('Kilométrage est obligatoire.');
    expect(attributeErrorMessage(km, { min: { min: 0 } })).toBe('Kilométrage doit être supérieur ou égal à 0.');
    expect(attributeErrorMessage(km, null)).toBeNull();
  });
});
