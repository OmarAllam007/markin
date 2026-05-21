<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->date('attendance_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();

            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('total_late_minutes')->default(0);
            $table->unsignedInteger('total_early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);

            $table->string('status');
            $table->foreignId('shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->time('scheduled_check_in')->nullable();
            $table->time('scheduled_check_out')->nullable();

            $table->string('attendance_source');

            $table->text('comments')->nullable();
            $table->string('attachment_path')->nullable();

            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();
            $table->string('address')->nullable();

            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_manual_edit')->default(false);
            $table->text('edit_reason')->nullable();

            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_locked')->default(false);

            $table->timestamp('payroll_exported_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('attendance_date');
            $table->index('status');
            $table->index('shift_id');
            $table->index(['tenant_id', 'attendance_date']);
            $table->index(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
