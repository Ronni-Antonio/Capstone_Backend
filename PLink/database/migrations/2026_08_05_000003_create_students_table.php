<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id('student_id');
            $table->string('student_number')->unique();
            $table->string('first_name');
            $table->string('last_name');

            $table->foreignId('grade_level_id')
                ->constrained('grade_levels', 'grade_level_id')
                ->restrictOnDelete();

            $table->foreignId('section_id')
                ->constrained('sections', 'section_id')
                ->restrictOnDelete();

            $table->string('status')->default('active');
            $table->unsignedInteger('points_balance')->default(0);

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
