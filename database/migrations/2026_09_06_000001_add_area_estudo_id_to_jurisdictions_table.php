<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->bigInteger('area_estudo_id')->nullable()->after('police_station_id');
        });

        DB::statement('ALTER TABLE jurisdictions ADD CONSTRAINT fk_jurisdictions_area_estudo 
            FOREIGN KEY (area_estudo_id) REFERENCES "Area de Estudo"(id) ON DELETE SET NULL');

        DB::statement('CREATE INDEX idx_jurisdictions_area_estudo_id ON jurisdictions (area_estudo_id)');
    }

    public function down(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->dropForeign(['area_estudo_id']);
            $table->dropIndex('idx_jurisdictions_area_estudo_id');
            $table->dropColumn('area_estudo_id');
        });
    }
};
