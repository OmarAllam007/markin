<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // General settings
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->after('contract_type');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete()->after('department_id');

            // Attendance settings
            $table->boolean('check_biometrics')->default(false)->after('location_id');
            $table->boolean('send_reminders')->default(false)->after('check_biometrics');
            $table->boolean('allow_remote_checkin')->default(false)->after('send_reminders');
            $table->boolean('allow_any_location_checkin')->default(false)->after('allow_remote_checkin');

            // Shift assignment
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete()->after('allow_any_location_checkin');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['location_id']);
            $table->dropForeign(['work_shift_id']);
            $table->dropColumn([
                'department_id', 'location_id',
                'check_biometrics', 'send_reminders',
                'allow_remote_checkin', 'allow_any_location_checkin',
                'work_shift_id',
            ]);
        });
    }
};
