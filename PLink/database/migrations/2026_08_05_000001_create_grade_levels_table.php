<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id('grade_level_id');
            $table->string('name')->unique(); // e.g. Grade 1
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
