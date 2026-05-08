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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // notification | warning
            $table->string('title');
            $table->text('description');
            $table->string('target_type'); // locations_departments | employees
            $table->foreignId('target_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('target_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->json('target_employee_ids')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
