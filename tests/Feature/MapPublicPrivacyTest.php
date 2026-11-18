<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Incident;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MapPublicPrivacyTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Este teste cria um schema temporário e só corre em SQLite :memory:.');
        }

        Schema::dropIfExists('incidents');
        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address_detail')->nullable();
            $table->string('neighborhood')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('police_station_id')->nullable();
            $table->dateTime('incident_date')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->category = Category::create([
            'name' => 'Teste',
            'slug' => 'teste',
            'color' => '#115cb9',
        ]);
    }

    public function test_heatmap_publico_nao_devolve_ocorrencias_privadas(): void
    {
        $public = $this->incident(true, 'PUBLIC-001', -25.9692, 32.4539);
        $private = $this->incident(false, 'PRIVATE-001', -25.9700, 32.4600);

        $response = $this->getJson('/api/map/heatmap?is_public=0&private=1');

        $response->assertOk()->assertJsonCount(1);

        $points = $response->json();
        $this->assertSame([
            'lat' => (float) $public->latitude,
            'lng' => (float) $public->longitude,
            'intensity' => 0.5,
        ], $points[0]);
        $this->assertNotSame((float) $private->latitude, $points[0]['lat']);
    }

    public function test_incidents_publico_nao_devolve_ocorrencias_privadas(): void
    {
        $public = $this->incident(true, 'PUBLIC-002', -25.9692, 32.4539);
        $private = $this->incident(false, 'PRIVATE-002', -25.9700, 32.4600);

        $response = $this->getJson('/api/map/incidents?is_public=0&private=1');

        $response->assertOk()->assertJsonCount(1, 'features');

        $ids = collect($response->json('features'))->pluck('properties.id')->all();
        $this->assertSame([$public->id], $ids);
        $this->assertNotContains($private->id, $ids);
    }

    private function incident(bool $isPublic, string $reference, float $latitude, float $longitude): Incident
    {
        return Incident::create([
            'reference_code' => $reference,
            'title' => $reference,
            'category_id' => $this->category->id,
            'priority' => 'medium',
            'status' => 'pending',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'incident_date' => now(),
            'is_public' => $isPublic,
        ]);
    }
}
