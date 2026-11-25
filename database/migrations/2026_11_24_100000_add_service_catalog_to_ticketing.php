<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('ticket_subcategories')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('ticket_groups', function (Blueprint $table) {
            $table->string('type', 30)->default('technician')->after('name');
        });

        Schema::create('ticket_group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('ticket_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });

        // Business rule + form per service-hierarchy node. Resolution walks
        // item → subcategory → category and takes the first non-null value.
        foreach (['ticket_categories', 'ticket_subcategories', 'ticket_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('group_id')->nullable()->constrained('ticket_groups')->nullOnDelete();
                $t->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('sla_id')->nullable()->constrained('ticket_slas')->nullOnDelete();
                $t->json('form_fields')->nullable();
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('subcategory_id')->constrained('ticket_items')->nullOnDelete();
            $table->text('description')->nullable()->change();
        });

        // Superseded by per-node form_fields designed in the service catalog.
        Schema::dropIfExists('ticket_type_custom_fields');
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
        });

        foreach (['ticket_items', 'ticket_subcategories', 'ticket_categories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('group_id');
                $t->dropConstrainedForeignId('technician_id');
                $t->dropConstrainedForeignId('sla_id');
                $t->dropColumn('form_fields');
            });
        }

        Schema::dropIfExists('ticket_group_user');
        Schema::table('ticket_groups', fn (Blueprint $t) => $t->dropColumn('type'));
        Schema::dropIfExists('ticket_items');
    }
};
