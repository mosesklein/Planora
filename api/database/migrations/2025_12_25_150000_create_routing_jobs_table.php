<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('uploaded');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_jobs');
    }
};
