<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routing_jobs', function (Blueprint $table) {
            $table->string('output_json_path')->nullable()->after('stored_path');
            $table->string('output_csv_path')->nullable()->after('output_json_path');
        });
    }

    public function down(): void
    {
        Schema::table('routing_jobs', function (Blueprint $table) {
            $table->dropColumn(['output_json_path', 'output_csv_path']);
        });
    }
};
