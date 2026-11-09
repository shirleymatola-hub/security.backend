<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório Administrativo - SMP</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 30px; }
        .header { text-align: center; border-bottom: 3px solid #1e3a5f; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; color: #1e3a5f; margin: 0; }
        .header p { font-size: 12px; color: #666; margin: 5px 0 0; }
        .section { margin-bottom: 20px; }
        .section h2 { font-size: 14px; color: #1e3a5f; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-bottom: 10px; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .stat-card { flex: 1; min-width: 140px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #1e3a5f; }
        .stat-card .label { font-size: 10px; color: #666; text-transform: uppercase; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #1e3a5f; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 2px solid #1e3a5f; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório Administrativo</h1>
        <p>SMP - Sistema de Gestão Policial</p>
        <p>Gerado por: Super-Admin | Data: {{ $generatedAt }}</p>
    </div>

    <div class="section">
        <h2>Resumo Geral</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value">{{ $stats['total_stations'] }}</div>
                <div class="label">Total Postos</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['active_stations'] }}</div>
                <div class="label">Postos Ativos</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['inactive_stations'] }}</div>
                <div class="label">Postos Inativos</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['total_officers'] }}</div>
                <div class="label">Total Efetivos</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['total_districts'] }}</div>
                <div class="label">Total Distritos</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['total_users'] }}</div>
                <div class="label">Total Utilizadores</div>
            </div>
            <div class="stat-card">
                <div class="value">{{ $stats['total_incidents'] }}</div>
                <div class="label">Total Ocorrências</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Distribuição dos Postos por Distrito</h2>
        <table>
            <thead>
                <tr><th>Distrito</th><th>Nº Postos</th></tr>
            </thead>
            <tbody>
                @forelse($stats['stations_by_district'] as $district => $count)
                <tr><td>{{ $district }}</td><td>{{ $count }}</td></tr>
                @empty
                <tr><td colspan="2" style="text-align: center;">Sem dados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Utilizadores por Função</h2>
        <table>
            <thead>
                <tr><th>Função</th><th>Nº Utilizadores</th></tr>
            </thead>
            <tbody>
                @forelse($stats['roles'] as $role => $count)
                <tr><td>{{ $role }}</td><td>{{ $count }}</td></tr>
                @empty
                <tr><td colspan="2" style="text-align: center;">Sem dados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ocorrências por Estado</h2>
        <table>
            <thead>
                <tr><th>Estado</th><th>Nº Ocorrências</th></tr>
            </thead>
            <tbody>
                @forelse($stats['incidents_by_status'] as $status => $count)
                <tr><td>{{ $status }}</td><td>{{ $count }}</td></tr>
                @empty
                <tr><td colspan="2" style="text-align: center;">Sem dados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ocorrências por Prioridade</h2>
        <table>
            <thead>
                <tr><th>Prioridade</th><th>Nº Ocorrências</th></tr>
            </thead>
            <tbody>
                @forelse($stats['incidents_by_priority'] as $priority => $count)
                <tr><td>{{ $priority }}</td><td>{{ $count }}</td></tr>
                @empty
                <tr><td colspan="2" style="text-align: center;">Sem dados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ocorrências por Categoria</h2>
        <table>
            <thead>
                <tr><th>Categoria</th><th>Nº Ocorrências</th></tr>
            </thead>
            <tbody>
                @forelse($stats['incidents_by_category'] as $category => $count)
                <tr><td>{{ $category }}</td><td>{{ $count }}</td></tr>
                @empty
                <tr><td colspan="2" style="text-align: center;">Sem dados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        SMP - Sistema de Gestão Policial | Relatório gerado automaticamente | {{ $generatedAt }}
    </div>
</body>
</html>
