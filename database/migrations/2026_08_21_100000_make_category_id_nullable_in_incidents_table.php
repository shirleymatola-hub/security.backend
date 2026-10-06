<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE incidents ALTER COLUMN category_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE incidents SET category_id = (SELECT id FROM categories LIMIT 1) WHERE category_id IS NULL');
        DB::statement('ALTER TABLE incidents ALTER COLUMN category_id SET NOT NULL');
    }
};
