<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycling_items', function (Blueprint $table) {
            $table->id('recycling_item_id');
            $table->foreignId('transaction_id')
                ->constrained('recycling_transactions', 'transaction_id')
                ->cascadeOnDelete();

            $table->unsignedInteger('item_number');
            $table->string('image_path')->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('status')->default('processing');
            // processing, accepted, rejected, failed

            $table->timestamps();

            $table->unique(['transaction_id', 'item_number']);
            $table->index(['transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycling_items');
    }
};
