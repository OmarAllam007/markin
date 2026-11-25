<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The department manager lives on the department only: one place to change it for everyone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropConstrainedForeignId('department_manager_id'));
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->foreignId('department_manager_id')->nullable()->after('direct_manager_id')->constrained('employees')->nullOnDelete());
    }
};
