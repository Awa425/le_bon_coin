export type ListingType = 'sale' | 'rental' | 'wanted' | 'service' | 'job';

export type ListingStatus =
  | 'draft'
  | 'pending_moderation'
  | 'active'
  | 'reserved'
  | 'sold'
  | 'refused'
  | 'expired';

export type AttributeValue = string | number | boolean | string[];

export interface GeoPoint {
  lat: number;
  lng: number;
}

export type PhotoStatus = 'pending' | 'processing' | 'ready' | 'failed';

export type PhotoSize = 'thumb' | 'card' | 'detail' | 'full';

export interface PhotoVariant {
  width: number;
  height: number | null;
  webp: string;
  avif: string;
}

export interface Photo {
  id: string;
  status: PhotoStatus;
  position: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  failure_reason: string | null;
  variants: Partial<Record<PhotoSize, PhotoVariant>>;
}

/** Formulaire d'envoi signé (politique S3 POST) : clé, type et taille exacts imposés par le stockage. */
export interface PhotoUploadTicket {
  data: Photo;
  upload: { method: 'POST'; url: string; fields: Record<string, string>; file_field: string; expires_in: number };
}

export interface Listing {
  id: string;
  slug: string;
  type: ListingType;
  status: ListingStatus;
  category_id: string;
  title: string;
  description: string | null;
  price: string | null;
  currency: string;
  attribute_values: Record<string, AttributeValue>;
  location?: GeoPoint | null;
  hide_phone: boolean;
  photos?: Photo[];
  published_at: string | null;
  expires_at: string | null;
}

/** Champs modifiables d'un brouillon (sauvegarde partielle à chaque étape du tunnel). */
export interface ListingDraftPayload {
  category_id?: string;
  type?: ListingType;
  title?: string;
  description?: string | null;
  price?: number | null;
  location?: GeoPoint | null;
  hide_phone?: boolean;
  attribute_values?: Record<string, AttributeValue>;
}

export const LISTING_TYPES: { value: ListingType; label: string; priced: boolean }[] = [
  { value: 'sale', label: 'Je vends', priced: true },
  { value: 'rental', label: 'Je loue', priced: true },
  { value: 'wanted', label: 'Je recherche', priced: false },
  { value: 'service', label: 'Je propose un service', priced: false },
  { value: 'job', label: "Offre d'emploi", priced: false },
];
