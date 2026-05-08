<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove settings columns that were previously added directly to tenants
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            // General
            $table->string('company_name')->nullable();
            $table->string('subdomain')->nullable()->unique();
            $table->string('cr_number')->nullable();
            $table->string('company_email')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_number')->nullable();
            $table->text('terms')->nullable();
            $table->text('policy')->nullable();
            $table->string('logo_path')->nullable();

            // Attendance
            $table->string('attendance_via')->default('all');
            $table->unsignedSmallInteger('checkin_before_minutes')->nullable();
            $table->unsignedSmallInteger('checkout_after_minutes')->nullable();
            $table->boolean('allow_temporary_shifts')->default(false);
            $table->string('temporary_shift_calculation')->nullable();
            $table->boolean('check_biometrics')->default(true);
            $table->boolean('send_reminders')->default(true);
            $table->boolean('allow_remote_checkin')->default(false);
            $table->boolean('allow_any_location_checkin')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cr_number')->nullable();
            $table->string('company_email')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_number')->nullable();
            $table->text('terms')->nullable();
            $table->text('policy')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('attendance_via')->default('all');
            $table->unsignedSmallInteger('checkin_before_minutes')->nullable();
            $table->unsignedSmallInteger('checkout_after_minutes')->nullable();
            $table->boolean('allow_temporary_shifts')->default(false);
            $table->string('temporary_shift_calculation')->nullable();
            $table->boolean('check_biometrics')->default(true);
            $table->boolean('send_reminders')->default(true);
            $table->boolean('allow_remote_checkin')->default(false);
            $table->boolean('allow_any_location_checkin')->default(false);
        });
    }
};
