<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les tests ne touchent jamais au stockage objet réel (S3).
        Storage::fake(config('photos.uploads_disk'));
        Storage::fake(config('photos.public_disk'));
    }
}
