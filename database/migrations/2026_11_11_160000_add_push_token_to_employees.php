<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // One device per employee is already enforced at login, so one token is enough.
            $table->string('push_token')->nullable()->after('device_name');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['push_token', 'push_token_updated_at']);
        });
    }
};
