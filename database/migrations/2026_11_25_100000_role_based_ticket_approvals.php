<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approvals follow roles, not people: a service lists roles in order
 * (direct manager, HR manager…) and each ticket maps them to employees.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Replaced by role-based steps.
        Schema::dropIfExists('ticket_approval_rules');

        // "Direct manager" = the manager of the requester's department.
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('manager_employee_id')->nullable()->after('name')->constrained('employees')->nullOnDelete();
        });

        Schema::create('approval_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // The person who holds the role in this company.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Ordered steps, e.g. ["direct_manager", "role:3"]; null = inherit from the parent level.
        foreach (['ticket_categories', 'ticket_subcategories', 'ticket_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('approval_steps')->nullable()->after('form_fields'));
        }

        // Which step an approval came from, as shown to people ("HR Manager").
        Schema::table('ticket_approvals', fn (Blueprint $t) => $t->string('role')->nullable()->after('requested_by_user_id'));
    }

    public function down(): void
    {
        Schema::table('ticket_approvals', fn (Blueprint $t) => $t->dropColumn('role'));
        foreach (['ticket_categories', 'ticket_subcategories', 'ticket_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('approval_steps'));
        }
        Schema::dropIfExists('approval_roles');
        Schema::table('departments', fn (Blueprint $t) => $t->dropConstrainedForeignId('manager_employee_id'));
    }
};
