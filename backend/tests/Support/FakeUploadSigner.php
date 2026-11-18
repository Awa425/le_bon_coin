<?php

namespace Tests\Support;

use App\Modules\Media\Uploads\PhotoUploadSigner;
use App\Modules\Media\Uploads\UploadForm;
use DateTimeInterface;

/** Signataire factice : enregistre les demandes, renvoie un formulaire déterministe. */
final class FakeUploadSigner implements PhotoUploadSigner
{
    /** @var list<array{path: string, contentType: string, size: int}> */
    public array $signed = [];

    public function sign(string $path, string $contentType, int $size, DateTimeInterface $expiresAt): UploadForm
    {
        $this->signed[] = compact('path', 'contentType', 'size');

        return new UploadForm('https://uploads.test/marketplace-uploads', [
            'key' => $path,
            'Content-Type' => $contentType,
            'Policy' => 'politique-signee',
            'X-Amz-Signature' => 'signature',
        ]);
    }
}
