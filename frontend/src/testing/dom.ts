import { ComponentFixture } from '@angular/core/testing';

/** Petits utilitaires DOM pour les tests de composants (zoneless : on attend la stabilité après chaque action). */
export function query<T extends Element = HTMLElement>(fixture: ComponentFixture<unknown>, selector: string): T {
  const element = (fixture.nativeElement as HTMLElement).querySelector<T>(selector);
  if (!element) {
    throw new Error(`Élément introuvable : ${selector}`);
  }
  return element;
}

export function queryAll<T extends Element = HTMLElement>(fixture: ComponentFixture<unknown>, selector: string): T[] {
  return Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<T>(selector));
}

export function text(fixture: ComponentFixture<unknown>): string {
  return (fixture.nativeElement as HTMLElement).textContent ?? '';
}

export async function typeInto(fixture: ComponentFixture<unknown>, selector: string, value: string): Promise<void> {
  const input = query<HTMLInputElement | HTMLTextAreaElement>(fixture, selector);
  input.value = value;
  input.dispatchEvent(new Event('input'));
  input.dispatchEvent(new Event('blur'));
  await fixture.whenStable();
}

export async function selectOption(fixture: ComponentFixture<unknown>, selector: string, index: number): Promise<void> {
  const select = query<HTMLSelectElement>(fixture, selector);
  select.selectedIndex = index;
  select.dispatchEvent(new Event('change'));
  await fixture.whenStable();
}
