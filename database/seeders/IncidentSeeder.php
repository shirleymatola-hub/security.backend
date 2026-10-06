<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Neighborhood;
use Illuminate\Support\Facades\DB;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');
        $stations = PoliceStation::pluck('id');
        $policeStationId = $stations->first();

        $carlos = User::where('email', 'gestor@matolac.mz')->first();
        $miguel = User::where('email', 'agente@matolac.mz')->first();
        $maria = User::where('email', 'cidadao@email.mz')->first();

        $neighborhoods = Neighborhood::all();

        $incidents = [
            // === PENDING ===
            [
                'title' => 'Roubo a residencia',
                'description' => 'Dois individuos armados entraram na residencia e subtrairam eletronicos e numerario.',
                'category' => 'Roubo',
                'priority' => 'high',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 2,
            ],
            [
                'title' => 'Furto de bicicleta',
                'description' => 'Bicicleta foi subtraida de frente a residencia durante a noite.',
                'category' => 'Furto',
                'priority' => 'low',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => null,
                'is_anonymous' => true,
                'is_public' => true,
                'days_ago' => 1,
            ],
            [
                'title' => 'Vandalismo em parque infantil',
                'description' => 'Brinquedos do parque foram destruidos e pichacoes feitas nas paredes.',
                'category' => 'Vandalismo',
                'priority' => 'medium',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => null,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 3,
            ],
            [
                'title' => 'Pessoa desaparecida - adolescente',
                'description' => 'Adolescente de 15 anos nao retornou a residencia ha dois dias.',
                'category' => 'Pessoa Desaparecida',
                'priority' => 'high',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 2,
            ],
            [
                'title' => 'Denuncia de trafico',
                'description' => 'Movimentacao suspeita de veiculos na area durante a madrugada.',
                'category' => 'Tráfico de Drogas',
                'priority' => 'high',
                'status' => 'pending',
                'reported_by' => null,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => true,
                'is_public' => false,
                'days_ago' => 1,
            ],
            [
                'title' => 'Incendio em lixao irregular',
                'description' => 'Fogo nao controlado em area de dumping irregular, gerando fumaca densa.',
                'category' => 'Incêndio',
                'priority' => 'medium',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => null,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 4,
            ],

            // === INVESTIGATING ===
            [
                'title' => 'Agressao fisica em bar',
                'description' => 'Briga entre dois clientes resultou em ferimentos leves. Suspeitos identificados.',
                'category' => 'Agressão Física',
                'priority' => 'medium',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 5,
            ],
            [
                'title' => 'Acidente de viacao na EN1',
                'description' => 'Colisao frontal entre dois veiculos na Estrada Nacional 1. Tres feridos levados ao Hospital Central.',
                'category' => 'Acidente de Viação',
                'priority' => 'high',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 7,
            ],
            [
                'title' => 'Violencia domestica reportada',
                'description' => 'Vitima procurou delegacia relatando agressoes fisicas do conjuge. Medida de protecao emitida.',
                'category' => 'Violência Doméstica',
                'priority' => 'high',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => false,
                'days_ago' => 10,
            ],
            [
                'title' => 'Fraude bancaria - golpe do investimento',
                'description' => 'Vitima transferiu MZN 45,000 para conta fraudulena prometendo retorno de 300%.',
                'category' => 'Fraude',
                'priority' => 'medium',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 12,
            ],
            [
                'title' => 'Perturbacao da ordem publica em concentracao politica',
                'description' => 'Manifestacao tornou-se desordeira com quebra de vidracas em estabelecimentos proximos.',
                'category' => 'Perturbação da Ordem Pública',
                'priority' => 'medium',
                'status' => 'investigating',
                'reported_by' => $carlos?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 8,
            ],
            [
                'title' => 'Roubo a loja de conveniencia',
                'description' => 'Dois assaltantes subtrairam o caixa e celulares dos clientes.',
                'category' => 'Roubo',
                'priority' => 'high',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 15,
            ],

            // === RESOLVED ===
            [
                'title' => 'Furto de veiculo',
                'description' => 'Veiculo foi recuperado 48 horas depois na zona de Machava. Suspeito preso.',
                'category' => 'Furto',
                'priority' => 'medium',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 20,
                'resolved_days_ago' => 18,
            ],
            [
                'title' => 'Incendio residencial',
                'description' => 'Curto-circuito causou fogo em quarto da residencia. Bombeiros controlaram o sinistro.',
                'category' => 'Incêndio',
                'priority' => 'high',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 25,
                'resolved_days_ago' => 24,
            ],
            [
                'title' => 'Agressao fisica entre vizinhos',
                'description' => 'Conflito por terreno entre vizinhos resultou em agressoes. Mediacoes realizadas com sucesso.',
                'category' => 'Agressão Física',
                'priority' => 'low',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 14,
                'resolved_days_ago' => 10,
            ],
            [
                'title' => 'Vandalismo em estacao de autobus',
                'description' => 'Pichacoes e danos materiais na estacao. Autores identificados e obrigados a reparar.',
                'category' => 'Vandalismo',
                'priority' => 'low',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 30,
                'resolved_days_ago' => 27,
            ],
            [
                'title' => 'Fraude de cartao de credito',
                'description' => 'Transacoes nao autorizadas detectadas em conta de vitima. Banco reembolsou o valor.',
                'category' => 'Fraude',
                'priority' => 'medium',
                'status' => 'resolved',
                'reported_by' => $carlos?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 35,
                'resolved_days_ago' => 30,
            ],
            [
                'title' => 'Acidente de viacao leve no cruzamento',
                'description' => 'Colisao lateral sem feridos graves. Relatorio policial emitido para seguro.',
                'category' => 'Acidente de Viação',
                'priority' => 'low',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 18,
                'resolved_days_ago' => 17,
            ],
            [
                'title' => 'Violacao de domicilio - tentativa',
                'description' => 'Suspeito tentou entrar na residencia mas foi impedido por vizinhos. Investigacao concluida.',
                'category' => 'Violação',
                'priority' => 'high',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => false,
                'days_ago' => 40,
                'resolved_days_ago' => 35,
            ],
            [
                'title' => 'Perturbacao de ordem publica - barulho excessivo',
                'description' => 'Festa vizinha com som alto atras das 22h. Aviso formal aplicado aos responsaveis.',
                'category' => 'Perturbação da Ordem Pública',
                'priority' => 'low',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => true,
                'is_public' => true,
                'days_ago' => 12,
                'resolved_days_ago' => 11,
            ],

            // === ARCHIVED ===
            [
                'title' => 'Roubo a caixa eletronico',
                'description' => 'Tentativa de arrombamento de ATM. Disparou alarme e suspeitos fugiram.',
                'category' => 'Roubo',
                'priority' => 'high',
                'status' => 'archived',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 60,
                'resolved_days_ago' => 55,
            ],
            [
                'title' => 'Desastre natural - inundacao',
                'description' => 'Chuvas fortes causaram inundacao em varias residencias. Operacao de resgate concluida.',
                'category' => 'Desastre Natural',
                'priority' => 'urgent',
                'status' => 'archived',
                'reported_by' => $carlos?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 90,
                'resolved_days_ago' => 85,
            ],
            [
                'title' => 'Homicidio',
                'description' => 'Corpo encontrado em terreno devoluto. Caso arquivado apos investigacao completa.',
                'category' => 'Homicídio',
                'priority' => 'urgent',
                'status' => 'archived',
                'reported_by' => null,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => false,
                'days_ago' => 120,
                'resolved_days_ago' => 100,
            ],
            [
                'title' => 'Furto de material de construcao',
                'description' => 'Tijolos e cimento subtraidos de obra. Caso encerrado sem identificacao de suspeitos.',
                'category' => 'Furto',
                'priority' => 'low',
                'status' => 'archived',
                'reported_by' => $maria?->id,
                'assigned_to' => null,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 75,
                'resolved_days_ago' => 70,
            ],
            [
                'title' => 'Incendio em veiculo estacionado',
                'description' => 'Veiculo incendiado na via publica. Possivel vandalismo, porem sem suspeitos.',
                'category' => 'Incêndio',
                'priority' => 'medium',
                'status' => 'archived',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 45,
                'resolved_days_ago' => 40,
            ],

            // === MORE PENDING (for distribution) ===
            [
                'title' => 'Tentativa de roubo a posto de combustivel',
                'description' => 'Dois individuos tentaram assaltar o posto mas foram impedidos pela equipa de seguranca.',
                'category' => 'Roubo',
                'priority' => 'high',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 0,
            ],
            [
                'title' => 'Objetos encontrados suspeitos',
                'description' => 'Saco contendo materiais eletronicos abandonado proximo a parada de autocarro.',
                'category' => 'Outro',
                'priority' => 'medium',
                'status' => 'pending',
                'reported_by' => $maria?->id,
                'assigned_to' => null,
                'is_anonymous' => true,
                'is_public' => true,
                'days_ago' => 1,
            ],
            [
                'title' => 'Agressao fisica a motorista de chapas',
                'description' => 'Passageiro agrediu motorista de chapas por disputa de tarifa.',
                'category' => 'Agressão Física',
                'priority' => 'medium',
                'status' => 'investigating',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 3,
            ],
            [
                'title' => 'Trafico de drogas',
                'description' => 'Equipamento policial apreendeu substancias entorpecentes em residencia suspeita.',
                'category' => 'Tráfico de Drogas',
                'priority' => 'high',
                'status' => 'investigating',
                'reported_by' => $carlos?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => false,
                'days_ago' => 6,
            ],
            [
                'title' => 'Incendio em contents',
                'description' => 'Fogo deflagrado em armazem de plasticos. Bombeiros controlaram sem feridos.',
                'category' => 'Incêndio',
                'priority' => 'medium',
                'status' => 'resolved',
                'reported_by' => $maria?->id,
                'assigned_to' => $miguel?->id,
                'is_anonymous' => false,
                'is_public' => true,
                'days_ago' => 16,
                'resolved_days_ago' => 15,
            ],
        ];

        foreach ($incidents as $data) {
            $categoryName = $data['category'];
            $categoryId = $categories->get($categoryName);
            if (!$categoryId) {
                continue;
            }

            $neighborhood = $neighborhoods->random();

            $point = DB::selectOne("
                SELECT
                    ST_Y(point) AS lat,
                    ST_X(point) AS lng
                FROM (
                    SELECT ST_PointOnSurface(geometry) AS point
                    FROM neighborhoods
                    WHERE id = ?
                ) AS t
            ", [$neighborhood->id]);

            if (!$point) {
                $point = DB::selectOne("
                    SELECT
                        ST_Y(ST_Centroid(geometry)) AS lat,
                        ST_X(ST_Centroid(geometry)) AS lng
                    FROM neighborhoods
                    WHERE id = ?
                ", [$neighborhood->id]);
            }

            $incidentDate = now()->subDays($data['days_ago'])->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $resolvedAt = null;
            if ($data['status'] === 'resolved' && isset($data['resolved_days_ago'])) {
                $resolvedAt = now()->subDays($data['resolved_days_ago']);
            } elseif ($data['status'] === 'archived' && isset($data['resolved_days_ago'])) {
                $resolvedAt = now()->subDays($data['resolved_days_ago']);
            }

            Incident::create([
                'reference_code' => Incident::generateReferenceCode(),
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $categoryId,
                'priority' => $data['priority'],
                'status' => $data['status'],
                'latitude' => $point->lat,
                'longitude' => $point->lng,
                'address_detail' => null,
                'neighborhood' => $neighborhood->name,
                'reported_by' => $data['reported_by'],
                'assigned_to' => $data['assigned_to'],
                'police_station_id' => $policeStationId,
                'incident_date' => $incidentDate,
                'resolved_at' => $resolvedAt,
                'is_anonymous' => $data['is_anonymous'],
                'is_public' => $data['is_public'],
                'created_at' => $incidentDate,
                'updated_at' => $incidentDate,
            ]);
        }
    }
}
