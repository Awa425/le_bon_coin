<?php

namespace App\Modules\Listings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Modules\Listings\Actions\SaveListingDraft;
use App\Modules\Listings\Actions\SubmitListing;
use App\Modules\Listings\Http\Requests\ListingDraftRequest;
use App\Modules\Listings\Http\Resources\ListingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    public function store(ListingDraftRequest $request, SaveListingDraft $save): JsonResponse
    {
        $listing = $save->create($request->user(), $request->validated());

        return (new ListingResource($listing->load('photos')))->response()->setStatusCode(201);
    }

    public function show(Listing $listing): ListingResource
    {
        Gate::authorize('view', $listing);

        return new ListingResource($listing->load('photos'));
    }

    public function update(ListingDraftRequest $request, Listing $listing, SaveListingDraft $save): ListingResource
    {
        return new ListingResource($save->update($listing, $request->validated())->load('photos'));
    }

    public function submit(Listing $listing, SubmitListing $submit): ListingResource
    {
        Gate::authorize('submit', $listing);

        return new ListingResource($submit($listing)->load('photos'));
    }
}
