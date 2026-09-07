<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recyclable_types')
            ->where('code', 'CONTAMINATED')
            ->update([
                'name' => 'Dirty / Damaged Recyclable Material',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('recyclable_types')
            ->where('code', 'CONTAMINATED')
            ->update([
                'name' => 'Contaminated PET Bottle',
                'updated_at' => now(),
            ]);
    }
};
