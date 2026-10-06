<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoliceStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'district_id',
        'commander_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the district that owns the police station.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the commander of the police station.
     */
    public function commander(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commander_id');
    }

    /**
     * Get the officers in this police station.
     */
    public function officers(): HasMany
    {
        return $this->hasMany(User::class, 'police_station_id');
    }

    /**
     * Get the incidents for this police station.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the jurisdictions for this police station.
     */
    public function jurisdictions(): HasMany
    {
        return $this->hasMany(Jurisdiction::class);
    }

    /**
     * Unidades policiais (esquadras/postos) vinculadas a este posto policial formal.
     */
    public function unidadesPoliciais(): HasMany
    {
        return $this->hasMany(UnidadePolicia::class);
    }

    /**
     * Filtrar postos dentro da área de estudo (Município da Matola)
     */
    public function scopeWithinStudyArea($query)
    {
        $studyArea = config('map.study_area');
        if ($studyArea) {
            $query->whereBetween('latitude', [$studyArea['south_west']['lat'], $studyArea['north_east']['lat']])
                ->whereBetween('longitude', [$studyArea['south_west']['lng'], $studyArea['north_east']['lng']]);
        }
        return $query;
    }
}
