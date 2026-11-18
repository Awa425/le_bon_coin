<?php

namespace App\Modules\Media\Images;

final readonly class ProcessedImage
{
    /** @param  array<string, ImageVariant>  $variants */
    public function __construct(
        public int $width,
        public int $height,
        public string $mimeType,
        public array $variants,
    ) {}
}
