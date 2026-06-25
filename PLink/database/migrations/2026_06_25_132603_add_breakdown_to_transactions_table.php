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
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable()->after('student_id');
            $table->integer('valid_qty')->default(0)->after('bottle_qty');
            $table->integer('contaminated_qty')->default(0)->after('valid_qty');
            $table->integer('non_pet_qty')->default(0)->after('contaminated_qty');
            $table->integer('rejected_qty')->default(0)->after('non_pet_qty');
            $table->json('breakdown')->nullable()->after('points_earned');

            $table->foreign('machine_id')->references('machine_id')->on('machines')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn(['machine_id', 'valid_qty', 'contaminated_qty', 'non_pet_qty', 'rejected_qty', 'breakdown']);
        });
    }
};
