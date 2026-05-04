<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }

        if ($this->indexExists('users', 'users_tenant_id_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'email']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        if (! $this->indexExists('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['email']);
            });
        }

        if ($this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }

        if (! $this->indexExists('users', 'users_tenant_id_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['tenant_id', 'email']);
            });
        }

        if (! $this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['name'] === $constraint) {
                return true;
            }
        }

        return false;
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
