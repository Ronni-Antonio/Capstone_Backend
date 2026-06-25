<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->bigIncrements('section_id')->primary();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Migrate existing sections from students table
        $existingSections = DB::table('students')
            ->select('section')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->distinct()
            ->pluck('section');

        foreach ($existingSections as $sectionName) {
            DB::table('sections')->insertOrIgnore(['name' => $sectionName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
