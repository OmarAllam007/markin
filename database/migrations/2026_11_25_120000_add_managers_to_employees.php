<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Used by the "Direct manager" approval step.
            $table->foreignId('direct_manager_id')->nullable()->after('department_id')->constrained('employees')->nullOnDelete();
            // Used by "Department manager"; when empty, the department's own manager applies.
            $table->foreignId('department_manager_id')->nullable()->after('direct_manager_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direct_manager_id');
            $table->dropConstrainedForeignId('department_manager_id');
        });
    }
};
