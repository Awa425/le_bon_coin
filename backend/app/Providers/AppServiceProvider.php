<?php

namespace App\Providers;

use App\Models\Listing;
use App\Models\Photo;
use App\Modules\Listings\Policies\ListingPolicy;
use App\Modules\Media\Uploads\PhotoUploadSigner;
use App\Modules\Media\Uploads\S3PostPolicySigner;
use App\Modules\Search\Observers\SearchSyncObserver;
use App\Modules\Search\OpenSearch\OpenSearchClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PhotoUploadSigner::class, fn () => new S3PostPolicySigner(config('photos.uploads_disk')));
        $this->app->singleton(OpenSearchClient::class, fn () => OpenSearchClient::fromConfig());
    }

    public function boot(): void
    {
        Gate::policy(Listing::class, ListingPolicy::class);

        // Index de recherche alimenté de façon asynchrone (§5.1).
        Listing::observe(SearchSyncObserver::class);
        Photo::observe(SearchSyncObserver::class);

        $this->configureRateLimiting();
    }

    /** Limites du cahier des charges §6.2 (valeurs de départ, à affiner en production). */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(120)->by('user:'.$request->user()->id)
            : Limit::perMinute(60)->by('ip:'.$request->ip()));

        // Anti force brute : par couple e-mail + IP, et plafond global par IP.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
        ]);

        // Envois de photos : 60 par heure et par compte.
        RateLimiter::for('photo-uploads', fn (Request $request) => Limit::perHour(60)->by('photos:'.$request->user()?->id));

        // 5 nouvelles annonces par heure et par compte.
        RateLimiter::for('listing-creation', fn (Request $request) => Limit::perHour(5)->by('listings:'.$request->user()?->id));
    }
}
