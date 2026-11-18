<?php

namespace App\Modules\Media\Uploads;

/** Formulaire multipart à envoyer par le navigateur : champs signés puis le fichier (champ « file », en dernier). */
final readonly class UploadForm
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public string $url,
        public array $fields,
    ) {}

    /** @return array{method: string, url: string, fields: array<string, string>, file_field: string} */
    public function toArray(): array
    {
        return ['method' => 'POST', 'url' => $this->url, 'fields' => $this->fields, 'file_field' => 'file'];
    }
}
