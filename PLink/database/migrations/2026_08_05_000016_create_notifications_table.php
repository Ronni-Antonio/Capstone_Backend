<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');

            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students', 'student_id')
                ->nullOnDelete();

            $table->foreignId('smart_bin_id')
                ->nullable()
                ->constrained('smart_bins', 'smart_bin_id')
                ->nullOnDelete();

            $table->string('notification_type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'is_read']);
            $table->index(['smart_bin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
