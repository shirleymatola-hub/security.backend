<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório Administrativo - SMP</title>
    <style>
        @page {
            margin: 9mm 12mm 15mm 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.22;
            color: #1f2937;
            margin: 0;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        table {
            border-collapse: collapse;
        }

        .masthead {
            width: 100%;
            border-bottom: 2px solid #17365d;
            margin-bottom: 2mm;
            table-layout: fixed;
        }

        .masthead td {
            vertical-align: bottom;
            padding: 0 0 1.5mm;
        }

        .masthead .brand {
            width: 20%;
            color: #17365d;
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.05;
        }

        .masthead .brand span {
            display: block;
            font-size: 6.2pt;
            font-weight: normal;
            color: #64748b;
        }

        .masthead .title {
            width: 42%;
            padding-left: 2mm;
        }

        .masthead .title h1 {
            font-size: 14pt;
            color: #17365d;
            white-space: nowrap;
            letter-spacing: -0.2pt;
            line-height: 1;
        }

        .masthead .title span {
            font-size: 6.4pt;
            color: #64748b;
        }

        .masthead .meta {
            width: 38%;
            text-align: right;
            font-size: 8pt;
            line-height: 1.1;
            color: #475569;
        }

        .meta div {
            margin-bottom: 0.3mm;
        }

        .meta strong {
            color: #17365d;
        }

        .kpi-primary,
        .kpi-secondary {
            width: 100%;
            margin-bottom: 2mm;
            table-layout: fixed;
        }

        .kpi-primary td {
            width: 25%;
            height: 14mm;
            padding-right: 1.5mm;
            vertical-align: top;
        }

        .kpi-primary td:last-child,
        .kpi-secondary td:last-child {
            padding-right: 0;
        }

        .kpi {
            height: 14mm;
            border: 1px solid #d7dee8;
            border-left: 3px solid #17365d;
            background-color: #f8fafc;
            padding: 1.5mm 1.8mm 1.2mm;
            box-sizing: border-box;
        }

        .kpi-label {
            font-size: 7.6pt;
            text-transform: uppercase;
            letter-spacing: 0;
            white-space: nowrap;
            color: #64748b;
            line-height: 1.05;
        }

        .kpi-value {
            font-size: 15pt;
            font-weight: bold;
            color: #17365d;
            margin-top: 1mm;
            line-height: 1;
        }

        .kpi-secondary td {
            width: 25%;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 1mm 2mm;
            font-size: 6.8pt;
            color: #475569;
        }

        .kpi-secondary strong {
            color: #17365d;
        }

        .scope-note {
            border-left: 3px solid #17365d;
            background-color: #eef3f8;
            padding: 1.2mm 2mm;
            font-size: 6.6pt;
            color: #475569;
            margin-bottom: 2.5mm;
        }

        .section {
            margin-bottom: 3mm;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 9pt;
            color: #17365d;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 1.2mm;
            margin-bottom: 2mm;
        }

        .two-col {
            width: 100%;
        }

        .two-col td {
            width: 50%;
            vertical-align: top;
            padding-right: 2mm;
        }

        .two-col td:last-child {
            padding-right: 0;
            padding-left: 2mm;
        }

        .panel-stack > table {
            width: 100%;
            margin-bottom: 3mm;
        }

        .panel {
            border: 1px solid #d7dee8;
            padding: 2mm 2.5mm 2.5mm;
            page-break-inside: avoid;
        }

        .panel h3 {
            font-size: 7.8pt;
            color: #17365d;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1.2mm;
            margin-bottom: 1.5mm;
        }

        .data-table {
            width: 100%;
            table-layout: fixed;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            background-color: #17365d;
            color: #ffffff;
            font-size: 6.4pt;
            text-transform: uppercase;
            text-align: left;
            padding: 1.5mm 2mm;
        }

        .data-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.2mm 2mm;
            font-size: 7pt;
            vertical-align: top;
            word-wrap: break-word;
        }

        .data-table td.num {
            text-align: right;
            width: 24%;
            font-weight: bold;
            color: #17365d;
        }

        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .empty {
            text-align: center;
            color: #64748b;
        }

        .page-break {
            page-break-before: always;
            height: 0;
        }

        .category-table {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 4mm;
        }

        .category-table thead {
            display: table-header-group;
        }

        .category-table th {
            background-color: #17365d;
            color: #ffffff;
            font-size: 6.4pt;
            text-transform: uppercase;
            text-align: left;
            padding: 1.7mm 2mm;
        }

        .category-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.3mm 2mm;
            font-size: 7pt;
            vertical-align: top;
        }

        .category-table td.num {
            text-align: right;
            width: 14%;
            font-weight: bold;
            color: #17365d;
        }

        .category-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .method-table {
            width: 100%;
            table-layout: fixed;
        }

        .method-table td {
            border-top: 1px solid #e2e8f0;
            padding: 1.4mm 2mm;
            font-size: 6.7pt;
            color: #475569;
            vertical-align: top;
        }

        .method-table td:first-child {
            width: 29%;
            color: #17365d;
            font-weight: bold;
        }

        .fixed-footer {
            position: fixed;
            bottom: -9mm;
            left: 0;
            right: 0;
            height: 6mm;
            text-align: center;
            font-size: 6.6pt;
            color: #64748b;
        }

        .fixed-footer::after {
            content: "SMP | Sistema de Gestão Policial | Página " counter(page);
        }
    </style>
</head>
<body>
    <div class="fixed-footer"></div>

    @php
        $recordTotal = $stats['total_stations'] ?? 0;
        $recordActive = $stats['active_stations'] ?? 0;
        $recordInactive = $stats['inactive_stations'] ?? 0;
        $linkedUsers = $stats['total_officers'] ?? 0;
        $operationalUnits = $stats['total_operational_units'] ?? 0;
        $districtTotal = $stats['total_districts'] ?? 0;
        $userTotal = $stats['total_users'] ?? 0;
        $incidentTotal = $stats['total_incidents'] ?? 0;

        $roleLabels = [
            'admin' => 'Administrador',
            'manager' => 'Gestor',
            'police' => 'Polícia',
            'post_commander' => 'Comandante de posto',
            'squad_commander' => 'Comandante de esquadra',
            'district_commander' => 'Comandante distrital',
            'sernic_officer' => 'Oficial SERNIC',
            'citizen' => 'Cidadão',
        ];

        $statusLabels = [
            'investigating' => 'Em investigação',
            'resolved' => 'Resolvida',
            'pending' => 'Pendente',
            'archived' => 'Arquivada',
        ];

        $priorityLabels = [
            'high' => 'Alta',
            'medium' => 'Média',
            'low' => 'Baixa',
            'urgent' => 'Urgente',
        ];

        $translate = static function ($value, array $labels): string {
            $value = (string) $value;

            return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
        };

        $categories = collect($stats['incidents_by_category'] ?? []);
        $categoryChunks = $categories->chunk((int) max(1, ceil($categories->count() / 2)));
        $leftCategories = $categoryChunks->get(0, collect())->all();
        $rightCategories = $categoryChunks->get(1, collect())->all();
        $leftKeys = array_keys($leftCategories);
        $rightKeys = array_keys($rightCategories);
        $categoryRows = max(count($leftKeys), count($rightKeys));
    @endphp

    <table class="masthead">
        <tr>
            <td class="brand">SMP<span>Sistema de Gestão Policial</span></td>
            <td class="title">
                <h1>Relatório Administrativo</h1>
                <span>Painel Super Admin</span>
            </td>
            <td class="meta">
                <div><strong>Gerado em:</strong> {{ $generatedAt }}</div>
                <div><strong>Responsável:</strong> {{ $generatedBy ?? 'Super-Admin' }}</div>
                <div><strong>Âmbito:</strong> painel administrativo do SMP</div>
            </td>
        </tr>
    </table>

    <table class="kpi-primary">
        <tr>
            <td><div class="kpi"><div class="kpi-label">Registos administrativos</div><div class="kpi-value">{{ $recordTotal }}</div></div></td>
            <td><div class="kpi"><div class="kpi-label">Subunidades Policiais</div><div class="kpi-value">{{ $operationalUnits }}</div></div></td>
            <td><div class="kpi"><div class="kpi-label">Utilizadores vinculados</div><div class="kpi-value">{{ $linkedUsers }}</div></div></td>
            <td><div class="kpi"><div class="kpi-label">Ocorrências</div><div class="kpi-value">{{ $incidentTotal }}</div></div></td>
        </tr>
    </table>

    <table class="kpi-secondary">
        <tr>
            <td>Registos activos: <strong>{{ $recordActive }}</strong></td>
            <td>Registos inactivos: <strong>{{ $recordInactive }}</strong></td>
            <td>Distritos: <strong>{{ $districtTotal }}</strong></td>
            <td>Utilizadores SMP: <strong>{{ $userTotal }}</strong></td>
        </tr>
    </table>

    <div class="scope-note">
        Os registos administrativos e as subunidades policiais correspondem a inventários distintos e são apresentados separadamente.
    </div>

    <div class="section">
        <h2 class="section-title">Composição e Distribuição</h2>
        <table class="two-col">
            <tr>
                <td>
                    <div class="panel-stack">
                        <div class="panel">
                            <h3>Composição dos registos administrativos</h3>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Tipo</th><th class="num">Registos</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($stats['stations_by_type'] ?? [] as $type => $count)
                                    <tr><td>{{ $type }}</td><td class="num">{{ $count }}</td></tr>
                                    @empty
                                    <tr><td colspan="2" class="empty">Sem dados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="panel">
                            <h3>Distribuição por distrito</h3>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Distrito</th><th class="num">Registos</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($stats['stations_by_district'] ?? [] as $district => $count)
                                    <tr><td>{{ $district }}</td><td class="num">{{ $count }}</td></tr>
                                    @empty
                                    <tr><td colspan="2" class="empty">Sem dados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="panel">
                            <h3>Ocorrências por prioridade</h3>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Prioridade</th><th class="num">Ocorrências</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($stats['incidents_by_priority'] ?? [] as $priority => $count)
                                    <tr><td>{{ $translate($priority, $priorityLabels) }}</td><td class="num">{{ $count }}</td></tr>
                                    @empty
                                    <tr><td colspan="2" class="empty">Sem dados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="panel-stack">
                        <div class="panel">
                            <h3>Atribuições de funções</h3>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Função</th><th class="num">Atribuições</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($stats['roles'] ?? [] as $role => $count)
                                    <tr><td>{{ $translate($role, $roleLabels) }}</td><td class="num">{{ $count }}</td></tr>
                                    @empty
                                    <tr><td colspan="2" class="empty">Sem dados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="panel">
                            <h3>Ocorrências por estado</h3>
                            <table class="data-table">
                                <thead>
                                    <tr><th>Estado</th><th class="num">Ocorrências</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($stats['incidents_by_status'] ?? [] as $status => $count)
                                    <tr><td>{{ $translate($status, $statusLabels) }}</td><td class="num">{{ $count }}</td></tr>
                                    @empty
                                    <tr><td colspan="2" class="empty">Sem dados</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2 class="section-title">Ocorrências por Categoria</h2>
        <table class="category-table">
            <colgroup>
                <col style="width: 36%">
                <col style="width: 14%">
                <col style="width: 36%">
                <col style="width: 14%">
            </colgroup>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="num">Ocorrências</th>
                    <th>Categoria</th>
                    <th class="num">Ocorrências</th>
                </tr>
            </thead>
            <tbody>
                @if($categories->isEmpty())
                <tr><td colspan="4" class="empty">Sem dados</td></tr>
                @else
                @for($i = 0; $i < $categoryRows; $i++)
                    @php
                        $leftName = $leftKeys[$i] ?? null;
                        $rightName = $rightKeys[$i] ?? null;
                    @endphp
                <tr>
                    <td>{{ $leftName ?? '' }}</td>
                    <td class="num">{{ $leftName !== null ? $leftCategories[$leftName] : '' }}</td>
                    <td>{{ $rightName ?? '' }}</td>
                    <td class="num">{{ $rightName !== null ? $rightCategories[$rightName] : '' }}</td>
                </tr>
                @endfor
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Nota Metodológica</h2>
        <table class="method-table">
            <tr><td>Registos administrativos</td><td>police_stations — inventário administrativo de esquadras e postos do SMP.</td></tr>
            <tr><td>Subunidades Policiais</td><td>unidades policiais — inventário operacional distinto, sem soma aos registos administrativos.</td></tr>
            <tr><td>Utilizadores vinculados</td><td>contas associadas aos registos administrativos (police_station_id preenchido); não representam necessariamente o número de efectivos policiais.</td></tr>
            <tr><td>Atribuições de funções</td><td>atribuições registadas em model_has_roles; a soma não corresponde ao total de utilizadores.</td></tr>
            <tr><td>Distritos, utilizadores e ocorrências</td><td>districts, users, incidents e categories — contagens directas das tabelas do sistema.</td></tr>
        </table>
    </div>
</body>
</html>
