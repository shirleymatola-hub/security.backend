<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('police_station_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('source_document', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('police_station_id');
            $table->index('is_active');
        });

        DB::statement("ALTER TABLE jurisdictions ADD COLUMN geometry GEOMETRY(MULTIPOLYGON, 4326)");
        DB::statement("CREATE INDEX idx_jurisdictions_geometry ON jurisdictions USING GIST (geometry)");
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdictions');
    }
};
