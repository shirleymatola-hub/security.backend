<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jurisdiction extends Model
{
    use HasFactory;

    protected $table = 'jurisdictions';

    protected $fillable = [
        'police_station_id',
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
     * O posto policial (esquadra) ao qual esta jurisdição pertence.
     */
    public function policeStation(): BelongsTo
    {
        return $this->belongsTo(PoliceStation::class);
    }

    /**
     * O bairro da Área de Estudo ao qual esta jurisdição está associada.
     */
    public function areaDeEstudo(): BelongsTo
    {
        return $this->belongsTo(AreaDeEstudo::class, 'area_estudo_id');
    }
}
