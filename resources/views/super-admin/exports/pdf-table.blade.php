<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - SMP Super-Admin</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 5px; }
        .header-info { font-size: 11px; color: #666; margin-bottom: 15px; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #1e3a5f; color: white; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="header-info">
        Gerado por: Super-Admin SMP | Data: {{ $generatedAt }} | Total: {{ count($rows) }} registo(s)
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($headers) }}" style="text-align: center;">Nenhum registo encontrado</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        SMP - Sistema de Gestão Policial | Documento gerado automaticamente | {{ $generatedAt }}
    </div>
</body>
</html>
