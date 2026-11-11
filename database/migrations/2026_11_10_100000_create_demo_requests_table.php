<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->unsignedInteger('number_of_employees')->nullable();
            $table->string('name');
            $table->string('country_code', 10);
            $table->string('mobile', 20);
            $table->string('email');
            $table->text('message')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
