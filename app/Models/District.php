<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'province',
        'latitude',
        'longitude',
        'boundary',
    ];

    /**
     * Get the neighborhoods in this district.
     */
    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    /**
     * Get the police stations in this district.
     */
    public function policeStations(): HasMany
    {
        return $this->hasMany(PoliceStation::class);
    }
}
