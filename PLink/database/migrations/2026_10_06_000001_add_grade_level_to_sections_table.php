<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('grade_level_id')
                ->nullable()
                ->after('section_id')
                ->constrained('grade_levels', 'grade_level_id')
                ->restrictOnDelete();
        });

        // The original schema made section names globally unique. A section name
        // can now exist once per grade level (for example, Grade 4 Rizal and
        // Grade 5 Rizal), so uniqueness must be scoped to grade_level_id.
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_name_unique');
        });

        // Preserve existing data. If one old section contains students from
        // multiple grades, clone that section per grade and reassign students so
        // each resulting section belongs to exactly one grade.
        $sections = DB::table('sections')->orderBy('section_id')->get();

        foreach ($sections as $section) {
            $gradeIds = DB::table('students')
                ->where('section_id', $section->section_id)
                ->whereNotNull('grade_level_id')
                ->distinct()
                ->orderBy('grade_level_id')
                ->pluck('grade_level_id')
                ->values();

            if ($gradeIds->isEmpty()) {
                continue;
            }

            $firstGradeId = (int) $gradeIds->first();
            DB::table('sections')
                ->where('section_id', $section->section_id)
                ->update(['grade_level_id' => $firstGradeId]);

            foreach ($gradeIds->slice(1) as $gradeId) {
                $newSectionId = DB::table('sections')->insertGetId([
                    'grade_level_id' => (int) $gradeId,
                    'name' => $section->name,
                    'created_at' => $section->created_at,
                    'updated_at' => now(),
                ]);

                DB::table('students')
                    ->where('section_id', $section->section_id)
                    ->where('grade_level_id', $gradeId)
                    ->update(['section_id' => $newSectionId]);
            }
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['grade_level_id', 'name'], 'sections_grade_level_name_unique');
        });
    }

    public function down(): void
    {
        // Merge grade-specific copies back to one section per name so the old
        // globally-unique section schema can be restored without losing students.
        $duplicateNames = DB::table('sections')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $ids = DB::table('sections')
                ->where('name', $name)
                ->orderBy('section_id')
                ->pluck('section_id')
                ->values();

            $keepId = (int) $ids->first();
            $removeIds = $ids->slice(1)->map(fn ($id) => (int) $id)->all();

            if (!empty($removeIds)) {
                DB::table('students')
                    ->whereIn('section_id', $removeIds)
                    ->update(['section_id' => $keepId]);

                DB::table('sections')
                    ->whereIn('section_id', $removeIds)
                    ->delete();
            }
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_grade_level_name_unique');
            $table->dropForeign(['grade_level_id']);
            $table->dropColumn('grade_level_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
