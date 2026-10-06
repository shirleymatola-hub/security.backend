<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório Posto - {{ $station->name }} - {{ $report['month_name'] }} {{ ($isAllTime ?? false) ? '' : $year }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 3px solid #001e40; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #001e40; font-size: 20px; margin: 0; }
        .header h2 { color: #115cb9; font-size: 14px; margin: 5px 0 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: #f6faff; border: 1px solid #c3c6d1; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-box .label { font-size: 10px; color: #737780; text-transform: uppercase; font-weight: bold; }
        .stat-box .value { font-size: 22px; font-weight: bold; color: #001e40; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #001e40; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e6eff8; font-size: 11px; }
        .section { margin-bottom: 20px; }
        .section h3 { color: #001e40; font-size: 14px; border-bottom: 2px solid #e6eff8; padding-bottom: 5px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #e6eff8; font-size: 10px; color: #737780; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $station->name }}</h1>
        <h2>Relatório {{ ($isAllTime ?? false) ? 'Histórico' : 'Mensal' }}: {{ $report['month_name'] }} {{ ($isAllTime ?? false) ? '' : $year }}</h2>
        <p>Código: {{ $station->code }} | {{ $station->address }}</p>
    </div>

    <div class="stats-grid">
        <div class="stat-box"><div class="label">Total</div><div class="value">{{ $report['total'] }}</div></div>
        <div class="stat-box"><div class="label">Pendentes</div><div class="value">{{ $report['pending'] }}</div></div>
        <div class="stat-box"><div class="label">Investigação</div><div class="value">{{ $report['investigating'] }}</div></div>
        <div class="stat-box"><div class="label">Resolvidas</div><div class="value">{{ $report['resolved'] }}</div></div>
        <div class="stat-box"><div class="label">Taxa</div><div class="value">{{ $report['resolution_rate'] }}%</div></div>
    </div>

    <div class="section">
        <h3>Por Categoria</h3>
        <table>
            <thead><tr><th>Categoria</th><th>Qtd</th><th>%</th></tr></thead>
            <tbody>
                @foreach($report['by_category'] as $item)
                    <tr><td>{{ $item['category'] }}</td><td>{{ $item['count'] }}</td><td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Por Prioridade</h3>
        <table>
            <thead><tr><th>Prioridade</th><th>Qtd</th><th>%</th></tr></thead>
            <tbody>
                @foreach($report['by_priority'] as $item)
                    <tr><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td><td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>{{ $station->name }} | SMP Matola C</p>
        <p>Gerado em: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
