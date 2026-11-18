<?php

namespace App\Modules\Search\Console;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Modules\Search\Index\ListingIndex;
use App\Modules\Search\OpenSearch\OpenSearchClient;
use Illuminate\Console\Command;

/**
 * Reconstruit l'index sans interruption : nouvel index versionné, remplissage par lots,
 * bascule atomique de l'alias, puis suppression des anciennes versions.
 */
class ReindexListings extends Command
{
    protected $signature = 'search:reindex {--chunk=500 : taille des lots}';

    protected $description = 'Reconstruit l’index de recherche des annonces (sans interruption de service)';

    public function handle(OpenSearchClient $client, ListingIndex $index): int
    {
        if (! $client->ping()) {
            $this->components->error('OpenSearch est injoignable ('.config('search.host').'). Lancez : docker compose up -d opensearch');

            return self::FAILURE;
        }

        $alias = $index->alias();
        $target = $index->newVersionName();
        $previous = $client->aliasTargets($alias);

        // Ancienne installation : un index (et non un alias) porte déjà ce nom.
        if ($previous === [] && $client->indexExists($alias)) {
            $client->deleteIndex($alias);
        }

        $client->createIndex($target, $index->definition());
        $this->components->info("Index « {$target} » créé.");

        $count = 0;
        Listing::query()
            ->whereIn('status', [ListingStatus::Active, ListingStatus::Reserved])
            ->with(['category', 'photos'])
            ->chunkById((int) $this->option('chunk'), function ($listings) use ($client, $index, $target, &$count): void {
                $documents = [];
                foreach ($listings as $listing) {
                    $documents[$listing->id] = $index->document($listing);
                }
                $client->bulkIndex($target, $documents);
                $count += count($documents);
                $this->output->write('.');
            });

        $client->refresh($target);
        $client->swapAlias($alias, $target);

        foreach ($previous as $old) {
            $client->deleteIndex($old);
        }

        $this->newLine();
        $this->components->info("{$count} annonce(s) indexée(s) ; l'alias « {$alias} » pointe vers « {$target} ».");

        return self::SUCCESS;
    }
}
