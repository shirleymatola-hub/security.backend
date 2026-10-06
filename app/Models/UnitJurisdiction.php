<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitJurisdiction extends Model
{
    use HasFactory;

    protected $table = 'unit_jurisdictions';

    protected $fillable = [
        'unidade_policial_id',
        'area_estudo_id',
        'name',
        'description',
        'effective_date',
        'source_document',
        'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * A unidade policial (posto/esquadra) ao qual esta jurisdicao pertence.
     */
    public function unidadePolicia(): BelongsTo
    {
        return $this->belongsTo(UnidadePolicia::class, 'unidade_policial_id');
    }

    /**
     * O bairro da Area de Estudo ao qual esta jurisdicao esta associada.
     */
    public function areaDeEstudo(): BelongsTo
    {
        return $this->belongsTo(AreaDeEstudo::class, 'area_estudo_id');
    }
}
