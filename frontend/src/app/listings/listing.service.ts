import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_BASE_URL } from '../core/api/api-config';
import { Listing, ListingDraftPayload } from './listing.models';

@Injectable({ providedIn: 'root' })
export class ListingService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);

  createDraft(payload: ListingDraftPayload): Observable<Listing> {
    return this.http.post<{ data: Listing }>(`${this.api}/listings`, payload).pipe(map(({ data }) => data));
  }

  updateDraft(id: string, payload: ListingDraftPayload): Observable<Listing> {
    return this.http.patch<{ data: Listing }>(`${this.api}/listings/${id}`, payload).pipe(map(({ data }) => data));
  }

  submit(id: string): Observable<Listing> {
    return this.http.post<{ data: Listing }>(`${this.api}/listings/${id}/submit`, {}).pipe(map(({ data }) => data));
  }
}
