<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id('model_id');
            $table->string('name');
            $table->string('version', 100);
            $table->string('framework', 100)->nullable(); // TensorFlow/Keras
            $table->decimal('accuracy', 6, 3)->nullable();
            $table->string('model_path')->nullable(); // S3/object path or deployed model identifier
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
