<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('unidade_policial_id');
            $table->bigInteger('area_estudo_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('source_document', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE unit_jurisdictions ADD CONSTRAINT fk_unit_jurisdictions_unidade_policial
            FOREIGN KEY (unidade_policial_id) REFERENCES "unidades policiais"(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE unit_jurisdictions ADD CONSTRAINT fk_unit_jurisdictions_area_estudo
            FOREIGN KEY (area_estudo_id) REFERENCES "Area de Estudo"(id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE unit_jurisdictions ADD COLUMN geometry GEOMETRY(MULTIPOLYGON, 4326)');

        DB::statement('CREATE INDEX idx_unit_jurisdictions_unidade_policial_id ON unit_jurisdictions (unidade_policial_id)');
        DB::statement('CREATE INDEX idx_unit_jurisdictions_area_estudo_id ON unit_jurisdictions (area_estudo_id)');
        DB::statement('CREATE INDEX idx_unit_jurisdictions_is_active ON unit_jurisdictions (is_active)');
        DB::statement('CREATE INDEX idx_unit_jurisdictions_geometry ON unit_jurisdictions USING GIST (geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_jurisdictions');
    }
};
