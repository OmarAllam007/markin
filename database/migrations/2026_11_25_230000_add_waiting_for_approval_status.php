<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Every company gets the automatic "Waiting for approval" status; requests already in review move to it. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('request_statuses')->distinct()->pluck('tenant_id') as $tenantId) {
            $id = DB::table('request_statuses')->where('tenant_id', $tenantId)->where('type', 'awaiting_approval')->value('id')
                ?? DB::table('request_statuses')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => DB::table('request_statuses')->where('tenant_id', $tenantId)->where('name', 'Waiting for approval')->exists() ? 'Waiting for approval (auto)' : 'Waiting for approval',
                    'type' => 'awaiting_approval',
                    'color' => '#b07d00',
                    'sort_order' => (int) DB::table('request_statuses')->where('tenant_id', $tenantId)->max('sort_order') + 1,
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $open = DB::table('request_statuses')->where('tenant_id', $tenantId)->whereIn('type', ['open', 'on_hold'])->pluck('id');
            DB::table('tickets')->where('tenant_id', $tenantId)->where('status', 'in_review')->whereIn('request_status_id', $open)
                ->update(['request_status_id' => $id, 'clock_running_since' => null, 'paused_at' => DB::raw('COALESCE(paused_at, CURRENT_TIMESTAMP)')]);
        }
    }

    public function down(): void
    {
        // Statuses stay: tickets may point at them.
    }
};
