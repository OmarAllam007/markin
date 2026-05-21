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
        Schema::create('zk_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zk_machine_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->dateTime('punched_at');
            $table->tinyInteger('punch_status');
            $table->tinyInteger('verify_type')->default(0);
            $table->text('raw_line');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['zk_machine_id', 'processed_at']);
            $table->index(['employee_code', 'punched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zk_raw_logs');
    }
};
