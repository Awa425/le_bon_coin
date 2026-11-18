<?php

namespace App\Modules\Media\Images;

use GdImage;

/**
 * Génère les déclinaisons WebP / AVIF d'une photo (cahier des charges §5.5) avec GD.
 *
 * - Le format réel est vérifié sur le contenu (pas sur l'extension ni le Content-Type déclaré).
 * - Garde-fou contre les « bombes de décompression » (nombre de pixels maximal).
 * - L'orientation EXIF est appliquée, puis l'image est ré-encodée : toutes les métadonnées
 *   (dont la position GPS) disparaissent des fichiers publiés.
 * - Pas d'agrandissement : une image plus petite qu'une déclinaison garde sa taille.
 */
class ImageProcessor
{
    /**
     * @param  array<string, int>  $sizes  nom => largeur maximale
     * @param  array<string, string>  $allowedMimeTypes  mime => extension
     */
    public function __construct(
        private readonly array $sizes,
        private readonly array $allowedMimeTypes,
        private readonly int $maxPixels,
        private readonly int $minSide,
        private readonly int $webpQuality = 80,
        private readonly int $avifQuality = 55,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            sizes: config('photos.sizes'),
            allowedMimeTypes: config('photos.mime_types'),
            maxPixels: config('photos.max_pixels'),
            minSide: config('photos.min_side'),
            webpQuality: config('photos.quality.webp'),
            avifQuality: config('photos.quality.avif'),
        );
    }

    public function process(string $binary): ProcessedImage
    {
        $info = @getimagesizefromstring($binary);

        if ($info === false) {
            throw new InvalidImage("Ce fichier n'est pas une image lisible.");
        }

        [$width, $height] = $info;
        $mime = $info['mime'];

        if (! array_key_exists($mime, $this->allowedMimeTypes)) {
            throw new InvalidImage('Format non accepté : utilisez une photo JPEG, PNG ou WebP.');
        }

        if ($width * $height > $this->maxPixels) {
            throw new InvalidImage('Image trop grande (50 mégapixels maximum).');
        }

        if (min($width, $height) < $this->minSide) {
            throw new InvalidImage("Image trop petite ({$this->minSide} pixels minimum de côté).");
        }

        $image = @imagecreatefromstring($binary);

        if (! $image instanceof GdImage) {
            throw new InvalidImage("Impossible de décoder l'image.");
        }

        if ($mime === 'image/jpeg') {
            $image = $this->applyExifOrientation($image, $binary);
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $variants = [];

        foreach ($this->sizes as $name => $maxWidth) {
            $resized = $sourceWidth > $maxWidth ? $this->resize($image, $maxWidth) : $image;

            $variants[$name] = new ImageVariant(
                width: imagesx($resized),
                height: imagesy($resized),
                webp: $this->encode(fn () => imagewebp($resized, null, $this->webpQuality)),
                avif: $this->encode(fn () => imageavif($resized, null, $this->avifQuality, 8)),
            );
        }

        return new ProcessedImage($sourceWidth, $sourceHeight, $mime, $variants);
    }

    private function resize(GdImage $image, int $width): GdImage
    {
        $resized = imagescale($image, $width, -1, IMG_BICUBIC);

        if (! $resized instanceof GdImage) {
            throw new InvalidImage("Impossible de redimensionner l'image.");
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        return $resized;
    }

    /** Remet l'image à l'endroit selon le tag EXIF « Orientation » (photos de smartphone). */
    private function applyExifOrientation(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    private function encode(callable $writer): string
    {
        ob_start();
        $ok = $writer();
        $data = (string) ob_get_clean();

        if (! $ok || $data === '') {
            throw new InvalidImage("Échec de l'encodage de l'image.");
        }

        return $data;
    }
}
