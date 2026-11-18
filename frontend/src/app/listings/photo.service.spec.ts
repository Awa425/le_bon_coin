import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { API_BASE_URL } from '../core/api/api-config';
import { authInterceptor } from '../core/auth/auth.interceptor';
import { TokenStorage } from '../core/auth/token-storage';
import { Photo, PhotoUploadTicket } from './listing.models';
import { PhotoService } from './photo.service';

const API = 'http://api.test/v1';
const UPLOAD_URL = 'http://storage.test/marketplace-uploads';

export const photo = (overrides: Partial<Photo> = {}): Photo => ({
  id: 'p1',
  status: 'pending',
  position: 0,
  width: null,
  height: null,
  alt_text: null,
  failure_reason: null,
  variants: {},
  ...overrides,
});

const ticket: PhotoUploadTicket = {
  data: photo(),
  upload: {
    method: 'POST',
    url: UPLOAD_URL,
    fields: { key: 'originals/l1/p1.jpg', 'Content-Type': 'image/jpeg', Policy: 'politique', 'X-Amz-Signature': 'sig' },
    file_field: 'file',
    expires_in: 900,
  },
};

describe('PhotoService', () => {
  let service: PhotoService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: API },
        { provide: TokenStorage, useValue: { read: () => 'jeton-secret', write: () => undefined, clear: () => undefined } },
      ],
    });
    service = TestBed.inject(PhotoService);
    service.pollIntervalMs = 1;
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('réserve, envoie directement au stockage, confirme puis attend la fin du traitement', async () => {
    const file = new Blob(['jpeg'], { type: 'image/jpeg' });
    const reserved: Photo[] = [];
    let result: Photo | undefined;

    service.upload('l1', file, (p) => reserved.push(p)).subscribe((p) => (result = p));

    const reserve = http.expectOne({ method: 'POST', url: `${API}/listings/l1/photos` });
    expect(reserve.request.body).toEqual({ content_type: 'image/jpeg', size: 4 });
    expect(reserve.request.headers.get('Authorization')).toBe('Bearer jeton-secret');
    reserve.flush(ticket);
    expect(reserved[0].id).toBe('p1');

    const post = http.expectOne({ method: 'POST', url: UPLOAD_URL });
    const form = post.request.body as FormData;
    expect([...form.keys()]).toEqual(['key', 'Content-Type', 'Policy', 'X-Amz-Signature', 'file']); // fichier en dernier
    expect(form.get('key')).toBe('originals/l1/p1.jpg');
    expect(form.get('file')).toBeInstanceOf(Blob);
    expect(post.request.headers.has('Authorization')).toBe(false); // le jeton ne part jamais vers le stockage
    post.flush('');

    http.expectOne({ method: 'POST', url: `${API}/listings/l1/photos/p1/complete` }).flush({ data: photo({ status: 'processing' }) });

    await new Promise((r) => setTimeout(r, 5));
    http.expectOne(`${API}/listings/l1/photos/p1`).flush({ data: photo({ status: 'processing' }) });
    await new Promise((r) => setTimeout(r, 5));
    http.expectOne(`${API}/listings/l1/photos/p1`).flush({ data: photo({ status: 'ready' }) });

    expect(result?.status).toBe('ready');
  });

  it('n’interroge pas le serveur si la photo est déjà traitée', async () => {
    let result: Photo | undefined;
    service.waitUntilProcessed('l1', photo({ status: 'failed', failure_reason: 'Format refusé' })).subscribe((p) => (result = p));
    await new Promise((r) => setTimeout(r, 5));

    expect(result?.failure_reason).toBe('Format refusé');
  });

  it('réordonne et supprime', () => {
    service.reorder('l1', ['b', 'a']).subscribe();
    expect(http.expectOne({ method: 'PUT', url: `${API}/listings/l1/photos/order` }).request.body).toEqual({ photo_ids: ['b', 'a'] });

    service.remove('l1', 'a').subscribe();
    http.expectOne({ method: 'DELETE', url: `${API}/listings/l1/photos/a` }).flush(null);
  });
});
