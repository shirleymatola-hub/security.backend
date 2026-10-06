<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Relatório SERNIC') }} - {{ $report['month_name'] }} {{ $year }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 3px solid #001e40; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #001e40; font-size: 20px; margin: 0; }
        .header h2 { color: #115cb9; font-size: 14px; margin: 5px 0 0; }
        .header p { font-size: 11px; color: #737780; margin-top: 4px; }
        .stats-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .stats-grid td { width: 20%; text-align: center; padding: 10px 5px; border: 1px solid #c3c6d1; background: #f6faff; }
        .stats-grid .label { font-size: 10px; color: #737780; text-transform: uppercase; font-weight: bold; display: block; }
        .stats-grid .value { font-size: 22px; font-weight: bold; color: #001e40; margin-top: 4px; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #001e40; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e6eff8; font-size: 11px; }
        tr:nth-child(even) { background: #f6faff; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section h3 { color: #001e40; font-size: 14px; border-bottom: 2px solid #e6eff8; padding-bottom: 5px; margin-bottom: 10px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #e6eff8; font-size: 10px; color: #737780; }
        .bar-row { margin-bottom: 3px; }
        .bar-label { display: inline-block; width: 35px; font-size: 10px; font-weight: bold; text-align: right; padding-right: 6px; color: #333; vertical-align: middle; }
        .bar-track { display: inline-block; width: 65%; height: 14px; background: #e6eff8; vertical-align: middle; }
        .bar-fill { display: block; height: 14px; background: #115cb9; }
        .bar-fill-cat { display: block; height: 14px; background: #001e40; }
        .bar-value { display: inline-block; width: 30px; font-size: 10px; padding-left: 4px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERNIC - Serviço de Investigação Criminal</h1>
        <h2>{{ __('Relatório Mensal') }}: {{ $report['month_name'] }} {{ $year }}</h2>
        <p>{{ __('Oficial') }}: {{ $user->name }} | {{ $user->email ?? '—' }}</p>
    </div>

    <table class="stats-grid">
        <tr>
            <td><span class="label">{{ __('Total') }}</span><span class="value">{{ $report['total'] }}</span></td>
            <td><span class="label">{{ __('Pendentes') }}</span><span class="value">{{ $report['pending'] }}</span></td>
            <td><span class="label">{{ __('Investigação') }}</span><span class="value">{{ $report['investigating'] }}</span></td>
            <td><span class="label">{{ __('Resolvidas') }}</span><span class="value">{{ $report['resolved'] }}</span></td>
            <td><span class="label">{{ __('Taxa Resolução') }}</span><span class="value">{{ $report['resolution_rate'] }}%</span></td>
        </tr>
    </table>

    <div class="section">
        <h3>{{ __('Por Categoria') }}</h3>
        <table>
            <thead><tr><th>{{ __('Categoria') }}</th><th>{{ __('Qtd') }}</th><th>%</th></tr></thead>
            <tbody>
                @forelse($report['by_category'] as $item)
                    <tr>
                        <td>{{ $item->category ?? $item['category'] ?? '-' }}</td>
                        <td>{{ $item->count ?? $item['count'] ?? 0 }}</td>
                        <td>{{ $report['total'] > 0 ? round(($item->count ?? $item['count'] ?? 0) / $report['total'] * 100, 1) : 0 }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:#999;">{{ __('Sem dados para este período') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Por Prioridade') }}</h3>
        <table>
            <thead><tr><th>{{ __('Prioridade') }}</th><th>{{ __('Qtd') }}</th><th>%</th></tr></thead>
            <tbody>
                @foreach($report['by_priority'] as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td>{{ $item['count'] }}</td>
                        <td>{{ $report['total'] > 0 ? round($item['count'] / $report['total'] * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(isset($report['incidents']) && count($report['incidents']) > 0)
    <div class="section">
        <h3>{{ __('Detalhes das Ocorrências') }} ({{ count($report['incidents']) }})</h3>
        <table>
            <thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Data') }}</th><th>{{ __('Categoria') }}</th><th>{{ __('Prioridade') }}</th><th>{{ __('Estado') }}</th></tr></thead>
            <tbody>
                @foreach($report['incidents'] as $incident)
                    <tr>
                        <td>{{ $incident->reference_code }}</td>
                        <td>{{ $incident->incident_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $incident->category?->name ?? '-' }}</td>
                        <td>{{ $incident->priority }}</td>
                        <td>{{ $incident->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>SERNIC - SMP Matola C</p>
        <p>{{ __('Relatório gerado em') }}: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
