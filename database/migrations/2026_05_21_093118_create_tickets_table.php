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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('creator_id')->constrained('users');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('source', 20)->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('ticket_groups')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->foreignId('category_id')->constrained('ticket_categories');
            $table->foreignId('subcategory_id')->nullable()->constrained('ticket_subcategories')->nullOnDelete();
            $table->string('type', 50)->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->foreignId('sla_id')->nullable()->constrained('ticket_slas')->nullOnDelete();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('first_response_date')->nullable();
            $table->dateTime('resolve_date')->nullable();
            $table->dateTime('close_date')->nullable();
            $table->integer('time_spent')->default(0);
            $table->boolean('overdue')->default(false);
            $table->foreignId('request_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->json('form_data')->nullable();
            $table->json('client_info')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index('technician_id');
            $table->index(['due_date', 'overdue']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
