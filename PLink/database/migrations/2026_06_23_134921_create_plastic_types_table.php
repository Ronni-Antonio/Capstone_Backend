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
        Schema::create('plastic_types', function (Blueprint $table) {
            $table->bigIncrements('plastic_type_id')->primary();
            $table->string('name'); // PET, HDPE, PVC, LDPE, PP, PS, Other
            $table->integer('points_per_item')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plastic_types');
    }
};
