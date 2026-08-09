<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id('prediction_id');

            $table->foreignId('model_id')
                ->nullable()
                ->constrained('ai_models', 'model_id')
                ->nullOnDelete();

            $table->string('prediction_type'); // plastic_reduction, collection_volume, etc.
            $table->date('prediction_date');
            $table->date('target_date')->nullable();
            $table->decimal('predicted_value', 12, 4)->nullable();
            $table->decimal('actual_value', 12, 4)->nullable();
            $table->decimal('confidence', 6, 4)->nullable();
            $table->json('input_summary')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();

            $table->index(['prediction_type', 'prediction_date']);
            $table->index(['model_id', 'prediction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
