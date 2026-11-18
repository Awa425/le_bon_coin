import { AbstractControl, FormControl, FormGroup, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { AttributeDefinition } from '../../catalog/category.models';
import { AttributeValue } from '../../listings/listing.models';

export type AttributeGroup = FormGroup<Record<string, FormControl<AttributeValue | null>>>;

/** Valeur obligatoire, y compris pour une liste multi-choix (au moins une case). */
const requiredValue: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
  const value = control.value as unknown;
  const empty = value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
  return empty ? { required: true } : null;
};

/** Une case booléenne obligatoire doit seulement avoir été renseignée (oui ou non). */
const requiredBoolean: ValidatorFn = (control) => (control.value === null ? { required: true } : null);

function allowedOptions(options: string[]): ValidatorFn {
  return (control) => {
    const value = control.value as unknown;
    const values = Array.isArray(value) ? value : value === null || value === '' ? [] : [value];
    return values.every((v) => options.includes(String(v))) ? null : { option: true };
  };
}

/** Construit un groupe de contrôles fidèle au schéma (mêmes règles que la validation Laravel). */
export function buildAttributeGroup(
  schema: AttributeDefinition[],
  values: Record<string, AttributeValue> = {},
): AttributeGroup {
  const controls: Record<string, FormControl<AttributeValue | null>> = {};

  for (const definition of schema) {
    const validators: ValidatorFn[] = [];
    let initial: AttributeValue | null = values[definition.key] ?? null;

    switch (definition.type) {
      case 'number':
        validators.push(Validators.pattern(/^-?\d+([.,]\d+)?$/));
        if (definition.min !== null) validators.push(Validators.min(definition.min));
        if (definition.max !== null) validators.push(Validators.max(definition.max));
        break;
      case 'text':
        validators.push(Validators.maxLength(255));
        break;
      case 'select':
        validators.push(allowedOptions(definition.options));
        break;
      case 'multi_select':
        validators.push(allowedOptions(definition.options));
        initial = Array.isArray(initial) ? initial : [];
        break;
      case 'boolean':
        initial = typeof initial === 'boolean' ? initial : null;
        break;
    }

    if (definition.required) {
      validators.push(definition.type === 'boolean' ? requiredBoolean : requiredValue);
    }

    controls[definition.key] = new FormControl<AttributeValue | null>(initial, { validators });
  }

  return new FormGroup(controls);
}

/** Valeurs à envoyer à l'API : nombres convertis, champs vides retirés. */
export function attributeValues(group: AttributeGroup, schema: AttributeDefinition[]): Record<string, AttributeValue> {
  const result: Record<string, AttributeValue> = {};

  for (const definition of schema) {
    const value = group.controls[definition.key]?.value;

    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
      continue;
    }

    result[definition.key] = definition.type === 'number' ? Number(String(value).replace(',', '.')) : value;
  }

  return result;
}

/** Message d'erreur client lisible pour un contrôle d'attribut. */
export function attributeErrorMessage(definition: AttributeDefinition, errors: ValidationErrors | null): string | null {
  if (!errors) return null;
  if (errors['required']) return `${definition.label} est obligatoire.`;
  if (errors['pattern']) return `${definition.label} doit être un nombre.`;
  if (errors['min']) return `${definition.label} doit être supérieur ou égal à ${definition.min}.`;
  if (errors['max']) return `${definition.label} doit être inférieur ou égal à ${definition.max}.`;
  if (errors['maxlength']) return `${definition.label} est trop long (255 caractères maximum).`;
  if (errors['option']) return `Valeur non autorisée pour ${definition.label}.`;
  return `${definition.label} est invalide.`;
}
