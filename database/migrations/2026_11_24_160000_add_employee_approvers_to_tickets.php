<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_approvals', function (Blueprint $table) {
            $table->foreignId('approver_user_id')->nullable()->change();
            // Approvals are requested from employees, who answer from the emailed link.
            $table->foreignId('approver_employee_id')->nullable()->after('approver_user_id')->constrained('employees')->cascadeOnDelete();
            // Null on an employee approval = added automatically by an approval rule.
            $table->foreignId('requested_by_user_id')->nullable()->after('approver_employee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable()->after('delegated_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_employee_id');
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn('notified_at');
        });
    }
};
