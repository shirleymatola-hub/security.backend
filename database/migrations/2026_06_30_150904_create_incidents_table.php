<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'investigating', 'resolved', 'archived'])->default('pending');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address_detail')->nullable();
            $table->string('neighborhood')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('police_station_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('incident_date')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('status');
            $table->index('priority');
            $table->index('reported_by');
            $table->index('assigned_to');
            $table->index('police_station_id');
        });

        DB::statement("ALTER TABLE incidents ADD COLUMN geometry GEOMETRY(POINT, 4326)");
        DB::statement("CREATE INDEX idx_incidents_geometry ON incidents USING GIST (geometry)");
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
