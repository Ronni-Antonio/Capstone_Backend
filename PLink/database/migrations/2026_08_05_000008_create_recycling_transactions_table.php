<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recycling_transactions', function (Blueprint $table) {
            $table->id('transaction_id');

            // NULL until the RFID card is scanned.
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students', 'student_id')
                ->nullOnDelete();

            $table->foreignId('rfid_card_id')
                ->nullable()
                ->constrained('rfid_cards', 'rfid_card_id')
                ->nullOnDelete();

            $table->foreignId('smart_bin_id')
                ->constrained('smart_bins', 'smart_bin_id')
                ->restrictOnDelete();

            // Public/device-facing identifier; do not expose the numeric PK.
            $table->uuid('transaction_code')->unique();

            $table->string('status')->default('pending');
            // pending, classifying, waiting_for_rfid, completed, rejected, cancelled

            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_points')->default(0);

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['smart_bin_id', 'status']);
            $table->index(['student_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycling_transactions');
    }
};
