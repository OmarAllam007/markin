<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_admin', 'is_supervisor', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete()->after('current_tenant_id');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_supervisor')->default(false);
            $table->string('status', 20)->default(UserStatus::Active->value);
        });
    }
};
