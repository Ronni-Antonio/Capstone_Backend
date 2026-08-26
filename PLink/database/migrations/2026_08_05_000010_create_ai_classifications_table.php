<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_classifications', function (Blueprint $table) {
            $table->id('classification_id');

            $table->foreignId('recycling_item_id')
                ->constrained('recycling_items', 'recycling_item_id')
                ->cascadeOnDelete();

            $table->foreignId('recyclable_type_id')
                ->nullable()
                ->constrained('recyclable_types', 'recyclable_type_id')
                ->nullOnDelete();

            $table->foreignId('model_id')
                ->nullable()
                ->constrained('ai_models', 'model_id')
                ->nullOnDelete();

            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('status')->default('valid');
            // valid, rejected, uncertain, failed, manually_verified

            $table->text('notes')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('classified_at')->nullable();
            $table->timestamps();

            $table->index(['recyclable_type_id', 'created_at']);
            $table->index(['model_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_classifications');
    }
};
