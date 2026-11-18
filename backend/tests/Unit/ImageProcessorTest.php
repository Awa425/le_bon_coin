<?php

namespace Tests\Unit;

use App\Modules\Media\Images\ImageProcessor;
use App\Modules\Media\Images\InvalidImage;
use Tests\Support\Images;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    private function processor(): ImageProcessor
    {
        return ImageProcessor::fromConfig();
    }

    public function test_it_generates_webp_and_avif_variants_without_upscaling(): void
    {
        $result = $this->processor()->process(Images::jpeg(1600, 1200));

        $this->assertSame([1600, 1200, 'image/jpeg'], [$result->width, $result->height, $result->mimeType]);
        $this->assertSame(['thumb', 'card', 'detail', 'full'], array_keys($result->variants));
        $this->assertSame([200, 150], [$result->variants['thumb']->width, $result->variants['thumb']->height]);
        $this->assertSame(400, $result->variants['card']->width);
        $this->assertSame(1200, $result->variants['detail']->width);
        $this->assertSame(1600, $result->variants['full']->width, 'pas d’agrandissement au-delà de l’original');

        foreach ($result->variants as $variant) {
            $this->assertSame('image/webp', getimagesizefromstring($variant->webp)['mime']);
            $this->assertStringContainsString('ftypavif', substr($variant->avif, 0, 32));
        }
    }

    public function test_exif_orientation_is_applied_and_metadata_removed(): void
    {
        $source = Images::jpegWithExif(orientation: 6);
        $this->assertSame(6, exif_read_data('data://image/jpeg;base64,'.base64_encode($source))['Orientation']);
        $this->assertStringContainsString('Exif', $source);

        $result = $this->processor()->process($source);

        // 1600×1200 tenu verticalement → 1200×1600 une fois redressée.
        $this->assertSame([1200, 1600], [$result->width, $result->height]);
        $this->assertSame([1200, 1600], [$result->variants['full']->width, $result->variants['full']->height]);

        foreach ($result->variants as $variant) {
            $this->assertStringNotContainsString('Exif', $variant->webp, 'aucune métadonnée EXIF / GPS publiée');
            $this->assertStringNotContainsString('Exif', $variant->avif);
        }
    }

    public function test_png_is_accepted(): void
    {
        $this->assertSame('image/png', $this->processor()->process(Images::png())->mimeType);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessage("n'est pas une image lisible");

        $this->processor()->process('<?php echo "pas une image";');
    }

    public function test_unsupported_formats_are_rejected(): void
    {
        $image = imagecreatetruecolor(400, 400);
        ob_start();
        imagegif($image);
        $gif = (string) ob_get_clean();

        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessage('Format non accepté');

        $this->processor()->process($gif);
    }

    public function test_too_small_images_are_rejected(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessage('trop petite');

        $this->processor()->process(Images::jpeg(150, 150));
    }

    public function test_decompression_bombs_are_rejected_before_decoding(): void
    {
        config(['photos.max_pixels' => 1_000_000]);

        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessage('trop grande');

        $this->processor()->process(Images::jpeg(1600, 1200));
    }
}
