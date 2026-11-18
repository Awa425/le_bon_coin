<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('buyer_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->index()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['listing_id', 'buyer_id']);
        });

        DB::statement('ALTER TABLE conversations ADD CONSTRAINT conversations_distinct_participants CHECK (buyer_id <> seller_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
