<?php

namespace App\Modules\Media\Console;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Crée les buckets photos s'ils n'existent pas (environnement local, CI, premier déploiement).
 * La lecture publique du bucket des déclinaisons se règle chez l'hébergeur
 * (en local : docker/seaweedfs/s3.json, identité « anonymous »).
 */
class SetupPhotoStorage extends Command
{
    protected $signature = 'photos:setup-storage';

    protected $description = 'Crée les buckets S3 des photos (originaux privés, déclinaisons publiques)';

    public function handle(): int
    {
        foreach ([config('photos.uploads_disk'), config('photos.public_disk')] as $disk) {
            $bucket = config("filesystems.disks.{$disk}.bucket");

            try {
                /** @var S3Client $client */
                $client = Storage::disk($disk)->getClient();

                if ($client->doesBucketExistV2($bucket)) {
                    $this->components->info("Bucket « {$bucket} » ({$disk}) : déjà présent.");
                } else {
                    $client->createBucket(['Bucket' => $bucket]);
                    $client->waitUntil('BucketExists', ['Bucket' => $bucket]);
                    $this->components->info("Bucket « {$bucket} » ({$disk}) : créé.");
                }

                // Envoi direct depuis le navigateur : CORS sur le bucket des originaux uniquement.
                if ($disk === config('photos.uploads_disk')) {
                    $client->putBucketCors([
                        'Bucket' => $bucket,
                        'CORSConfiguration' => ['CORSRules' => [[
                            'AllowedOrigins' => config('photos.cors_origins'),
                            'AllowedMethods' => ['POST'],
                            'AllowedHeaders' => ['*'],
                            'ExposeHeaders' => ['ETag'],
                            'MaxAgeSeconds' => 3600,
                        ]]],
                    ]);
                    $this->components->info('CORS « '.$bucket.' » : '.implode(', ', config('photos.cors_origins')));
                }
            } catch (Throwable $e) {
                $this->components->error("Bucket « {$bucket} » ({$disk}) : {$e->getMessage()}");
                $this->line('  Le stockage S3 est-il démarré ? (docker compose up -d s3)');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
