<?php

namespace Tests\Feature;

use App\Models\SuperAdminUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class SuperAdminPdfExportTest extends TestCase
{
    private const EXPORTS = [
        'stations' => 'postos-policiais.pdf',
        'districts' => 'distritos.pdf',
        'neighborhoods' => 'bairros.pdf',
        'users' => 'utilizadores-operacionais.pdf',
        'citizens' => 'cidadaos.pdf',
        'incidents' => 'ocorrencias.pdf',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requer PostgreSQL/PostGIS com smp_test.');
        }

        if (! Schema::hasTable('super_admin_users')) {
            $this->markTestSkipped('Tabela super_admin_users indisponível.');
        }
    }

    public function test_csv_export_continues_to_work_with_super_admin_session(): void
    {
        $response = $this->actingAs($this->activeSuperAdmin(), 'super_admin')
            ->get('/api/super-admin/export/stations/csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('postos-policiais.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Nº;ID;Código;Nome', $content);
    }

    public function test_xlsx_export_continues_to_work_with_super_admin_session(): void
    {
        $response = $this->actingAs($this->activeSuperAdmin(), 'super_admin')
            ->get('/api/super-admin/export/stations/excel');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('postos-policiais.xlsx', (string) $response->headers->get('Content-Disposition'));

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $file = $baseResponse->getFile();
        $this->assertNotNull($file);
        $this->assertGreaterThan(100, $file->getSize());
        $this->assertStringStartsWith('PK', (string) file_get_contents($file->getPathname()));
    }

    public function test_all_pdf_exports_return_valid_documents_with_super_admin_session(): void
    {
        foreach (self::EXPORTS as $type => $filename) {
            $response = $this->actingAs($this->activeSuperAdmin(), 'super_admin')
                ->get("/api/super-admin/export/{$type}/pdf");

            $this->assertValidPdf($response, $filename);
        }
    }

    public function test_generate_report_returns_valid_pdf_with_super_admin_session(): void
    {
        $response = $this->actingAs($this->activeSuperAdmin(), 'super_admin')
            ->get('/api/super-admin/reports/generate');

        $pdf = $this->assertValidPdf($response, 'relatorio-administrativo-smp.pdf');
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    public function test_pdf_exports_require_a_super_admin_session(): void
    {
        $response = $this->getJson('/api/super-admin/export/stations/pdf');
        $response->assertUnauthorized();
        $this->assertStringNotContainsString('cache path', strtolower((string) json_encode($response->json())));
        $this->assertStringNotContainsString('invalidargumentexception', strtolower((string) json_encode($response->json())));

        $user = User::where('status', 'active')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/super-admin/export/stations/pdf');
        $response->assertUnauthorized();
    }

    public function test_pdf_views_render_the_exported_data(): void
    {
        $exportHtml = view('super-admin.exports.pdf-table', [
            'title' => 'Postos Policiais',
            'headers' => ['Nº', 'ID', 'Nome'],
            'rows' => [[1, 10, 'Matola A']],
            'generatedAt' => '18/11/2026 12:00',
        ])->render();

        $this->assertStringContainsString('Postos Policiais', $exportHtml);
        $this->assertStringContainsString('Nº', $exportHtml);
        $this->assertStringContainsString('Matola A', $exportHtml);

        $reportHtml = view('super-admin.reports.admin-report', [
            'stats' => [
                'total_stations' => 14,
                'active_stations' => 13,
                'inactive_stations' => 1,
                'total_officers' => 40,
                'total_districts' => 1,
                'total_users' => 14,
                'total_incidents' => 165,
                'stations_by_district' => ['Matola' => 14],
                'roles' => ['manager' => 1],
                'incidents_by_status' => ['Resolvido' => 10],
                'incidents_by_priority' => ['Alta' => 5],
                'incidents_by_category' => ['Segurança' => 3],
            ],
            'generatedAt' => '18/11/2026 12:00:00',
        ])->render();

        $this->assertStringContainsString('Relatório Administrativo', $reportHtml);
        $this->assertStringContainsString('165', $reportHtml);
        $this->assertStringContainsString('Matola', $reportHtml);
    }

    private function activeSuperAdmin(): SuperAdminUser
    {
        $admin = SuperAdminUser::where('status', 'active')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    private function assertValidPdf(TestResponse $response, string $filename): string
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString($filename, $disposition);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertGreaterThan(1500, strlen($content));
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', substr($content, -2048));
        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $content);

        return $content;
    }
}
