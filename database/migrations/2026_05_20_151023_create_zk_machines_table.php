<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zk_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('name')->nullable();
            $table->string('secret_token', 64)->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('platform')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedBigInteger('last_attlog_stamp')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zk_machines');
    }
};
