<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classification_histories', function (Blueprint $table) {
            $table->bigIncrements('classification_id')->primary();
            $table->unsignedBigInteger('plastic_type_id')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0.00 to 100.00%
            $table->string('image_path')->nullable(); // If we save captured images
            $table->text('ai_model_version')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('plastic_type_id')->references('plastic_type_id')->on('plastic_types')->onDelete('set null');
            $table->foreign('machine_id')->references('machine_id')->on('machines')->onDelete('set null');
            $table->foreign('transaction_id')->references('transaction_id')->on('transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classification_histories');
    }
};
