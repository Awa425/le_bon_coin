import { CropRect } from './image-preparer';

export interface CropState {
  /** Rapport largeur / hauteur du cadre ; null = format d'origine. */
  aspect: number | null;
  /** 1 = cadre le plus grand possible ; jusqu'à 3. */
  zoom: number;
  /** Centre du cadre, en pixels de l'image. */
  centerX: number;
  centerY: number;
}

export const ASPECTS: { label: string; value: number | null }[] = [
  { label: 'Original', value: null },
  { label: '4:3', value: 4 / 3 },
  { label: 'Carré', value: 1 },
  { label: '3:4', value: 3 / 4 },
];

export const MIN_ZOOM = 1;
export const MAX_ZOOM = 3;

export function initialCrop(width: number, height: number, aspect: number | null = null): CropState {
  return { aspect, zoom: 1, centerX: width / 2, centerY: height / 2 };
}

/** Zone recadrée (toujours entièrement dans l'image), en pixels entiers. */
export function cropRect(state: CropState, width: number, height: number): CropRect {
  const aspect = state.aspect ?? width / height;
  let baseWidth = width;
  let baseHeight = width / aspect;

  if (baseHeight > height) {
    baseHeight = height;
    baseWidth = height * aspect;
  }

  const zoom = clamp(state.zoom, MIN_ZOOM, MAX_ZOOM);
  const cropWidth = baseWidth / zoom;
  const cropHeight = baseHeight / zoom;
  const centerX = clamp(state.centerX, cropWidth / 2, width - cropWidth / 2);
  const centerY = clamp(state.centerY, cropHeight / 2, height - cropHeight / 2);

  return {
    x: Math.round(centerX - cropWidth / 2),
    y: Math.round(centerY - cropHeight / 2),
    width: Math.round(cropWidth),
    height: Math.round(cropHeight),
  };
}

/** Recentre l'état sur la zone effectivement affichée (après bornage). */
export function normalize(state: CropState, width: number, height: number): CropState {
  const rect = cropRect(state, width, height);
  return { ...state, zoom: clamp(state.zoom, MIN_ZOOM, MAX_ZOOM), centerX: rect.x + rect.width / 2, centerY: rect.y + rect.height / 2 };
}

/** Le recadrage couvre-t-il toute l'image (aucune transformation utile) ? */
export function isFullImage(rect: CropRect, width: number, height: number): boolean {
  return rect.x === 0 && rect.y === 0 && rect.width === width && rect.height === height;
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), Math.max(min, max));
}
