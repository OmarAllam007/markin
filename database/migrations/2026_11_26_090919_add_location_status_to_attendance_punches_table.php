<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->string('location_status')->default('unknown')->index();
            $table->unsignedInteger('distance_from_location_m')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropColumn(['location_status', 'distance_from_location_m']);
        });
    }
};
