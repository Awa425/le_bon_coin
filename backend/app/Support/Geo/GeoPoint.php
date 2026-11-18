<?php

namespace App\Support\Geo;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Point géographique WGS84 (SRID 4326) stocké dans une colonne PostGIS geography(Point, 4326).
 */
final readonly class GeoPoint implements JsonSerializable
{
    public const SRID = 4326;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("Latitude invalide : {$latitude}");
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("Longitude invalide : {$longitude}");
        }
    }

    /** Représentation EWKT acceptée en entrée par PostGIS (attention : longitude puis latitude). */
    public function toEwkt(): string
    {
        return sprintf('SRID=%d;POINT(%s %s)', self::SRID, $this->longitude, $this->latitude);
    }

    /** Décode l'EWKB hexadécimal renvoyé par PostgreSQL pour une colonne geography. */
    public static function fromEwkbHex(string $hex): self
    {
        $binary = hex2bin($hex);

        if ($binary === false || strlen($binary) < 21) {
            throw new InvalidArgumentException('EWKB invalide.');
        }

        $littleEndian = ord($binary[0]) === 1;
        $type = unpack($littleEndian ? 'V' : 'N', substr($binary, 1, 4))[1];
        $offset = 5;

        if ($type & 0x20000000) {
            $offset += 4; // SRID embarqué
        }

        if (($type & 0xFF) !== 1) {
            throw new InvalidArgumentException('La géométrie EWKB n\'est pas un point.');
        }

        $format = $littleEndian ? 'e' : 'E';
        $longitude = unpack($format, substr($binary, $offset, 8))[1];
        $latitude = unpack($format, substr($binary, $offset + 8, 8))[1];

        return new self($latitude, $longitude);
    }

    /** @return array{lat: float, lng: float} */
    public function jsonSerialize(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude];
    }
}
