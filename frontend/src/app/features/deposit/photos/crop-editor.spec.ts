import { ComponentFixture, TestBed } from '@angular/core/testing';
import { query, queryAll } from '../../../../testing/dom';
import { CropEditor } from './crop-editor';
import { CropRect } from './image-preparer';

describe('CropEditor', () => {
  let fixture: ComponentFixture<CropEditor>;
  let applied: CropRect[];
  let cancelled: number;

  beforeEach(async () => {
    fixture = TestBed.createComponent(CropEditor);
    fixture.componentRef.setInput('src', 'blob:local/1');
    fixture.componentRef.setInput('naturalWidth', 1600);
    fixture.componentRef.setInput('naturalHeight', 1200);
    applied = [];
    cancelled = 0;
    fixture.componentInstance.applied.subscribe((r) => applied.push(r));
    fixture.componentInstance.cancelled.subscribe(() => cancelled++);
    await fixture.whenStable();
  });

  const button = (label: string) =>
    queryAll<HTMLButtonElement>(fixture, 'button').find((b) => b.textContent?.includes(label))!;

  it('est une fenêtre modale accessible, focus sur « Appliquer »', () => {
    const dialog = query(fixture, '[role="dialog"]');
    expect(dialog.getAttribute('aria-modal')).toBe('true');
    expect(dialog.getAttribute('aria-labelledby')).toBe('crop-title');
    expect(document.activeElement).toBe(button('Appliquer'));
  });

  it('applique le format carré, centré', async () => {
    queryAll<HTMLInputElement>(fixture, 'input[name="crop-aspect"]')[2].click(); // Carré
    await fixture.whenStable();

    expect(query(fixture, '.frame').style.aspectRatio).toBe('1200 / 1200');
    button('Appliquer').click();
    expect(applied).toEqual([{ x: 200, y: 0, width: 1200, height: 1200 }]);
  });

  it('se déplace au clavier sans sortir de l’image', async () => {
    fixture.componentInstance.setAspect(1);
    for (let i = 0; i < 10; i++) {
      query(fixture, '.frame').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
    }
    await fixture.whenStable();

    expect(fixture.componentInstance.rect()).toEqual({ x: 0, y: 0, width: 1200, height: 1200 });
  });

  it('zoome via le curseur', async () => {
    const range = query<HTMLInputElement>(fixture, 'input[type="range"]');
    range.value = '2';
    range.dispatchEvent(new Event('input'));
    await fixture.whenStable();

    expect(fixture.componentInstance.rect()).toEqual({ x: 400, y: 300, width: 800, height: 600 });
  });

  it('se ferme avec Échap ou Annuler', () => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    button('Annuler').click();

    expect(cancelled).toBe(2);
    expect(applied).toEqual([]);
  });
});
