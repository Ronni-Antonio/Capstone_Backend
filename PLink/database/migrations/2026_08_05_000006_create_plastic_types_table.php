<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plastic_types', function (Blueprint $table) {
            $table->id('plastic_type_id');
            $table->string('code', 50)->unique(); // PET, INVALID, etc.
            $table->string('name')->unique();
            $table->unsignedInteger('points_value')->default(0);
            $table->boolean('is_accepted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_accepted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plastic_types');
    }
};
