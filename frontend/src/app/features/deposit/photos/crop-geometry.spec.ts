import { cropRect, initialCrop, isFullImage, normalize } from './crop-geometry';

describe('géométrie du recadrage', () => {
  it('couvre toute l’image au format d’origine sans zoom', () => {
    const rect = cropRect(initialCrop(1600, 1200), 1600, 1200);

    expect(rect).toEqual({ x: 0, y: 0, width: 1600, height: 1200 });
    expect(isFullImage(rect, 1600, 1200)).toBe(true);
  });

  it('prend le plus grand carré centré', () => {
    expect(cropRect(initialCrop(1600, 1200, 1), 1600, 1200)).toEqual({ x: 200, y: 0, width: 1200, height: 1200 });
  });

  it('gère un format portrait dans une image paysage', () => {
    expect(cropRect(initialCrop(1600, 1200, 3 / 4), 1600, 1200)).toEqual({ x: 350, y: 0, width: 900, height: 1200 });
  });

  it('réduit la zone avec le zoom', () => {
    const rect = cropRect({ ...initialCrop(1600, 1200), zoom: 2 }, 1600, 1200);

    expect(rect).toEqual({ x: 400, y: 300, width: 800, height: 600 });
  });

  it('garde toujours la zone dans l’image, même si on la déplace trop loin', () => {
    const state = { ...initialCrop(1600, 1200), zoom: 2, centerX: -5000, centerY: 99999 };

    expect(cropRect(state, 1600, 1200)).toEqual({ x: 0, y: 600, width: 800, height: 600 });
    expect(normalize(state, 1600, 1200)).toMatchObject({ centerX: 400, centerY: 900 });
  });

  it('borne le zoom entre 1 et 3', () => {
    expect(cropRect({ ...initialCrop(900, 900), zoom: 10 }, 900, 900).width).toBe(300);
    expect(cropRect({ ...initialCrop(900, 900), zoom: 0.2 }, 900, 900).width).toBe(900);
  });
});
