<?php

namespace App\Enums;

/**
 * Cycle de vie d'une annonce (cahier des charges §4) :
 * BROUILLON → EN_MODERATION → ACTIVE → RESERVEE → VENDUE,
 * avec les sorties REFUSEE (modération) et EXPIREE (60 jours, republiable).
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingModeration = 'pending_moderation';
    case Active = 'active';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Refused = 'refused';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingModeration => 'En modération',
            self::Active => 'Active',
            self::Reserved => 'Réservée',
            self::Sold => 'Vendue',
            self::Refused => 'Refusée',
            self::Expired => 'Expirée',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingModeration],
            self::PendingModeration => [self::Active, self::Refused],
            self::Active => [self::Reserved, self::Sold, self::Expired],
            self::Reserved => [self::Active, self::Sold],
            self::Refused => [self::Draft, self::PendingModeration],
            self::Expired => [self::PendingModeration],
            self::Sold => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Annonce visible publiquement dans la recherche et sur sa fiche. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Active, self::Reserved], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
