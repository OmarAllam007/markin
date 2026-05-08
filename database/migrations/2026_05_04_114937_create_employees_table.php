<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Employee details
            $table->string('arabic_name');
            $table->string('english_name');
            $table->string('mobile_country_code', 10);
            $table->string('mobile_number', 30);
            $table->string('email')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('religion')->nullable();

            // Job details
            $table->string('job_title_ar')->nullable();
            $table->string('job_title_en')->nullable();
            $table->string('employee_number')->nullable();
            $table->string('social_security_number')->nullable();
            $table->string('id_number')->nullable();
            $table->date('working_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('contract_type')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
