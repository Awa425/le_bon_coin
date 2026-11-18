<?php

namespace Tests\Support;

/** Fabrique de vraies images de test (GD), y compris un JPEG avec un tag EXIF d'orientation et des coordonnées GPS. */
final class Images
{
    public static function jpeg(int $width = 1600, int $height = 1200): string
    {
        return self::render($width, $height, fn ($image) => imagejpeg($image, null, 85));
    }

    public static function png(int $width = 800, int $height = 600): string
    {
        return self::render($width, $height, fn ($image) => imagepng($image));
    }

    /**
     * JPEG « paysage » 1600×1200 marqué Orientation = 6 (rotation de 90° à appliquer, comme une photo
     * prise téléphone tenu verticalement) et contenant un bloc GPS.
     */
    public static function jpegWithExif(int $orientation = 6): string
    {
        $jpeg = self::jpeg(1600, 1200);

        // Segment APP1 « Exif » minimal (TIFF little-endian) : IFD0 = Orientation + pointeur GPS ; IFD GPS = latitude « N ».
        $tiff = 'II'.pack('v', 42).pack('V', 8);
        $ifd0 = pack('v', 2)
            .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)   // Orientation (SHORT)
            .pack('vvVV', 0x8825, 4, 1, 38)                   // GPSInfo IFD pointer (LONG) → offset 38
            .pack('V', 0);
        $gps = pack('v', 1)
            .pack('vvVa4', 0x0001, 2, 2, "N\0\0\0")            // GPSLatitudeRef = "N"
            .pack('V', 0);
        $exif = "Exif\0\0".$tiff.$ifd0.$gps;
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        // Insertion juste après le marqueur SOI (FFD8).
        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private static function render(int $width, int $height, callable $writer): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 232, 89, 12));
        // Repère dans le coin supérieur gauche pour vérifier la rotation.
        imagefilledrectangle($image, 0, 0, (int) ($width / 4), (int) ($height / 4), imagecolorallocate($image, 255, 255, 255));

        ob_start();
        $writer($image);

        return (string) ob_get_clean();
    }
}
