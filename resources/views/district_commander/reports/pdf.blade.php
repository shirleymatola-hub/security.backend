{{--
    Relatório do Distrito (Comandante Distrital).
    Esta view não existia no projeto original (ReportController::exportPdf falhava);
    criada com base em post_commander/reports/pdf.blade.php e nos dados que o controller passa:
    $report (total, pending, investigating, resolved, resolution_rate), $districtId, $district, $year, $month, $isAllTime.
--}}
@php
    $districtName = $district?->name ?? (__('Distrito') . ' #' . $districtId);
    $periodLabel = $isAllTime
        ? __('Todos (Histórico)')
        : sprintf('%02d/%d', (int) $month, $year) . ' (' . \Carbon\Carbon::create($year, (int) $month, 1)->format('F') . ')';
    $statusRows = [
        __('Pendentes') => $report['pending'],
        __('Em Investigação') => $report['investigating'],
        __('Resolvidas') => $report['resolved'],
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Relatório do Distrito') }} - {{ $districtName }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { background: #001e40; color: #fff; padding: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0 0; opacity: 0.8; font-size: 12px; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 20px; }
        .stat-box { border: 1px solid #ddd; padding: 12px; text-align: center; border-radius: 6px; }
        .stat-box .number { font-size: 24px; font-weight: bold; color: #001e40; }
        .stat-box .label { font-size: 10px; color: #666; text-transform: uppercase; }
        table.summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.summary th { background: #f0f0f0; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #001e40; }
        table.summary td { padding: 8px; border-bottom: 1px solid #eee; font-size: 11px; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            @if($isAllTime)
                {{ __('Relatório Histórico do Distrito (Todas as Ocorrências)') }}
            @else
                {{ __('Relatório Mensal') }}
            @endif
            — {{ $districtName }}
        </h1>
        <p>{{ __('Período:') }} {{ $periodLabel }}</p>
    </div>

    <table class="stats">
        <tr>
            <td class="stat-box">
                <div class="number">{{ $report['total'] }}</div>
                <div class="label">{{ __('Total') }}</div>
            </td>
            <td class="stat-box">
                <div class="number" style="color:#f59e0b">{{ $report['pending'] }}</div>
                <div class="label">{{ __('Pendentes') }}</div>
            </td>
            <td class="stat-box">
                <div class="number" style="color:#3b82f6">{{ $report['investigating'] }}</div>
                <div class="label">{{ __('Investigação') }}</div>
            </td>
            <td class="stat-box">
                <div class="number" style="color:#22c55e">{{ $report['resolved'] }}</div>
                <div class="label">{{ __('Resolvidas') }}</div>
            </td>
            <td class="stat-box">
                <div class="number">{{ $report['resolution_rate'] }}%</div>
                <div class="label">{{ __('Taxa Resolução') }}</div>
            </td>
        </tr>
    </table>

    <h2 style="font-size:14px; color:#001e40; border-bottom:2px solid #001e40; padding-bottom:4px;">{{ __('Resumo por Estado') }}</h2>
    <table class="summary">
        <thead>
            <tr><th>{{ __('Estado') }}</th><th style="text-align:right">{{ __('Quantidade') }}</th><th style="text-align:right">%</th></tr>
        </thead>
        <tbody>
            @foreach($statusRows as $label => $count)
                <tr>
                    <td>{{ $label }}</td>
                    <td style="text-align:right">{{ $count }}</td>
                    <td style="text-align:right">{{ $report['total'] > 0 ? round(($count / $report['total']) * 100, 1) : 0 }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }} — SMP Matola C
    </div>
</body>
</html>
