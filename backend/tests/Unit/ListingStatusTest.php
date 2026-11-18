<?php

namespace Tests\Unit;

use App\Enums\ListingStatus;
use PHPUnit\Framework\TestCase;

class ListingStatusTest extends TestCase
{
    public function test_nominal_lifecycle_follows_the_specification(): void
    {
        $this->assertTrue(ListingStatus::Draft->canTransitionTo(ListingStatus::PendingModeration));
        $this->assertTrue(ListingStatus::PendingModeration->canTransitionTo(ListingStatus::Active));
        $this->assertTrue(ListingStatus::Active->canTransitionTo(ListingStatus::Reserved));
        $this->assertTrue(ListingStatus::Reserved->canTransitionTo(ListingStatus::Sold));
    }

    public function test_exit_paths_refused_and_expired(): void
    {
        $this->assertTrue(ListingStatus::PendingModeration->canTransitionTo(ListingStatus::Refused));
        $this->assertTrue(ListingStatus::Refused->canTransitionTo(ListingStatus::PendingModeration));
        $this->assertTrue(ListingStatus::Active->canTransitionTo(ListingStatus::Expired));
        $this->assertTrue(ListingStatus::Expired->canTransitionTo(ListingStatus::PendingModeration));
    }

    public function test_forbidden_shortcuts(): void
    {
        $this->assertFalse(ListingStatus::Draft->canTransitionTo(ListingStatus::Active), 'publication sans modération');
        $this->assertFalse(ListingStatus::PendingModeration->canTransitionTo(ListingStatus::PendingModeration));
        $this->assertSame([], ListingStatus::Sold->allowedTransitions(), 'une annonce vendue est définitive');
    }

    public function test_only_active_and_reserved_listings_are_public(): void
    {
        $public = array_values(array_filter(ListingStatus::cases(), fn (ListingStatus $s) => $s->isPublic()));

        $this->assertSame([ListingStatus::Active, ListingStatus::Reserved], $public);
    }
}
