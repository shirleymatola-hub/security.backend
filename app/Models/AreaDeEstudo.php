<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaDeEstudo extends Model
{
    protected $table = 'Area de Estudo';

    public $timestamps = false;

    protected $fillable = [
        'OBJECTID',
        'CodProv',
        'Provincia',
        'CodDist',
        'Distrito',
        'CodPost',
        'Posto',
        'CodLocal',
        'Localidade',
        'CodBairro',
        'Bairro',
        'TOTAL',
        'H',
        'M',
        'Shape_Leng',
        'Shape_Area',
    ];

    protected $casts = [
        'TOTAL' => 'double',
        'H' => 'double',
        'M' => 'double',
        'Shape_Leng' => 'double',
        'Shape_Area' => 'double',
    ];

    /**
     * Unidades policiais fisicamente localizadas neste bairro.
     */
    public function unidadesPoliciais(): HasMany
    {
        return $this->hasMany(UnidadePolicialBairro::class, 'area_estudo_id');
    }

    /**
     * Jurisdições administrativas que incluem este bairro.
     */
    public function jurisdictions(): HasMany
    {
        return $this->hasMany(Jurisdiction::class, 'area_estudo_id');
    }

    /**
     * Jurisdições de atuação de postos/unidades policiais que incluem este bairro.
     */
    public function unitJurisdictions(): HasMany
    {
        return $this->hasMany(UnitJurisdiction::class, 'area_estudo_id');
    }
}
