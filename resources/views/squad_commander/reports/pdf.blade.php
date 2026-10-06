{{--
    Relatório Mensal da Esquadra (Comandante de Esquadra).
    Esta view não existia no projeto original (ReportController::exportPdf falhava);
    criada com base em manager/reports/pdf.blade.php e nos dados que o controller passa:
    $report (ReportService::generateMonthlyReport), $year, $month, $station.
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório Esquadra - {{ $station?->name ?? '' }} - {{ $report['month_name'] }} {{ $year }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 3px solid #001e40; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #001e40; font-size: 20px; margin: 0; }
        .header h2 { color: #115cb9; font-size: 14px; margin: 5px 0 0; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 20px; }
        .stat-box { background: #f6faff; border: 1px solid #c3c6d1; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-box .label { font-size: 10px; color: #737780; text-transform: uppercase; font-weight: bold; }
        .stat-box .value { font-size: 22px; font-weight: bold; color: #001e40; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th { background: #001e40; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        table.data td { padding: 8px; border-bottom: 1px solid #e6eff8; font-size: 11px; }
        .section { margin-bottom: 20px; }
        .section h3 { color: #001e40; font-size: 14px; border-bottom: 2px solid #e6eff8; padding-bottom: 5px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #e6eff8; font-size: 10px; color: #737780; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $station?->name ?? 'SMP - Sistema de Monitoramento Policial' }}</h1>
        <h2>Relatório Mensal: {{ $report['month_name'] }} {{ $year }}</h2>
        @if($station)
            <p>Código: {{ $station->code }} | {{ $station->address }}</p>
        @endif
    </div>

    <table class="stats">
        <tr>
            <td class="stat-box"><div class="label">Total</div><div class="value">{{ $report['total'] }}</div></td>
            <td class="stat-box"><div class="label">Pendentes</div><div class="value">{{ $report['pending'] }}</div></td>
            <td class="stat-box"><div class="label">Investigação</div><div class="value">{{ $report['investigating'] }}</div></td>
            <td class="stat-box"><div class="label">Resolvidas</div><div class="value">{{ $report['resolved'] }}</div></td>
            <td class="stat-box"><div class="label">Taxa</div><div class="value">{{ $report['resolution_rate'] }}%</div></td>
        </tr>
    </table>

    <div class="section">
        <h3>Por Categoria</h3>
        <table class="data">
            <thead><tr><th>Categoria</th><th>Qtd</th><th>%</th></tr></thead>
            <tbody>
                @forelse($report['by_category'] as $item)
                    <tr><td>{{ $item['category'] }}</td><td>{{ $item['count'] }}</td><td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td></tr>
                @empty
                    <tr><td colspan="3">Sem ocorrências neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Por Prioridade</h3>
        <table class="data">
            <thead><tr><th>Prioridade</th><th>Qtd</th><th>%</th></tr></thead>
            <tbody>
                @foreach($report['by_priority'] as $item)
                    <tr><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td><td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Por Bairro</h3>
        <table class="data">
            <thead><tr><th>Bairro</th><th>Qtd</th><th>%</th></tr></thead>
            <tbody>
                @forelse($report['by_neighborhood'] as $item)
                    <tr><td>{{ $item['neighborhood'] }}</td><td>{{ $item['count'] }}</td><td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td></tr>
                @empty
                    <tr><td colspan="3">Sem dados de bairros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>{{ $station?->name ?? '' }} | SMP Matola C</p>
        <p>Gerado em: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
