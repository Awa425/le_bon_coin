import { Injectable } from '@angular/core';

/** Zone de recadrage, en pixels de l'image redressée. */
export interface CropRect {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface PreparedImage {
  blob: Blob;
  width: number;
  height: number;
}

/** Plus grand côté envoyé : le serveur produit au plus 2 000 px, une marge suffit. */
export const MAX_UPLOAD_SIDE = 2400;

export class UnsupportedImageError extends Error {}

/**
 * Préparation côté navigateur (DEP-04) :
 * - redresse selon l'orientation EXIF (`imageOrientation: 'from-image'`) ;
 * - applique le recadrage éventuel ;
 * - réduit à 2 400 px et ré-encode en JPEG : les métadonnées (dont le GPS) ne quittent pas l'appareil.
 */
@Injectable({ providedIn: 'root' })
export class ImagePreparer {
  async dimensions(source: Blob): Promise<{ width: number; height: number }> {
    const bitmap = await this.decode(source);
    const size = { width: bitmap.width, height: bitmap.height };
    bitmap.close();
    return size;
  }

  async prepare(source: Blob, crop?: CropRect): Promise<PreparedImage> {
    const bitmap = await this.decode(source);
    const area = crop ?? { x: 0, y: 0, width: bitmap.width, height: bitmap.height };
    const scale = Math.min(1, MAX_UPLOAD_SIDE / Math.max(area.width, area.height));
    const width = Math.max(1, Math.round(area.width * scale));
    const height = Math.max(1, Math.round(area.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) {
      bitmap.close();
      throw new UnsupportedImageError('Votre navigateur ne permet pas de préparer cette image.');
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(bitmap, area.x, area.y, area.width, area.height, 0, 0, width, height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    if (!blob) {
      throw new UnsupportedImageError("Impossible d'encoder l'image.");
    }

    return { blob, width, height };
  }

  private async decode(source: Blob): Promise<ImageBitmap> {
    try {
      return await createImageBitmap(source, { imageOrientation: 'from-image' });
    } catch {
      throw new UnsupportedImageError('Ce format de photo n’est pas pris en charge par votre navigateur.');
    }
  }
}
