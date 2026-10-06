<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadePolicialBairro extends Model
{
    protected $table = 'unidade_policial_bairros';

    public $timestamps = false;

    protected $fillable = [
        'area_estudo_id',
        'unidade_policial_id',
    ];

    /**
     * O bairro da tabela "Area de Estudo".
     */
    public function areaDeEstudo(): BelongsTo
    {
        return $this->belongsTo(AreaDeEstudo::class, 'area_estudo_id');
    }

    /**
     * A unidade policial.
     */
    public function unidadePolicia(): BelongsTo
    {
        return $this->belongsTo(UnidadePolicia::class, 'unidade_policial_id');
    }
}
