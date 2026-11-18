<?php

namespace App\Modules\Listings\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /** Annonce publique visible de tous ; sinon réservée à son auteur. */
    public function view(?User $user, Listing $listing): bool
    {
        return $listing->status->isPublic() || $this->owns($user, $listing);
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->isEditable();
    }

    public function submit(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    private function owns(?User $user, Listing $listing): bool
    {
        return $user !== null && $user->id === $listing->user_id;
    }
}
