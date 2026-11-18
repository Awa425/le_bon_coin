<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            // Original envoyé par le navigateur (bucket privé), supprimé après traitement.
            $table->string('original_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Déclinaisons publiques : { "card": { "width": 400, "webp": "…", "avif": "…" }, … }
            $table->jsonb('variants')->default('{}');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'position']);
        });

        DB::statement("ALTER TABLE photos ADD CONSTRAINT photos_status_check CHECK (status IN ('pending', 'processing', 'ready', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
