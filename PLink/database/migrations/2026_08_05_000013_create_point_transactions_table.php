<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id('point_transaction_id');

            $table->foreignId('student_id')
                ->constrained('students', 'student_id')
                ->restrictOnDelete();

            $table->foreignId('recycling_transaction_id')
                ->nullable()
                ->constrained('recycling_transactions', 'transaction_id')
                ->nullOnDelete();

            $table->foreignId('redemption_id')
                ->nullable()
                ->constrained('redemptions', 'redemption_id')
                ->nullOnDelete();

            $table->integer('points'); // positive earned, negative spent/adjusted
            $table->string('transaction_type'); // earned, redeemed, adjustment
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
