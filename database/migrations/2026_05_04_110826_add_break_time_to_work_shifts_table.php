<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('break_random_checks')->nullable()->after('calculate_overtime_early_checkin');
            $table->unsignedSmallInteger('break_hours')->nullable()->after('break_random_checks');
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('break_hours');
            $table->string('break_start_from')->nullable()->after('break_minutes');
            $table->string('break_start_to')->nullable()->after('break_start_from');
            $table->boolean('break_apply_as_overtime')->default(false)->after('break_start_to');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'break_random_checks',
                'break_hours',
                'break_minutes',
                'break_start_from',
                'break_start_to',
                'break_apply_as_overtime',
            ]);
        });
    }
};
