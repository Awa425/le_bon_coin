import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { AttributeDefinition } from '../../catalog/category.models';
import { ValidationErrors, fieldError } from '../../core/api/api-errors';
import { AttributeGroup, attributeErrorMessage } from './attribute-form';

/** Champs de caractéristiques générés depuis le schéma de la catégorie (DEP-01). */
@Component({
  selector: 'app-attribute-fields',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div [formGroup]="group()" class="fields attributes">
      @for (attr of schema(); track attr.key) {
        @let error = errorFor(attr);
        <div class="field" [class.field--invalid]="error">
          @switch (attr.type) {
            @case ('boolean') {
              <fieldset class="choices">
                <legend>{{ attr.label }}@if (attr.required) {<span class="required" aria-hidden="true"> *</span>}</legend>
                <label class="choice"><input type="radio" [formControlName]="attr.key" [value]="true" /><span>Oui</span></label>
                <label class="choice"><input type="radio" [formControlName]="attr.key" [value]="false" /><span>Non</span></label>
              </fieldset>
            }
            @case ('multi_select') {
              <fieldset class="choices">
                <legend>{{ attr.label }}@if (attr.required) {<span class="required" aria-hidden="true"> *</span>}</legend>
                @for (option of attr.options; track option) {
                  <label class="choice">
                    <input
                      type="checkbox"
                      [checked]="isChecked(attr.key, option)"
                      (change)="toggle(attr.key, option, $any($event.target).checked)"
                    />
                    <span>{{ humanize(option) }}</span>
                  </label>
                }
              </fieldset>
            }
            @case ('select') {
              <label [for]="'attr-' + attr.key">{{ attr.label }}@if (attr.required) {<span class="required" aria-hidden="true"> *</span>}</label>
              <select [id]="'attr-' + attr.key" [formControlName]="attr.key" [attr.aria-invalid]="!!error">
                <option [ngValue]="null">Choisir…</option>
                @for (option of attr.options; track option) {
                  <option [ngValue]="option">{{ humanize(option) }}{{ attr.unit ? ' ' + attr.unit : '' }}</option>
                }
              </select>
            }
            @default {
              <label [for]="'attr-' + attr.key">
                {{ attr.label }}@if (attr.unit) { ({{ attr.unit }})}@if (attr.required) {<span class="required" aria-hidden="true"> *</span>}
              </label>
              <input
                [id]="'attr-' + attr.key"
                [formControlName]="attr.key"
                [type]="attr.type === 'number' ? 'number' : 'text'"
                [attr.inputmode]="attr.type === 'number' ? 'decimal' : null"
                [attr.min]="attr.min"
                [attr.max]="attr.max"
                [attr.aria-invalid]="!!error"
              />
            }
          }
          @if (error) {
            <p class="field__error" role="alert">{{ error }}</p>
          }
        </div>
      } @empty {
        <p class="muted">Aucune caractéristique spécifique pour cette catégorie.</p>
      }
    </div>
  `,
  styles: `
    @media (min-width: 640px) {
      .attributes {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1rem;
      }
      .attributes > .field:has(.choices),
      .attributes > p {
        grid-column: 1 / -1;
      }
    }
  `,
})
export class AttributeFields {
  readonly schema = input.required<AttributeDefinition[]>();
  readonly group = input.required<AttributeGroup>();
  /** Erreurs renvoyées par l'API (clés « attribute_values.<clé> »). */
  readonly serverErrors = input<ValidationErrors>({});

  errorFor(attr: AttributeDefinition): string | null {
    const control = this.group().controls[attr.key];
    if (control && control.invalid && (control.touched || control.dirty)) {
      return attributeErrorMessage(attr, control.errors);
    }
    return fieldError(this.serverErrors(), `attribute_values.${attr.key}`);
  }

  isChecked(key: string, option: string): boolean {
    const value = this.group().controls[key]?.value;
    return Array.isArray(value) && value.includes(option);
  }

  toggle(key: string, option: string, checked: boolean): void {
    const control = this.group().controls[key];
    const current = Array.isArray(control.value) ? control.value : [];
    control.setValue(checked ? [...current, option] : current.filter((v) => v !== option));
    control.markAsTouched();
  }

  /** « tres_bon_etat » → « Tres bon etat » ; les libellés d'options viendront du back-office. */
  humanize(value: string): string {
    const text = value.replace(/_/g, ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
  }
}
