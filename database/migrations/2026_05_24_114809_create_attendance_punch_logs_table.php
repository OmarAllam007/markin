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
        Schema::create('attendance_punch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('source'); // mobile, biometric, manual, etc.
            $table->string('punch_type')->nullable(); // check_in / check_out
            $table->string('status'); // success / failed
            $table->text('failure_reason')->nullable();
            $table->foreignId('attendance_punch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_name')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['employee_id', 'attempted_at']);
            $table->index(['tenant_id', 'attempted_at']);
            $table->index(['status', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_punch_logs');
    }
};
