<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province')->default('Maputo');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('boundary')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE districts ADD COLUMN geometry GEOMETRY(POLYGON, 4326)");
        DB::statement("CREATE INDEX idx_districts_geometry ON districts USING GIST (geometry)");
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
