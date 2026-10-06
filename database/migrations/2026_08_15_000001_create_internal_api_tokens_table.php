<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_name');
            $table->string('token_hash', 64);
            $table->json('abilities')->default(\DB::raw("'[\"users:read\"]'::jsonb"));
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('token_hash');
            $table->index('token_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_api_tokens');
    }
};
