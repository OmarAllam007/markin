<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The resolution clock, kept by status changes rather than a counter job:
 * elapsed = elapsed_minutes + (now - clock_running_since) while the clock runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Minutes the clock ran in finished stretches (open-type statuses only).
            $table->unsignedInteger('elapsed_minutes')->default(0)->after('time_spent');
            // Set while the status is open-type; null when on hold or finished.
            $table->timestamp('clock_running_since')->nullable()->after('elapsed_minutes');
            // Set while on hold, so the due date can move by the paused time on resume.
            $table->timestamp('paused_at')->nullable()->after('clock_running_since');

            $table->index(['clock_running_since', 'overdue']);
        });

        // Existing requests: open ones count from when they were created, on-hold ones are paused now.
        $types = DB::table('request_statuses')->pluck('type', 'id');
        DB::table('tickets')->whereNotNull('request_status_id')->orderBy('id')->each(function ($t) use ($types) {
            $type = $types[$t->request_status_id] ?? null;
            DB::table('tickets')->where('id', $t->id)->update([
                'clock_running_since' => $type === 'open' ? $t->created_at : null,
                'paused_at' => $type === 'on_hold' ? now() : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['clock_running_since', 'overdue']);
            $table->dropColumn(['elapsed_minutes', 'clock_running_since', 'paused_at']);
        });
    }
};
