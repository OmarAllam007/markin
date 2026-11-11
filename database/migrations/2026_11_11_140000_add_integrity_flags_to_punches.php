<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['attendance_punches', 'attendance_punch_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('gps_accuracy', 8, 2)->nullable();
                $t->boolean('is_mocked')->default(false);
                $t->boolean('is_rooted')->default(false);
                $t->boolean('is_emulator')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['attendance_punches', 'attendance_punch_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['gps_accuracy', 'is_mocked', 'is_rooted', 'is_emulator']);
            });
        }
    }
};
