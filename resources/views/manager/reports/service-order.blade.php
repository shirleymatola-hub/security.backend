<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço - {{ $station->name }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; line-height: 1.4; }

        .header { text-align: center; margin-bottom: 6px; }
        .header img { width: 80px; height: auto; display: block; margin: 0 auto 6px auto; }
        .header .rep { font-size: 12px; font-weight: bold; margin: 1px 0; }
        .header .min { font-size: 11px; margin: 1px 0; }
        .header .pol { font-size: 11px; font-weight: bold; margin: 1px 0; }
        .header .unidade { font-size: 11px; margin: 1px 0; }
        .header .posto { font-size: 12px; font-weight: bold; margin: 2px 0; text-transform: uppercase; }

        .divider { border-top: 2px solid #000; margin: 8px 0; }

        .doc-title {
            text-align: center; font-weight: bold; font-size: 14px;
            margin: 10px 0; text-decoration: underline; letter-spacing: 1px;
        }

        .sub-header { display: flex; justify-content: flex-end; margin-bottom: 6px; font-size: 10px; }
        .sub-header .visto { text-align: right; }
        .sub-header .visto .linha { border-bottom: 1px solid #000; display: inline-block; min-width: 100px; }
        .sub-header .assinatura { text-align: center; margin-left: 40px; }
        .sub-header .assinatura .nome-cargo { font-size: 10px; }

        .campo { margin: 4px 0; line-height: 1.6; }
        .campo .rotulo { font-weight: bold; }
        .campo .linha {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: calc(100% - 20px);
            min-height: 14px;
        }
        .campo .linha-short {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 120px;
            min-height: 14px;
        }

        .section { margin-top: 10px; margin-bottom: 4px; page-break-inside: avoid; }
        .section-title {
            font-weight: bold; font-size: 11px; margin-bottom: 4px;
            text-decoration: underline; background-color: #f0f0f0;
            padding: 2px 6px; border-left: 3px solid #000;
        }

        .item { margin: 3px 0; padding-left: 8px; line-height: 1.5; }
        .item .linha {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: calc(100% - 30px);
            min-height: 14px;
        }

        .row { display: flex; gap: 20px; margin: 4px 0; }
        .row > div { flex: 1; }

        .force-row { display: flex; gap: 30px; margin: 4px 0; }
        .force-row > div { flex: 1; }

        .auto-fill {
            background-color: #e8e8e8; padding: 1px 4px; border-radius: 2px;
            border-bottom: 1px solid #999; display: inline-block; min-width: 120px;
        }

        .blank-full { border-bottom: 1px solid #000; display: block; width: 100%; min-height: 18px; margin: 4px 0; }

        .periodo { margin: 8px 0; text-align: justify; line-height: 1.6; }
        .periodo .linha {
            border-bottom: 1px solid #000;
            display: inline;
        }

        .assunto {
            text-align: center; font-weight: bold; font-size: 12px;
            margin: 12px 0; text-decoration: underline;
        }

        .signature-area { margin-top: 50px; }
        .signature-block { display: flex; justify-content: space-between; padding: 0 20px; }
        .signature-block .sig { text-align: center; width: 200px; }
        .signature-block .sig .linha-assinatura {
            border-top: 1px solid #000; margin-top: 60px; padding-top: 4px;
            font-size: 10px;
        }

        .footer {
            margin-top: 30px; text-align: center; font-size: 9px; color: #666;
            border-top: 1px solid #ccc; padding-top: 8px;
        }
    </style>
</head>
<body>

    {{-- Cabeçalho Institucional --}}
    <div class="header">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/mozambique-emblem.png'))) }}" alt="Emblema de Moçambique" />
        <p class="rep">REPÚBLICA DE MOÇAMBIQUE</p>
        <p class="min">MINISTÉRIO DO INTERIOR</p>
        <p class="pol">POLÍCIA DA REPÚBLICA DE MOÇAMBIQUE</p>
        <p class="unidade">COMANDO PROVINCIAL DA PRM — MAPUTO</p>
        <p class="unidade">2ª ESQUADRA DA PRM — DJONASSE</p>
        <p class="posto">POSTO POLICIAL DA PRM {{ strtoupper($station->name) }}</p>
    </div>

    <div class="divider"></div>

    {{-- Título do Documento --}}
    <div class="doc-title">ORDEM DE SERVIÇO</div>

    {{-- Cabeçalho: Visto e Assinatura --}}
    <div class="sub-header">
        <div class="visto">
            Visto em <span class="linha">&nbsp;&nbsp;/&nbsp;&nbsp;/{{ now()->year }}</span>
        </div>
        <div class="assinatura">
            <div class="nome-cargo">Chefe de Posto</div>
        </div>
    </div>

    {{-- Assunto --}}
    <div class="campo">
        <span class="rotulo">Nº da OS:</span>
        <span class="linha"></span>
        <span style="margin-left: 8px; font-size: 10px;">/GCE/{{ now()->year }}</span>
    </div>

    {{-- Período --}}
    <div class="periodo">
        <strong>Período:</strong> Data ………… de …………{{ now()->year }}, das 08 horas às 08 horas do dia seguinte.
        Para o cumprimento das formalidades legais, o senhor Comandante desta Subunidade, manda através desta
        ordem o posicionamento da força e meios no terreno, por um período de 24 horas.
    </div>

    {{-- Direcção do Posto --}}
    <div class="section">
        <div class="section-title">1. DIRECÇÃO DO POSTO</div>
        <div class="campo">
            <span class="rotulo">Chefe do Posto:</span>
            {{ optional($station->commander)->name ?? '_________________________________' }},
            {{ optional($station->commander)->role ?? 'Inspector Principal da Polícia' }}
        </div>
    </div>

    {{-- Sector de Permanência --}}
    <div class="section">
        <div class="section-title">2. SECTOR DE PERMANÊNCIA</div>
        <div class="force-row">
            <div class="campo">
                <span class="rotulo">Força Escalada:</span>
                (<span class="linha-short">{{ $officersCount }}</span>)
            </div>
            <div class="campo">
                <span class="rotulo">Força Empregue:</span>
                (<span class="linha-short">&nbsp;</span>)
            </div>
        </div>
        <div class="campo">
            <span class="rotulo">Oficial de Controlo:</span>
            <span class="linha"></span>
        </div>
        <div class="campo">
            <span class="rotulo">Oficial de Permanência:</span>
            <span class="auto-fill">{{ $authUser->name }}</span>
        </div>
        <div class="campo">
            <span class="rotulo">Adjunto de Of. Permanência:</span>
            <span class="linha"></span>
        </div>
        <div class="campo">
            <span class="rotulo">Agente de SAFMVV:</span>
            <span class="linha"></span>
        </div>
    </div>

    {{-- Sectorização --}}
    <div class="section">
        <div class="section-title">3. SECTORIZAÇÃO</div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
        <div class="item">3. <span class="linha"></span></div>
    </div>

    {{-- Segurança do Posto / Sentinela --}}
    <div class="section">
        <div class="section-title">4. SEGURANÇA DO POSTO / SENTINELA</div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
    </div>

    {{-- Posições --}}
    <div class="section">
        <div class="section-title">5. POSIÇÕES</div>
        <div class="campo">
            <span class="rotulo">Posição de</span> <span class="linha-short">&nbsp;</span> <span class="rotulo">24h00</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>

        <div class="campo" style="margin-top: 6px;">
            <span class="rotulo">Posição de</span> <span class="linha-short">&nbsp;</span> <span class="rotulo">24h00</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
    </div>

    {{-- Giros de Patrulha --}}
    <div class="section">
        <div class="section-title">6. GIROS DE PATRULHA</div>
        <div class="campo">
            <span class="rotulo">Giro nº 1</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>

        <div class="campo" style="margin-top: 6px;">
            <span class="rotulo">Giro nº 2</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
    </div>

    {{-- Pessoal --}}
    <div class="section">
        <div class="section-title">7. PESSOAL</div>

        <div class="campo">
            <span class="rotulo">Dispensandos</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
        <div class="item">3. <span class="linha"></span></div>

        <div class="campo" style="margin-top: 6px;">
            <span class="rotulo">Doentes</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>

        <div class="campo" style="margin-top: 6px;">
            <span class="rotulo">Férias</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>

        <div class="campo" style="margin-top: 6px;">
            <span class="rotulo">Faltas</span>
        </div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
        <div class="item">3. <span class="linha"></span></div>
    </div>

    {{-- Escala Noturna --}}
    <div class="section">
        <div class="section-title">8. ESCALA NOTURNA</div>
        <div class="item">1. <span class="linha"></span></div>
        <div class="item">2. <span class="linha"></span></div>
    </div>

    {{-- Posto Fixo / Escalar --}}
    <div class="section">
        <div class="section-title">9. POSTO FIXO / ESCALAR</div>
        <div class="blank-full">&nbsp;</div>
    </div>

    {{-- Assinatura --}}
    <div class="signature-area">
        <div class="signature-block">
            <div class="sig">
                <div class="linha-assinatura">
                    {{ optional($station->commander)->name ?? '_________________________' }}<br>
                    <span style="font-size: 9px;">{{ optional($station->commander)->role ?? 'Inspector Principal da Polícia' }}</span><br>
                    <span style="font-size: 9px;">/Chefe do Posto/</span>
                </div>
            </div>
            <div class="sig">
                <div class="linha-assinatura">
                    ___________________________<br>
                    <span style="font-size: 9px;">Visto</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Rodapé --}}
    <div class="footer">
        <p>{{ $station->name }} | {{ $station->code }} | SMP</p>
        <p>Documento gerado automaticamente em {{ now()->format('d/m/Y H:i') }} — Preencher os campos em branco antes de assinar.</p>
    </div>

</body>
</html>
