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
        Schema::create('recycling_sessions', function (Blueprint $table) {
            $table->bigIncrements('recycling_session_id')->primary();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('machine_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
            $table->string('status')->default('in_progress'); // in_progress, completed, cancelled, error
            $table->integer('items_processed')->default(0);
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->integer('total_points_earned')->default(0);
            $table->json('items_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('set null');
            $table->foreign('machine_id')->references('machine_id')->on('machines')->onDelete('cascade');
            $table->foreign('transaction_id')->references('transaction_id')->on('transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recycling_sessions');
    }
};
