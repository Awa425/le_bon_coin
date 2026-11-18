<?php

namespace App\Modules\Search\Jobs;

use App\Models\Listing;
use App\Modules\Search\Index\ListingIndex;
use App\Modules\Search\OpenSearch\OpenSearchClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Met l'index en phase avec la base (source de vérité, §5.1) : indexe une annonce publique,
 * retire une annonce qui ne l'est plus (ou supprimée). Délai cible < 5 s après publication.
 */
class SyncListingToIndex implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    /** Plusieurs modifications rapprochées d'une même annonce → une seule synchronisation en attente. */
    public int $uniqueFor = 30;

    public function __construct(public readonly string $listingId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->listingId;
    }

    public function handle(OpenSearchClient $client, ListingIndex $index): void
    {
        $listing = Listing::query()->with(['category', 'photos'])->find($this->listingId);

        // Premier usage : jamais d'index créé implicitement (il aurait un mapping deviné, donc faux).
        if (! $client->indexExists($index->alias())) {
            $target = $index->newVersionName();
            $client->createIndex($target, $index->definition());
            $client->swapAlias($index->alias(), $target);
        }

        if ($listing === null || ! $index->shouldBeIndexed($listing)) {
            $client->delete($index->alias(), $this->listingId);

            return;
        }

        $client->index($index->alias(), $listing->id, $index->document($listing));
    }
}
