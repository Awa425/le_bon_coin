import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { API_BASE_URL } from '../../../core/api/api-config';
import { Photo } from '../../../listings/listing.models';
import { PhotoService } from '../../../listings/photo.service';
import { queryAll, text } from '../../../../testing/dom';
import { ImagePreparer, UnsupportedImageError } from './image-preparer';
import { PhotoManager, PhotoSummary } from './photo-manager';

const API = 'http://api.test/v1';

const ready = (id: string, position: number): Photo => ({
  id,
  status: 'ready',
  position,
  width: 1600,
  height: 1200,
  alt_text: null,
  failure_reason: null,
  variants: {
    card: { width: 400, height: 300, webp: `http://cdn.test/${id}/card.webp`, avif: `http://cdn.test/${id}/card.avif` },
  },
});

const flush = () => new Promise((r) => setTimeout(r, 0));

describe('PhotoManager (DEP-04)', () => {
  let fixture: ComponentFixture<PhotoManager>;
  let http: HttpTestingController;
  let preparer: { prepare: ReturnType<typeof vi.fn>; dimensions: ReturnType<typeof vi.fn> };
  let summaries: PhotoSummary[];

  async function create(initialPhotos: Photo[] = []): Promise<void> {
    fixture = TestBed.createComponent(PhotoManager);
    fixture.componentRef.setInput('listingId', 'l1');
    fixture.componentRef.setInput('initialPhotos', initialPhotos);
    summaries = [];
    fixture.componentInstance.summaryChange.subscribe((s) => summaries.push(s));
    await fixture.whenStable();
  }

  const file = (name = 'photo.jpg', type = 'image/jpeg') => new File(['x'], name, { type });

  beforeEach(() => {
    let urls = 0;
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: () => `blob:local/${urls++}`, revokeObjectURL: () => undefined }));
    preparer = {
      prepare: vi.fn().mockResolvedValue({ blob: new Blob(['jpeg-prepare'], { type: 'image/jpeg' }), width: 1600, height: 1200 }),
      dimensions: vi.fn().mockResolvedValue({ width: 1600, height: 1200 }),
    };
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: API },
        { provide: ImagePreparer, useValue: preparer },
      ],
    });
    TestBed.inject(PhotoService).pollIntervalMs = 1;
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    vi.unstubAllGlobals();
  });

  /** Simule le parcours serveur complet d'un envoi. */
  async function serveUpload(id: string, position: number): Promise<void> {
    await flush();
    http.expectOne({ method: 'POST', url: `${API}/listings/l1/photos` }).flush({
      data: { ...ready(id, position), status: 'pending', variants: {} },
      upload: { method: 'POST', url: `http://storage.test/${id}`, fields: { key: `originals/${id}.jpg` }, file_field: 'file', expires_in: 900 },
    });
    http.expectOne(`http://storage.test/${id}`).flush('');
    http.expectOne(`${API}/listings/l1/photos/${id}/complete`).flush({ data: ready(id, position) });
    await flush();
    await fixture.whenStable();
  }

  it('affiche les photos existantes dans l’ordre, la première étant la principale', async () => {
    await create([ready('b', 1), ready('a', 0)]);

    const images = queryAll<HTMLImageElement>(fixture, '.tile img');
    expect(images.map((i) => i.getAttribute('src'))).toEqual(['http://cdn.test/a/card.webp', 'http://cdn.test/b/card.webp']);
    expect(queryAll(fixture, '.tile')[0].textContent).toContain('Principale');
    expect(text(fixture)).toContain('2/10 photos');
    expect(summaries.at(-1)).toEqual({ ready: 2, busy: false });
  });

  it('prépare puis envoie une photo ajoutée, jusqu’à son traitement', async () => {
    await create();

    fixture.componentInstance.addFiles([file()]);
    await fixture.whenStable();
    expect(summaries.at(-1)).toEqual({ ready: 0, busy: true });
    expect(text(fixture)).toMatch(/Préparation…|Envoi et optimisation…/);

    await serveUpload('p1', 0);

    expect(preparer.prepare).toHaveBeenCalledOnce();
    expect(summaries.at(-1)).toEqual({ ready: 1, busy: false });
    expect(queryAll<HTMLImageElement>(fixture, '.tile img')[0].getAttribute('src')).toBe('http://cdn.test/p1/card.webp');
  });

  it('refuse les fichiers qui ne sont pas des images et respecte le maximum', async () => {
    await create(Array.from({ length: 9 }, (_, i) => ready(`e${i}`, i)));

    fixture.componentInstance.addFiles([file('notes.pdf', 'application/pdf'), file('a.jpg'), file('b.jpg')]);
    await fixture.whenStable();

    expect(text(fixture)).toContain('Maximum 10 photos : 1 photo(s) ignorée(s).');
    expect(fixture.componentInstance.used()).toBe(10);
    expect(queryAll(fixture, '.dropzone')).toHaveLength(0);
    await serveUpload('a', 9);
  });

  it('affiche l’erreur si le navigateur ne sait pas lire le format', async () => {
    preparer.prepare.mockRejectedValueOnce(new UnsupportedImageError('Ce format de photo n’est pas pris en charge par votre navigateur.'));
    await create();

    fixture.componentInstance.addFiles([file('photo.heic', '')]);
    await flush();
    await fixture.whenStable();

    expect(text(fixture)).toContain('Ce format de photo n’est pas pris en charge par votre navigateur.');
    expect(summaries.at(-1)).toEqual({ ready: 0, busy: false });
  });

  it('affiche la raison d’un échec de traitement côté serveur', async () => {
    await create([{ ...ready('x', 0), status: 'failed', failure_reason: "Ce fichier n'est pas une image lisible.", variants: {} }]);

    expect(text(fixture)).toContain("Ce fichier n'est pas une image lisible.");
    expect(fixture.componentInstance.used()).toBe(0);
  });

  it('réordonne avec les boutons et enregistre l’ordre', async () => {
    await create([ready('a', 0), ready('b', 1), ready('c', 2)]);

    queryAll<HTMLButtonElement>(fixture, 'button[aria-label="Déplacer la photo 3 vers la gauche"]')[0].click();
    const first = http.expectOne({ method: 'PUT', url: `${API}/listings/l1/photos/order` });
    expect(first.request.body).toEqual({ photo_ids: ['a', 'c', 'b'] });
    first.flush({ data: [] });
    await fixture.whenStable();

    queryAll<HTMLButtonElement>(fixture, 'button[aria-label="Définir la photo 3 comme principale"]')[0].click();
    const order = http.expectOne({ method: 'PUT', url: `${API}/listings/l1/photos/order` });

    expect(order.request.body).toEqual({ photo_ids: ['b', 'a', 'c'] });
    order.flush({ data: [] });
  });

  it('supprime une photo', async () => {
    await create([ready('a', 0), ready('b', 1)]);

    queryAll<HTMLButtonElement>(fixture, 'button[aria-label="Supprimer la photo 1"]')[0].click();
    http.expectOne({ method: 'DELETE', url: `${API}/listings/l1/photos/a` }).flush(null);
    await fixture.whenStable();

    expect(fixture.componentInstance.items().map((i) => i.photo?.id)).toEqual(['b']);
    expect(summaries.at(-1)).toEqual({ ready: 1, busy: false });
  });

  it('recadre : la version recadrée remplace l’ancienne', async () => {
    await create();
    fixture.componentInstance.addFiles([file()]);
    await serveUpload('old', 0);

    await fixture.componentInstance.openCrop(fixture.componentInstance.items()[0]);
    await fixture.whenStable();
    expect(queryAll(fixture, 'app-crop-editor')).toHaveLength(1);

    fixture.componentInstance.applyCrop({ x: 200, y: 0, width: 1200, height: 1200 });
    expect(preparer.prepare).toHaveBeenLastCalledWith(expect.any(File), { x: 200, y: 0, width: 1200, height: 1200 });
    await serveUpload('new', 1);

    http.expectOne({ method: 'DELETE', url: `${API}/listings/l1/photos/old` }).flush(null);
    expect(fixture.componentInstance.items().map((i) => i.photo?.id)).toEqual(['new']);
  });
});
