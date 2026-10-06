<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('police_station_id')->nullable()->constrained('police_stations')->nullOnDelete();
            $table->string('status')->default('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['police_station_id']);
            $table->dropColumn([
                'phone',
                'avatar',
                'is_anonymous',
                'police_station_id',
                'status',
            ]);
        });
    }
};
