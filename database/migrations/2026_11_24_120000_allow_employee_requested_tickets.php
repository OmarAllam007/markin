<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tickets raised from the mobile app have an employee, not a web user, as requester. */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable()->change();
            $table->foreignId('creator_id')->nullable()->change();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable(false)->change();
            $table->foreignId('creator_id')->nullable(false)->change();
        });
    }
};
