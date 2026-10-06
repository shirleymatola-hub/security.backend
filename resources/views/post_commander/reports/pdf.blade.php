<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório do Posto - {{ $station->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { background: #001e40; color: #fff; padding: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0 0; opacity: 0.8; font-size: 12px; }
        .stats { display: flex; gap: 12px; margin-bottom: 20px; }
        .stat-box { flex: 1; border: 1px solid #ddd; padding: 12px; text-align: center; border-radius: 6px; }
        .stat-box .number { font-size: 24px; font-weight: bold; color: #001e40; }
        .stat-box .label { font-size: 10px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f0f0f0; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #001e40; }
        td { padding: 8px; border-bottom: 1px solid #eee; font-size: 11px; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Relatório Mensal') }} — {{ $station->name }}</h1>
        <p>{{ __('Código:') }} {{ $station->code }} | {{ __('Mês:') }} {{ sprintf('%02d/%d', $month, $year) }}</p>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="number">{{ $total }}</div>
            <div class="label">{{ __('Total') }}</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#f59e0b">{{ $pending }}</div>
            <div class="label">{{ __('Pendentes') }}</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#3b82f6">{{ $investigating }}</div>
            <div class="label">{{ __('Investigação') }}</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#22c55e">{{ $resolved }}</div>
            <div class="label">{{ __('Resolvidas') }}</div>
        </div>
    </div>

    <h2 style="font-size:14px; color:#001e40; border-bottom:2px solid #001e40; padding-bottom:4px;">{{ __('Ocorrências por Categoria') }}</h2>
    <table>
        <thead>
            <tr><th>{{ __('Categoria') }}</th><th style="text-align:right">{{ __('Quantidade') }}</th><th style="text-align:right">%</th></tr>
        </thead>
        <tbody>
            @foreach($byCategory as $cat)
                <tr>
                    <td>{{ $cat->category }}</td>
                    <td style="text-align:right">{{ $cat->count }}</td>
                    <td style="text-align:right">{{ $total > 0 ? round(($cat->count / $total) * 100, 1) : 0 }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }} — SMP Matola C
    </div>
</body>
</html>
