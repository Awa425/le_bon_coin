<?php

namespace App\Modules\Media\Uploads;

use Aws\S3\PostObjectV4;
use Aws\S3\S3Client;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Envoi par « POST policy » S3 (SigV4), plutôt qu'une URL PUT pré-signée : une URL PUT ne peut pas
 * imposer la taille (Content-Length n'est pas signé), alors qu'une politique POST impose au stockage
 * la clé exacte, le type exact et la taille exacte (`content-length-range`).
 * Fonctionne avec AWS S3, Scaleway, OVHcloud et SeaweedFS.
 */
class S3PostPolicySigner implements PhotoUploadSigner
{
    public function __construct(private readonly string $disk) {}

    public function sign(string $path, string $contentType, int $size, DateTimeInterface $expiresAt): UploadForm
    {
        $adapter = Storage::disk($this->disk);
        /** @var S3Client $client */
        $client = $adapter->getClient();
        $bucket = config("filesystems.disks.{$this->disk}.bucket");

        $post = new PostObjectV4(
            $client,
            $bucket,
            ['key' => $path, 'Content-Type' => $contentType],
            [
                ['bucket' => $bucket],
                ['eq', '$key', $path],
                ['eq', '$Content-Type', $contentType],
                ['content-length-range', $size, $size],
            ],
            $expiresAt->format(DATE_ATOM),
        );

        return new UploadForm($post->getFormAttributes()['action'], $post->getFormInputs());
    }
}
