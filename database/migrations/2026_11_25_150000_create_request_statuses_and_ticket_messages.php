<?php

use App\Models\RequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each company's request statuses. The type says what the status means for timing:
        // open = clock runs, on_hold = paused, resolved / closed / cancelled = stopped.
        Schema::create('request_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('name_translations')->nullable();
            $table->string('type', 20);
            $table->string('color', 20)->default('#7e8299');
            $table->unsignedSmallInteger('sort_order')->default(0);
            // New requests start here.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // The work status; `status` keeps the approval state (in review / approved / rejected).
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('request_status_id')->nullable()->after('status')->constrained('request_statuses')->nullOnDelete();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Author: a web user or an employee from the mobile app.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            // Which side wrote it, so the thread and the email go the right way.
            $table->boolean('from_requester');
            $table->text('body');
            // Status the request was moved to with this message.
            $table->foreignId('request_status_id')->nullable()->constrained('request_statuses')->nullOnDelete();
            // Employee ids copied on the email.
            $table->json('cc')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        // Existing companies get the default statuses; their open tickets start on the default one.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (RequestStatus::DEFAULTS as $i => [$name, $type, $color, $default]) {
                DB::table('request_statuses')->insert([
                    'tenant_id' => $tenantId, 'name' => $name, 'type' => $type, 'color' => $color,
                    'sort_order' => $i, 'is_default' => $default, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('tickets')->where('tenant_id', $tenantId)->update([
                'request_status_id' => DB::table('request_statuses')->where('tenant_id', $tenantId)->where('is_default', true)->value('id'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::table('tickets', fn (Blueprint $table) => $table->dropConstrainedForeignId('request_status_id'));
        Schema::dropIfExists('request_statuses');
    }
};
