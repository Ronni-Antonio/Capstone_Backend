<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_controller_commands', function (Blueprint $table) {
            $table->bigIncrements('command_id');
            $table->string('controller_code', 64)->index();
            $table->string('command_type', 64)->index();
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'claimed', 'completed', 'failed', 'cancelled', 'expired'])
                ->default('pending')
                ->index();
            $table->text('result_message')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['controller_code', 'status', 'created_at'], 'iot_cmd_controller_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_controller_commands');
    }
};
