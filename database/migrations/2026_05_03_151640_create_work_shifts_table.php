<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // fixed | flexible
            $table->json('weekends')->nullable();

            // Fixed shift
            $table->string('checkin_time')->nullable();
            $table->string('checkout_time')->nullable();

            // Flexible shift
            $table->unsignedSmallInteger('working_hours')->nullable();
            $table->unsignedSmallInteger('working_minutes')->nullable();
            $table->string('limit_checkin_from')->nullable();
            $table->string('limit_checkin_to')->nullable();

            // Late & Overtime
            $table->boolean('overtime_enabled')->default(false);
            $table->unsignedSmallInteger('overtime_hours')->nullable();
            $table->unsignedSmallInteger('overtime_minutes')->nullable();
            $table->boolean('calculate_overtime_early_checkin')->default(false);

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
