<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id('rfid_card_id');
            $table->foreignId('student_id')
                ->constrained('students', 'student_id')
                ->restrictOnDelete();

            $table->string('card_uid', 100)->unique();
            $table->string('status')->default('active'); // active, lost, blocked, unassigned
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_cards');
    }
};
