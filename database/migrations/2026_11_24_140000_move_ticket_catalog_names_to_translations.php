<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['ticket_categories', 'ticket_subcategories', 'ticket_items'];

    /** name_ar → name_translations {"ar": …}, so names support any content locale. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('name_translations')->nullable()->after('name'));

            DB::table($table)->whereNotNull('name_ar')->where('name_ar', '!=', '')->orderBy('id')
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)
                    ->update(['name_translations' => json_encode(['ar' => $row->name_ar], JSON_UNESCAPED_UNICODE)]));

            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_ar'));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('name_ar')->nullable()->after('name'));

            DB::table($table)->whereNotNull('name_translations')->orderBy('id')
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)
                    ->update(['name_ar' => json_decode($row->name_translations, true)['ar'] ?? null]));

            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_translations'));
        }
    }
};
