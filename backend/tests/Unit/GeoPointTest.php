<?php

namespace Tests\Unit;

use App\Casts\GeoPointCast;
use App\Models\Listing;
use App\Support\Geo\GeoPoint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeoPointTest extends TestCase
{
    public function test_it_serializes_to_ewkt_with_longitude_first(): void
    {
        $this->assertSame('SRID=4326;POINT(2.3522 48.8566)', (new GeoPoint(48.8566, 2.3522))->toEwkt());
    }

    public function test_it_decodes_postgis_little_endian_ewkb(): void
    {
        // SELECT 'SRID=4326;POINT(2.3522 48.8566)'::geography
        $hex = '0101000020E6100000A835CD3B4ED1024076E09C11A56D4840';

        $point = GeoPoint::fromEwkbHex($hex);

        $this->assertEqualsWithDelta(48.8566, $point->latitude, 1e-9);
        $this->assertEqualsWithDelta(2.3522, $point->longitude, 1e-9);
    }

    public function test_it_rejects_non_point_geometries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // LINESTRING vide avec SRID 4326
        GeoPoint::fromEwkbHex('0102000020E610000000000000000000000000000000000000');
    }

    /** @return array<string, array{float, float}> */
    public static function invalidCoordinates(): array
    {
        return [
            'latitude trop haute' => [90.1, 0],
            'latitude trop basse' => [-90.1, 0],
            'longitude trop haute' => [0, 180.1],
            'longitude trop basse' => [0, -180.1],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_it_rejects_out_of_range_coordinates(float $lat, float $lng): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeoPoint($lat, $lng);
    }

    public function test_it_serializes_to_json_as_lat_lng(): void
    {
        $this->assertSame('{"lat":45.75,"lng":4.85}', json_encode(new GeoPoint(45.75, 4.85)));
    }

    public function test_cast_accepts_arrays_and_null(): void
    {
        $cast = new GeoPointCast;
        $model = new Listing;

        $this->assertSame('SRID=4326;POINT(4.85 45.75)', $cast->set($model, 'location', ['lat' => 45.75, 'lng' => 4.85], []));
        $this->assertNull($cast->set($model, 'location', null, []));
        $this->assertNull($cast->get($model, 'location', null, []));
    }

    public function test_cast_rejects_unsupported_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GeoPointCast)->set(new Listing, 'location', 'POINT(1 2)', []);
    }
}
