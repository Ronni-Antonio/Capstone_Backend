<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id('collection_id');

            $table->foreignId('smart_bin_id')
                ->constrained('smart_bins', 'smart_bin_id')
                ->restrictOnDelete();

            $table->foreignId('collected_by_user_id')
                ->nullable()
                ->constrained('users', 'id')
                ->nullOnDelete();

            $table->unsignedInteger('bottles_collected');
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->timestamp('collection_date')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['smart_bin_id', 'collection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
