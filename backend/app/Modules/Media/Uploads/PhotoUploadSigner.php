<?php

namespace App\Modules\Media\Uploads;

use DateTimeInterface;

/**
 * Autorise un envoi direct navigateur → stockage objet pour UN fichier précis.
 * Le stockage lui-même doit refuser tout fichier dont la clé, le type ou la taille diffèrent.
 */
interface PhotoUploadSigner
{
    public function sign(string $path, string $contentType, int $size, DateTimeInterface $expiresAt): UploadForm;
}
