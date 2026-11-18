<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->index()->constrained()->restrictOnDelete();
            $table->string('type')->default('sale');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('status')->default('draft');
            $table->jsonb('attribute_values')->default('{}');
            $table->geography('location', 'point', 4326)->nullable();
            $table->boolean('hide_phone')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->spatialIndex('location');
        });

        DB::statement('CREATE INDEX listings_attribute_values_gin ON listings USING GIN (attribute_values jsonb_path_ops)');
        DB::statement('ALTER TABLE listings ADD CONSTRAINT listings_price_non_negative CHECK (price IS NULL OR price >= 0)');
        DB::statement("ALTER TABLE listings ADD CONSTRAINT listings_status_check CHECK (status IN ('draft', 'pending_moderation', 'active', 'reserved', 'sold', 'refused', 'expired'))");
        DB::statement("ALTER TABLE listings ADD CONSTRAINT listings_type_check CHECK (type IN ('sale', 'rental', 'wanted', 'service', 'job'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
