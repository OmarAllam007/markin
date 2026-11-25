<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Working window per weekday for SLAs marked "business hours only":
            // {"0": ["08:00", "17:00"], …, "5": null} — keys are 0 = Sunday … 6 = Saturday, null = closed.
            $table->json('business_hours')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn('business_hours'));
    }
};
