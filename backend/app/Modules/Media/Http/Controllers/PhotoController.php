<?php

namespace App\Modules\Media\Http\Controllers;

use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Photo;
use App\Modules\Media\Http\Resources\PhotoResource;
use App\Modules\Media\Jobs\ProcessPhoto;
use App\Modules\Media\Uploads\PhotoUploadSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * DEP-04 : envoi direct navigateur → stockage objet via formulaire POST signé (politique S3).
 *
 * 1. POST   /listings/{id}/photos                  → réserve un emplacement et renvoie le formulaire signé
 * 2. POST   <stockage>                             → le navigateur envoie le fichier, sans passer par l'API
 * 3. POST   /listings/{id}/photos/{photo}/complete → confirme l'envoi, lance le traitement asynchrone
 * 4. PUT    /listings/{id}/photos/order            → réordonne ; la première est la photo principale
 * 5. DELETE /listings/{id}/photos/{photo}
 */
class PhotoController extends Controller
{
    public function store(Request $request, Listing $listing, PhotoUploadSigner $signer): JsonResponse
    {
        Gate::authorize('update', $listing);

        $mimeTypes = config('photos.mime_types');
        $data = $request->validate([
            'content_type' => ['required', 'string', Rule::in(array_keys($mimeTypes))],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('photos.max_bytes')],
        ], [
            'content_type.in' => 'Format non accepté : utilisez une photo JPEG, PNG ou WebP.',
            'size.max' => 'La photo dépasse 15 Mo.',
        ], ['content_type' => 'format', 'size' => 'taille']);

        $photo = DB::transaction(function () use ($listing, $data, $mimeTypes): Photo {
            // Verrou sur l'annonce : deux envois simultanés ne peuvent pas dépasser le quota.
            Listing::query()->whereKey($listing->id)->lockForUpdate()->first();
            $existing = $listing->photos()->where('status', '!=', PhotoStatus::Failed)->count();

            if ($existing >= config('photos.max_per_listing')) {
                throw ValidationException::withMessages([
                    'photos' => 'Vous avez atteint le maximum de '.config('photos.max_per_listing').' photos pour cette annonce.',
                ]);
            }

            $photo = new Photo;
            $photo->id = $photo->newUniqueId();
            $photo->fill([
                'listing_id' => $listing->id,
                'status' => PhotoStatus::Pending,
                'mime_type' => $data['content_type'],
                'size_bytes' => $data['size'],
                'position' => $listing->photos()->exists() ? (int) $listing->photos()->max('position') + 1 : 0,
                'original_path' => "originals/{$listing->id}/{$photo->id}.{$mimeTypes[$data['content_type']]}",
            ])->save();

            return $photo;
        });

        // Politique signée : le stockage refuse tout fichier dont la clé, le type ou la taille diffèrent.
        $form = $signer->sign(
            $photo->original_path,
            $data['content_type'],
            $data['size'],
            now()->addMinutes(config('photos.upload_url_ttl_minutes')),
        );

        return (new PhotoResource($photo))
            ->additional(['upload' => [
                ...$form->toArray(),
                'expires_in' => config('photos.upload_url_ttl_minutes') * 60,
            ]])
            ->response()
            ->setStatusCode(201);
    }

    public function complete(Listing $listing, Photo $photo): PhotoResource
    {
        Gate::authorize('update', $listing);

        if ($photo->status !== PhotoStatus::Pending) {
            throw ValidationException::withMessages(['photo' => 'Cette photo a déjà été confirmée.']);
        }

        $uploads = Storage::disk(config('photos.uploads_disk'));

        if (! $uploads->exists($photo->original_path)) {
            throw ValidationException::withMessages(['photo' => "Le fichier n'a pas été reçu. Réessayez l'envoi."]);
        }

        $size = $uploads->size($photo->original_path);

        if ($size > config('photos.max_bytes')) {
            $uploads->delete($photo->original_path);
            $photo->update(['status' => PhotoStatus::Failed, 'failure_reason' => 'La photo dépasse 15 Mo.', 'original_path' => null]);
            throw ValidationException::withMessages(['photo' => 'La photo dépasse 15 Mo.']);
        }

        $photo->update(['status' => PhotoStatus::Processing, 'size_bytes' => $size]);
        ProcessPhoto::dispatch($photo);

        return new PhotoResource($photo->fresh());
    }

    public function show(Listing $listing, Photo $photo): PhotoResource
    {
        Gate::authorize('update', $listing);

        return new PhotoResource($photo);
    }

    public function reorder(Request $request, Listing $listing): AnonymousResourceCollection
    {
        Gate::authorize('update', $listing);

        $ids = $listing->photos()->pluck('id')->all();
        $data = $request->validate([
            'photo_ids' => ['required', 'array', 'size:'.count($ids)],
            'photo_ids.*' => ['required', 'uuid', 'distinct', Rule::in($ids)],
        ], [
            'photo_ids.size' => 'La liste doit contenir toutes les photos de l\'annonce.',
        ], ['photo_ids' => 'photos']);

        DB::transaction(function () use ($data): void {
            foreach ($data['photo_ids'] as $position => $id) {
                Photo::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        return PhotoResource::collection($listing->photos()->get());
    }

    public function destroy(Listing $listing, Photo $photo): Response
    {
        Gate::authorize('update', $listing);

        Storage::disk(config('photos.public_disk'))->delete($photo->variantPaths());

        if ($photo->original_path !== null) {
            Storage::disk(config('photos.uploads_disk'))->delete($photo->original_path);
        }

        DB::transaction(function () use ($listing, $photo): void {
            $photo->delete();

            foreach ($listing->photos()->get() as $position => $remaining) {
                $remaining->update(['position' => $position]);
            }
        });

        return response()->noContent();
    }
}
