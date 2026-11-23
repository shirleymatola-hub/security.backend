<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_assign_nearest_station ON public.incidents');
    }

    public function down(): void
    {
        DB::statement('
            CREATE TRIGGER trigger_assign_nearest_station
                BEFORE INSERT ON public.incidents
                FOR EACH ROW
                EXECUTE FUNCTION assign_nearest_station()
        ');
    }
};
