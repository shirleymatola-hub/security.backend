@php
    $priorityEnum = \App\Enums\Priority::tryFrom((string) $incident->priority);
    $statusEnum = \App\Enums\IncidentStatus::tryFrom((string) $incident->status);
    $station = $incident->policeStation;
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Ocorrência') }} #{{ $incident->reference_code }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 3px solid #001e40; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #001e40; font-size: 20px; margin: 0; }
        .header h2 { color: #115cb9; font-size: 14px; margin: 5px 0 0; }
        .header p { font-size: 11px; color: #737780; margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #001e40; color: white; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e6eff8; font-size: 11px; vertical-align: top; }
        .details td.label { width: 30%; font-weight: bold; color: #001e40; background: #f6faff; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section h3 { color: #001e40; font-size: 14px; border-bottom: 2px solid #e6eff8; padding-bottom: 5px; margin-bottom: 10px; }
        .description { padding: 10px; border: 1px solid #c3c6d1; background: #f6faff; font-size: 11px; line-height: 1.5; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #e6eff8; font-size: 10px; color: #737780; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $station->name ?? 'SMP Matola C' }}</h1>
        <h2>{{ __('Ficha da Ocorrência') }}: #{{ $incident->reference_code }}</h2>
        @if($station)
            <p>{{ __('Código') }}: {{ $station->code }} | {{ $station->address ?? '—' }} | {{ __('Telefone') }}: {{ $station->phone ?? '—' }}</p>
        @endif
    </div>

    <div class="section">
        <h3>{{ $incident->title }}</h3>
        <p>
            @if($priorityEnum)
                <span class="badge" style="background-color: {{ $priorityEnum->color() }}20; color: {{ $priorityEnum->color() }}">{{ $priorityEnum->label() }}</span>
            @endif
            @if($statusEnum)
                <span class="badge" style="background-color: {{ $statusEnum->color() }}20; color: {{ $statusEnum->color() }}">{{ $statusEnum->label() }}</span>
            @endif
        </p>
    </div>

    <div class="section">
        <h3>{{ __('Detalhes') }}</h3>
        <table class="details">
            <tbody>
                <tr><td class="label">{{ __('Referência') }}</td><td>{{ $incident->reference_code }}</td></tr>
                <tr><td class="label">{{ __('Categoria') }}</td><td>{{ $incident->category?->name ?? '-' }}</td></tr>
                <tr><td class="label">{{ __('Prioridade') }}</td><td>{{ $priorityEnum?->label() ?? $incident->priority }}</td></tr>
                <tr><td class="label">{{ __('Estado') }}</td><td>{{ $statusEnum?->label() ?? $incident->status }}</td></tr>
                <tr><td class="label">{{ __('Data da Ocorrência') }}</td><td>{{ $incident->incident_date?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                <tr><td class="label">{{ __('Registada em') }}</td><td>{{ $incident->created_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                @if($incident->resolved_at)
                    <tr><td class="label">{{ __('Resolvida em') }}</td><td>{{ $incident->resolved_at->format('d/m/Y H:i') }}</td></tr>
                @endif
                <tr><td class="label">{{ __('Posto Policial') }}</td><td>{{ $station->name ?? '-' }}</td></tr>
                <tr><td class="label">{{ __('Agente Atribuído') }}</td><td>{{ $incident->assignee?->name ?? __('Não atribuído') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Localização') }}</h3>
        <table class="details">
            <tbody>
                <tr><td class="label">{{ __('Endereço') }}</td><td>{{ $incident->address_detail ?? '-' }}</td></tr>
                <tr><td class="label">{{ __('Bairro') }}</td><td>{{ $incident->neighborhood ?? '-' }}</td></tr>
                <tr>
                    <td class="label">{{ __('Coordenadas') }}</td>
                    <td>
                        @if($incident->latitude && $incident->longitude)
                            {{ $incident->latitude }}, {{ $incident->longitude }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('Descrição') }}</h3>
        <div class="description">{{ $incident->description ?? '-' }}</div>
    </div>

    <div class="section">
        <h3>{{ __('Reportado Por') }}</h3>
        <table class="details">
            <tbody>
                <tr><td class="label">{{ __('Nome') }}</td><td>{{ $incident->is_anonymous ? __('Anónimo') : ($incident->reporter?->name ?? __('Anónimo')) }}</td></tr>
                <tr><td class="label">E-mail</td><td>{{ $incident->is_anonymous ? '-' : ($incident->reporter?->email ?? '-') }}</td></tr>
                <tr><td class="label">{{ __('Telefone') }}</td><td>{{ $incident->is_anonymous ? '-' : ($incident->reporter?->phone ?? '-') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>{{ $station->name ?? 'SMP' }} | SMP Matola C</p>
        <p>{{ __('Documento gerado em') }}: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
