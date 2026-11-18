import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, filter, map, repeat, switchMap, take, timer } from 'rxjs';
import { API_BASE_URL } from '../core/api/api-config';
import { Photo, PhotoUploadTicket } from './listing.models';

/** Contraintes partagées avec l'API (config/photos.php). */
export const PHOTO_LIMITS = {
  maxPerListing: 10,
  maxBytes: 15 * 1024 * 1024,
  acceptedTypes: ['image/jpeg', 'image/png', 'image/webp'],
} as const;

/**
 * DEP-04 : envoi direct navigateur → stockage objet.
 * 1. l'API réserve un emplacement et signe un formulaire d'envoi (clé, type et taille imposés) ;
 * 2. le fichier part directement vers le stockage (sans transiter par l'API) ;
 * 3. l'API confirme et lance le traitement ; on attend que la photo soit prête.
 */
@Injectable({ providedIn: 'root' })
export class PhotoService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);

  /** Intervalle de suivi du traitement (surchargé dans les tests). */
  pollIntervalMs = 1000;
  /** Nombre maximal de vérifications avant d'abandonner le suivi. */
  maxPolls = 90;

  private base(listingId: string): string {
    return `${this.api}/listings/${listingId}/photos`;
  }

  requestUpload(listingId: string, file: Blob): Observable<PhotoUploadTicket> {
    return this.http.post<PhotoUploadTicket>(this.base(listingId), { content_type: file.type, size: file.size });
  }

  /**
   * Envoi multipart direct vers le stockage : champs signés d'abord, fichier en dernier (exigence S3).
   * L'intercepteur n'ajoute pas le jeton : l'URL est hors de l'API.
   */
  sendFile(ticket: PhotoUploadTicket, file: Blob): Observable<unknown> {
    const form = new FormData();
    for (const [name, value] of Object.entries(ticket.upload.fields)) {
      form.append(name, value);
    }
    form.append(ticket.upload.file_field, file, 'photo');

    return this.http.post(ticket.upload.url, form, { responseType: 'text' });
  }

  complete(listingId: string, photoId: string): Observable<Photo> {
    return this.http
      .post<{ data: Photo }>(`${this.base(listingId)}/${photoId}/complete`, {})
      .pipe(map(({ data }) => data));
  }

  get(listingId: string, photoId: string): Observable<Photo> {
    return this.http.get<{ data: Photo }>(`${this.base(listingId)}/${photoId}`).pipe(map(({ data }) => data));
  }

  /** Suit le traitement asynchrone jusqu'à « prête » ou « échec ». */
  waitUntilProcessed(listingId: string, photo: Photo): Observable<Photo> {
    if (photo.status === 'ready' || photo.status === 'failed') {
      return timer(0).pipe(map(() => photo));
    }

    // Interrogation séquentielle : la vérification suivante ne part qu'après la réponse précédente
    // (pas d'empilement de requêtes sur un réseau lent).
    return timer(this.pollIntervalMs).pipe(
      switchMap(() =>
        this.get(listingId, photo.id).pipe(repeat({ count: this.maxPolls, delay: this.pollIntervalMs })),
      ),
      filter((current) => current.status === 'ready' || current.status === 'failed'),
      take(1),
    );
  }

  /** Envoi complet d'un fichier déjà préparé. */
  upload(listingId: string, file: Blob, onReserved?: (photo: Photo) => void): Observable<Photo> {
    return this.requestUpload(listingId, file).pipe(
      switchMap((ticket) => {
        onReserved?.(ticket.data);
        return this.sendFile(ticket, file).pipe(
          switchMap(() => this.complete(listingId, ticket.data.id)),
          switchMap((photo) => this.waitUntilProcessed(listingId, photo)),
        );
      }),
    );
  }

  reorder(listingId: string, photoIds: string[]): Observable<Photo[]> {
    return this.http
      .put<{ data: Photo[] }>(`${this.base(listingId)}/order`, { photo_ids: photoIds })
      .pipe(map(({ data }) => data));
  }

  remove(listingId: string, photoId: string): Observable<void> {
    return this.http.delete<void>(`${this.base(listingId)}/${photoId}`);
  }
}
