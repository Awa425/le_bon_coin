<?php

namespace App\Modules\Search\Observers;

use App\Models\Listing;
use App\Models\Photo;
use App\Modules\Search\Jobs\SyncListingToIndex;
use Illuminate\Database\Eloquent\Model;

/** Toute modification d'une annonce ou de ses photos déclenche une synchronisation de l'index. */
class SearchSyncObserver
{
    public function saved(Model $model): void
    {
        $this->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        if (! config('search.sync')) {
            return;
        }

        $listingId = match (true) {
            $model instanceof Listing => $model->id,
            $model instanceof Photo => $model->listing_id,
            default => null,
        };

        if ($listingId !== null) {
            SyncListingToIndex::dispatch($listingId);
        }
    }
}
