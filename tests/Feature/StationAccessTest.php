<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StationAccessTest extends TestCase
{
    /** @var array<int, User> */
    private array $users = [];

    private bool $prepared = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requer PostgreSQL/PostGIS.');
        }

        if (DB::getDatabaseName() !== 'smp_55a_descartavel_rw'
            || getenv('SMP_TEST_DISPOSABLE_DB') !== '1'
            || ! Schema::hasTable('smp_55a_disposable_marker')) {
            $this->markTestSkipped('Requer a base descartável smp_55a_descartavel_rw explicitamente autorizada.');
        }

        DB::table('districts')->insertOrIgnore([
            'id' => 99002,
            'name' => 'Distrito Teste 55A',
            'province' => 'Maputo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->prepared = true;
    }

    protected function tearDown(): void
    {
        if (! $this->prepared) {
            parent::tearDown();

            return;
        }

        $ids = array_map(static fn (User $user): int => (int) $user->id, $this->users);

        if ($ids !== []) {
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
            DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
        }

        DB::table('districts')->where('id', 99002)->delete();

        parent::tearDown();
    }

    public function test_agente_ve_apenas_a_propria_esquadra(): void
    {
        $user = $this->makeUser('police', 2, 1);

        $this->actingAs($user, 'sanctum')->getJson('/api/map/station/2')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/map/station/10')->assertForbidden();
    }

    public function test_comandante_distrital_ve_apenas_esquadras_do_seu_distrito(): void
    {
        $fromDistrict = $this->makeUser('district_commander', 1, 1);
        $fromOtherDistrict = $this->makeUser('district_commander', 1, 99002);

        $this->actingAs($fromDistrict, 'sanctum')->getJson('/api/map/station/2')->assertOk();
        $this->actingAs($fromOtherDistrict, 'sanctum')->getJson('/api/map/station/2')->assertForbidden();
    }

    public function test_admin_pode_consultar_qualquer_esquadra(): void
    {
        $user = $this->makeUser('admin', 1, 1);

        $this->actingAs($user, 'sanctum')->getJson('/api/map/station/10')->assertOk();
    }

    private function makeUser(string $role, int $stationId, int $districtId): User
    {
        $user = User::create([
            'name' => 'Teste '.$role,
            'email' => uniqid('station-access-', true).'@example.test',
            'password' => Hash::make('password'),
            'police_station_id' => $stationId,
            'district_id' => $districtId,
            'status' => 'active',
        ]);

        $user->assignRole($role);
        $this->users[] = $user;

        return $user;
    }
}
