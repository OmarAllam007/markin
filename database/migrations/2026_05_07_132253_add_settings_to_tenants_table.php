<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cr_number')->nullable()->after('domain');
            $table->string('company_email')->nullable()->after('cr_number');
            $table->string('address')->nullable()->after('company_email');
            $table->string('country')->nullable()->after('address');
            $table->string('city')->nullable()->after('country');
            $table->string('postal_number')->nullable()->after('city');
            $table->text('terms')->nullable()->after('postal_number');
            $table->text('policy')->nullable()->after('terms');
            $table->string('logo_path')->nullable()->after('policy');

            $table->string('attendance_via')->default('all')->after('logo_path');
            $table->unsignedSmallInteger('checkin_before_minutes')->nullable()->after('attendance_via');
            $table->unsignedSmallInteger('checkout_after_minutes')->nullable()->after('checkin_before_minutes');
            $table->boolean('allow_temporary_shifts')->default(false)->after('checkout_after_minutes');
            $table->string('temporary_shift_calculation')->nullable()->after('allow_temporary_shifts');
            $table->boolean('check_biometrics')->default(true)->after('temporary_shift_calculation');
            $table->boolean('send_reminders')->default(true)->after('check_biometrics');
            $table->boolean('allow_remote_checkin')->default(false)->after('send_reminders');
            $table->boolean('allow_any_location_checkin')->default(false)->after('allow_remote_checkin');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cr_number', 'company_email', 'address', 'country', 'city',
                'postal_number', 'terms', 'policy', 'logo_path',
                'attendance_via', 'checkin_before_minutes', 'checkout_after_minutes',
                'allow_temporary_shifts', 'temporary_shift_calculation',
                'check_biometrics', 'send_reminders', 'allow_remote_checkin',
                'allow_any_location_checkin',
            ]);
        });
    }
};
