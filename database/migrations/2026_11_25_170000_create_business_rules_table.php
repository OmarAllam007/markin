<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assignment rules, checked in order when a request is submitted; the first match wins.
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // all = every condition must hold; any = one is enough.
            $table->string('match', 3)->default('all');
            // [{ field, operator: in|not_in, values: [...] }]
            $table->json('conditions');
            $table->foreignId('group_id')->constrained('ticket_groups')->cascadeOnDelete();
            // Empty = the least busy member of the group.
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rules');
    }
};
