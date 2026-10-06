<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('boundary')->nullable();
            $table->timestamps();

            $table->index('district_id');
        });

        DB::statement("ALTER TABLE neighborhoods ADD COLUMN geometry GEOMETRY(POLYGON, 4326)");
        DB::statement("CREATE INDEX idx_neighborhoods_geometry ON neighborhoods USING GIST (geometry)");
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
