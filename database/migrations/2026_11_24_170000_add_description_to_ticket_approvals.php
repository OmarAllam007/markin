<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_approvals', function (Blueprint $table) {
            // What the requester asks the approver to look at; `comments` is the approver's reply.
            $table->text('description')->nullable()->after('requested_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_approvals', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
