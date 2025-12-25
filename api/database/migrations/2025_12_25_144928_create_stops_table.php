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
    Schema::create('stops', function (Blueprint $table) {
        $table->id();
        // Links this stop to a specific company
        $table->foreignId('company_id')->constrained()->onDelete('cascade');
        $table->string('name');            // Name of the stop (e.g., 'Main & 5th')
        $table->decimal('lat', 10, 8);     // Precise Latitude
        $table->decimal('lng', 11, 8);     // Precise Longitude
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};
