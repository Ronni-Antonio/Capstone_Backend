<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_device_configs', function (Blueprint $table) {
            $table->id('device_config_id');
            $table->string('controller_code', 50)->unique();
            $table->string('device_name');
            $table->string('wifi_ssid')->nullable();
            $table->text('wifi_password')->nullable();
            $table->unsignedBigInteger('config_version')->default(1);
            $table->unsignedBigInteger('applied_version')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('iot_device_configs')->insert([
            [
                'controller_code' => 'controller-1',
                'device_name' => 'ESP32 Controller 1',
                'config_version' => 1,
                'applied_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'controller_code' => 'controller-2',
                'device_name' => 'ESP32 Controller 2',
                'config_version' => 1,
                'applied_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_device_configs');
    }
};
