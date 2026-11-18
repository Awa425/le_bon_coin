<?php

namespace Tests\Feature\Media;

use App\Enums\ListingStatus;
use App\Enums\PhotoStatus;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Photo;
use App\Models\User;
use App\Modules\Media\Jobs\ProcessPhoto;
use App\Modules\Media\Uploads\PhotoUploadSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeUploadSigner;
use Tests\Support\Images;
use Tests\TestCase;

/** DEP-04 : jusqu'à 10 photos, envoi direct par URL pré-signée, réordonnancement, photo principale. */
class PhotoApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeUploadSigner $signer;

    private User $seller;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new FakeUploadSigner;
        $this->app->instance(PhotoUploadSigner::class, $this->signer);

        $this->seller = User::factory()->create();
        $this->listing = Listing::factory()->for($this->seller)->create();
        Sanctum::actingAs($this->seller);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/listings/{$this->listing->id}/photos{$suffix}";
    }

    /** Parcours complet : demande d'URL, envoi (simulé) du fichier, confirmation. */
    private function uploadPhoto(string $binary): Photo
    {
        $id = $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => strlen($binary)])
            ->assertCreated()
            ->json('data.id');
        $photo = Photo::query()->findOrFail($id);
        Storage::disk('uploads')->put($photo->original_path, $binary);
        $this->postJson($this->url("/{$id}/complete"))->assertOk();

        return $photo->fresh();
    }

    public function test_requesting_an_upload_returns_a_signed_post_form_for_this_exact_file(): void
    {
        $response = $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 2_000_000]);

        $photo = Photo::query()->sole();
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('upload.method', 'POST')
            ->assertJsonPath('upload.url', 'https://uploads.test/marketplace-uploads')
            ->assertJsonPath('upload.file_field', 'file')
            ->assertJsonPath('upload.fields.key', $photo->original_path)
            ->assertJsonPath('upload.expires_in', 900);

        $this->assertSame("originals/{$this->listing->id}/{$photo->id}.jpg", $photo->original_path);
        $this->assertSame([['path' => $photo->original_path, 'contentType' => 'image/jpeg', 'size' => 2_000_000]], $this->signer->signed);
    }

    public function test_upload_requests_are_validated(): void
    {
        $this->postJson($this->url(), ['content_type' => 'image/gif', 'size' => 16 * 1024 * 1024])
            ->assertUnprocessable()
            ->assertJsonPath('errors.content_type.0', 'Format non accepté : utilisez une photo JPEG, PNG ou WebP.')
            ->assertJsonPath('errors.size.0', 'La photo dépasse 15 Mo.');
    }

    public function test_a_listing_accepts_at_most_ten_photos(): void
    {
        Photo::factory()->for($this->listing)->count(9)->sequence(fn ($s) => ['position' => $s->index])->create();
        Photo::factory()->for($this->listing)->create(['status' => PhotoStatus::Failed, 'position' => 9]);

        $this->postJson($this->url(), ['content_type' => 'image/png', 'size' => 1000])->assertCreated();
        $this->postJson($this->url(), ['content_type' => 'image/png', 'size' => 1000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.photos.0', 'Vous avez atteint le maximum de 10 photos pour cette annonce.');
    }

    public function test_a_confirmed_upload_is_processed_into_public_variants_and_the_original_is_deleted(): void
    {
        $photo = $this->uploadPhoto(Images::jpegWithExif());

        $this->assertSame(PhotoStatus::Ready, $photo->status);
        $this->assertSame([1200, 1600], [$photo->width, $photo->height]);
        $this->assertNull($photo->original_path);
        $this->assertSame([], Storage::disk('uploads')->allFiles(), 'original (EXIF/GPS) supprimé');

        foreach (['thumb', 'card', 'detail', 'full'] as $size) {
            Storage::disk('photos')->assertExists("variants/{$this->listing->id}/{$photo->id}/{$size}.webp");
            Storage::disk('photos')->assertExists("variants/{$this->listing->id}/{$photo->id}/{$size}.avif");
        }

        $this->getJson("/api/v1/listings/{$this->listing->id}")
            ->assertJsonPath('data.photos.0.status', 'ready')
            ->assertJsonPath('data.photos.0.variants.card.width', 400)
            ->assertJsonPath('data.photos.0.variants.card.webp', Storage::disk('photos')->url("variants/{$this->listing->id}/{$photo->id}/card.webp"));
    }

    public function test_confirmation_is_asynchronous(): void
    {
        Queue::fake();
        $id = $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 100])->json('data.id');
        Storage::disk('uploads')->put(Photo::query()->findOrFail($id)->original_path, Images::jpeg());

        $this->postJson($this->url("/{$id}/complete"))->assertOk()->assertJsonPath('data.status', 'processing');

        Queue::assertPushed(ProcessPhoto::class, fn (ProcessPhoto $job) => $job->photo->id === $id);
    }

    public function test_confirmation_fails_if_the_file_was_not_received(): void
    {
        $id = $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 100])->json('data.id');

        $this->postJson($this->url("/{$id}/complete"))
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', "Le fichier n'a pas été reçu. Réessayez l'envoi.");
    }

    public function test_a_disguised_file_is_marked_failed_with_a_reason(): void
    {
        $id = $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 30])->json('data.id');
        Storage::disk('uploads')->put(Photo::query()->findOrFail($id)->original_path, '<?php system($_GET["c"]);');

        $this->postJson($this->url("/{$id}/complete"))->assertOk();

        $photo = Photo::query()->findOrFail($id);
        $this->assertSame(PhotoStatus::Failed, $photo->status);
        $this->assertSame("Ce fichier n'est pas une image lisible.", $photo->failure_reason);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
        $this->assertSame([], Storage::disk('photos')->allFiles());
    }

    public function test_photos_can_be_reordered_and_the_first_one_is_the_main_photo(): void
    {
        [$a, $b, $c] = Photo::factory()->for($this->listing)->count(3)->sequence(fn ($s) => ['position' => $s->index])->create();

        $this->putJson($this->url('/order'), ['photo_ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $c->id)
            ->assertJsonPath('data.0.position', 0);

        $this->assertSame([$c->id, $a->id, $b->id], $this->listing->photos()->pluck('id')->all());
    }

    public function test_reordering_requires_exactly_the_listing_photos(): void
    {
        [$a, $b] = Photo::factory()->for($this->listing)->count(2)->create();
        $foreign = Photo::factory()->create();

        $this->putJson($this->url('/order'), ['photo_ids' => [$a->id]])->assertUnprocessable();
        $this->putJson($this->url('/order'), ['photo_ids' => [$a->id, $foreign->id]])->assertUnprocessable();
        $this->putJson($this->url('/order'), ['photo_ids' => [$a->id, $a->id]])->assertUnprocessable();
    }

    public function test_deleting_a_photo_removes_its_files_and_compacts_positions(): void
    {
        $first = $this->uploadPhoto(Images::jpeg());
        $second = Photo::factory()->for($this->listing)->create(['position' => 1]);

        $this->deleteJson($this->url("/{$first->id}"))->assertNoContent();

        $this->assertSame([], Storage::disk('photos')->allFiles());
        $this->assertSame(0, $second->fresh()->position);
    }

    public function test_only_the_owner_can_manage_photos_and_only_while_editable(): void
    {
        $photo = Photo::factory()->for($this->listing)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 100])->assertForbidden();
        $this->deleteJson($this->url("/{$photo->id}"))->assertForbidden();

        Sanctum::actingAs($this->seller);
        $this->listing->update(['status' => ListingStatus::PendingModeration]);
        $this->postJson($this->url(), ['content_type' => 'image/jpeg', 'size' => 100])->assertForbidden();
    }

    public function test_a_photo_of_another_listing_is_not_reachable_through_this_listing(): void
    {
        $foreign = Photo::factory()->create();

        $this->deleteJson($this->url("/{$foreign->id}"))->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_the_public_only_sees_ready_photos(): void
    {
        $this->listing->update(['status' => ListingStatus::Active]);
        Photo::factory()->for($this->listing)->create(['position' => 0]);
        Photo::factory()->for($this->listing)->pending()->create(['position' => 1]);

        $this->getJson("/api/v1/listings/{$this->listing->id}")->assertJsonCount(2, 'data.photos');

        Sanctum::actingAs(User::factory()->create());
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/listings/{$this->listing->id}")->assertJsonCount(1, 'data.photos');
    }

    public function test_a_sale_cannot_be_submitted_without_a_ready_photo(): void
    {
        $category = Category::factory()->create();
        AttributeDefinition::factory()->for($category)->create(['key' => 'marque']);
        $listing = Listing::factory()->for($this->seller)->for($category)->create();

        $this->postJson("/api/v1/listings/{$listing->id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('errors.photos.0', 'Ajoutez au moins une photo.');

        Photo::factory()->for($listing)->create();
        $this->postJson("/api/v1/listings/{$listing->id}/submit")->assertOk();
    }
}
