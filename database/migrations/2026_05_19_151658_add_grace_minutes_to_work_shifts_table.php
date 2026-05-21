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
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_checkin_grace_minutes')->default(0)->after('allow_multiple_sessions');
            $table->unsignedSmallInteger('early_checkout_grace_minutes')->default(0)->after('late_checkin_grace_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn(['late_checkin_grace_minutes', 'early_checkout_grace_minutes']);
        });
    }
};
