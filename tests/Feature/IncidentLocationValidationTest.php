<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncidentLocationValidationTest extends TestCase
{
    public function test_authenticated_incident_store_rejects_missing_and_invalid_coordinates(): void
    {
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('Não existe schema de utilizadores para validar o fluxo autenticado.');
        }

        $user = User::whereHas('roles', static function ($query): void {
            $query->where('name', 'citizen');
        })->first();

        if ($user === null) {
            $this->markTestSkipped('Não existe utilizador com a função citizen para validar o fluxo autenticado.');
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/citizen/incidents', [
            'description' => 'Validação de coordenadas sem escrita na base de dados.',
            'latitude' => '',
            'longitude' => 200,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude'])
            ->assertJsonFragment(['A latitude é obrigatória.'])
            ->assertJsonFragment(['A longitude deve estar entre -180 e 180.']);
    }

    public function test_anonymous_report_store_rejects_missing_and_invalid_coordinates(): void
    {
        $response = $this->postJson('/api/public/anonymous-reports', [
            'description' => 'Validação de coordenadas sem escrita na base de dados.',
            'latitude' => 'norte',
            'longitude' => null,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude'])
            ->assertJsonFragment(['A latitude deve ser um número válido.'])
            ->assertJsonFragment(['A longitude é obrigatória.']);
    }
}
