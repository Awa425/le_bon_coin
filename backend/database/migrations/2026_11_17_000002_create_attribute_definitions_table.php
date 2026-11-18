<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->string('unit')->nullable();
            $table->jsonb('options')->default('[]');
            $table->decimal('min_value', 14, 2)->nullable();
            $table->decimal('max_value', 14, 2)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
        });

        DB::statement("ALTER TABLE attribute_definitions ADD CONSTRAINT attribute_definitions_type_check CHECK (type IN ('text', 'number', 'boolean', 'select', 'multi_select'))");
        DB::statement("ALTER TABLE attribute_definitions ADD CONSTRAINT attribute_definitions_key_format CHECK (key ~ '^[a-z][a-z0-9_]*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_definitions');
    }
};
