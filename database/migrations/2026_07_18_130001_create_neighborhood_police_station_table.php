<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('neighborhood_police_station', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighborhood_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('police_station_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique([
                'neighborhood_id',
                'police_station_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neighborhood_police_station');
    }
};