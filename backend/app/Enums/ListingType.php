<?php

namespace App\Enums;

/** Types d'annonces (cahier des charges §1.1). */
enum ListingType: string
{
    case Sale = 'sale';
    case Rental = 'rental';
    case Wanted = 'wanted';
    case Service = 'service';
    case Job = 'job';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
