<?php

namespace App\Modules\Media\Images;

final readonly class ImageVariant
{
    public function __construct(
        public int $width,
        public int $height,
        public string $webp,
        public string $avif,
    ) {}
}
