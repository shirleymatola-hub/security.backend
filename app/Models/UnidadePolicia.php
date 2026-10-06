<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class UnidadePolicia extends Model
{
    protected $table = 'unidades policiais';

    public $timestamps = false;

    protected $fillable = [
        'esquadra_tutela',
        'unidade_policial',
        'localizacao_referencia',
        'area_jurisdicao',
        'contacto_telefonico',
        'latitude',
        'longitude',
        'geom',
        'police_station_id',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'police_station_id' => 'integer',
    ];

    /**
     * O posto policial formal (police_stations) ao qual esta unidade está vinculada.
     */
    public function policeStation(): BelongsTo
    {
        return $this->belongsTo(PoliceStation::class);
    }

    /**
     * Registros de vinculação com bairros da Área de Estudo.
     */
    public function unidadePolicialBairros(): HasMany
    {
        return $this->hasMany(UnidadePolicialBairro::class, 'unidade_policial_id');
    }

    /**
     * Bairros da Área de Estudo onde esta unidade está fisicamente localizada.
     */
    public function bairros(): HasManyThrough
    {
        return $this->hasManyThrough(
            AreaDeEstudo::class,
            UnidadePolicialBairro::class,
            'unidade_policial_id',
            'id',
            'id',
            'area_estudo_id'
        );
    }

    /**
     * Jurisdições de atuação desta unidade policial (postos/bairros atendidos).
     */
    public function unitJurisdictions(): HasMany
    {
        return $this->hasMany(UnitJurisdiction::class, 'unidade_policial_id');
    }

    /**
     * Bairros da Área de Estudo que esta unidade atende (via unit_jurisdictions).
     */
    public function bairrosAtuacao(): HasManyThrough
    {
        return $this->hasManyThrough(
            AreaDeEstudo::class,
            UnitJurisdiction::class,
            'unidade_policial_id',
            'id',
            'id',
            'area_estudo_id'
        );
    }
}
