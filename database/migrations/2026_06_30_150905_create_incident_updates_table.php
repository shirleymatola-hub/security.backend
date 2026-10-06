<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'investigating', 'resolved', 'archived'])->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index('incident_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_updates');
    }
};
