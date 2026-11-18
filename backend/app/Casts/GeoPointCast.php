<?php

namespace App\Casts;

use App\Support\Geo\GeoPoint;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<GeoPoint|null, GeoPoint|array{lat: float, lng: float}|null>
 */
class GeoPointCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GeoPoint
    {
        if ($value === null || $value === '') {
            return null;
        }

        return GeoPoint::fromEwkbHex($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && isset($value['lat'], $value['lng'])) {
            $value = new GeoPoint((float) $value['lat'], (float) $value['lng']);
        }

        if (! $value instanceof GeoPoint) {
            throw new InvalidArgumentException("La valeur de [{$key}] doit être un GeoPoint ou un tableau {lat, lng}.");
        }

        return $value->toEwkt();
    }
}
