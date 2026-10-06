<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 3ª Esquadra - PRM (OSM node 12128525998)
        // Real location near campinho, Assembleia de Deus, Fomento area
        // Geometry auto-updated by trigger_update_police_station_geometry
        DB::table('police_stations')
            ->whereRaw("name LIKE '%3%' AND name LIKE '%Esquadra%' AND name LIKE '%PRM%'")
            ->update([
                'latitude'  => -25.9263421,
                'longitude' => 32.4818772,
            ]);

        // Fallback: match Portuguese ordinal "terceira"
        DB::table('police_stations')
            ->whereRaw("name LIKE '%terceira%' AND name LIKE '%esquadra%'")
            ->update([
                'latitude'  => -25.9263421,
                'longitude' => 32.4818772,
            ]);
    }

    public function down(): void
    {
        // Reverse is not practical without a backup; leave as-is.
    }
};
