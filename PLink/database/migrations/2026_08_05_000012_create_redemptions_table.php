<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id('redemption_id');

            $table->foreignId('student_id')
                ->constrained('students', 'student_id')
                ->restrictOnDelete();

            $table->foreignId('reward_id')
                ->constrained('rewards', 'reward_id')
                ->restrictOnDelete();

            $table->string('redemption_code', 36)->unique();
            $table->unsignedInteger('points_spent');
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamps();

            $table->index(['student_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
