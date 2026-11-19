<?php

namespace App\Services\SuperAdmin;

use App\Models\UnidadePolicia;
use App\Services\AreaDeEstudoSpatialFilter;

class SubunidadePoliciaService
{
    public function all(bool $onlyStudyArea = true): array
    {
        $query = UnidadePolicia::query()
            ->with([
                'policeStation:id,name',
                'unidadePolicialBairros.areaDeEstudo:id,Bairro',
            ]);

        if ($onlyStudyArea) {
            AreaDeEstudoSpatialFilter::apply($query, 'geom');
        }

        return $query
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (UnidadePolicia $unit, int $index) => $this->present($unit, $index + 1))
            ->all();
    }

    private function present(UnidadePolicia $unit, int $number): array
    {
        $nome = trim((string) $unit->unidade_policial);
        $tipo = $this->tipo($nome);

        return [
            'numero' => $number,
            'id' => (int) $unit->id,
            'nome' => $nome,
            'tipo' => $tipo,
            'tipo_label' => match ($tipo) {
                'esquadra' => 'Esquadra',
                'sernic' => 'SERNIC',
                default => 'Posto Policial',
            },
            'esquadra_tutela' => $unit->esquadra_tutela,
            'esquadra_administrativa_id' => $unit->police_station_id,
            'esquadra_administrativa' => $unit->policeStation?->name,
            'bairros_fisicos' => $unit->unidadePolicialBairros
                ->pluck('areaDeEstudo.Bairro')
                ->filter()
                ->values()
                ->all(),
            'localizacao_referencia' => $unit->localizacao_referencia,
            'area_atuacao' => $unit->area_jurisdicao,
            'contacto' => $unit->contacto_telefonico,
            'latitude' => $unit->latitude === null ? null : (float) $unit->latitude,
            'longitude' => $unit->longitude === null ? null : (float) $unit->longitude,
        ];
    }

    private function tipo(string $nome): string
    {
        if (stripos($nome, 'sernic') !== false) {
            return 'sernic';
        }

        return preg_match('/^\s*esquadra\b/i', $nome) === 1 ? 'esquadra' : 'posto';
    }
}
