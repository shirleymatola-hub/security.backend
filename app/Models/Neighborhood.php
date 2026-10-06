<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'name',
        'latitude',
        'longitude',
        'boundary',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function policeStations(): BelongsToMany
    {
        return $this->belongsToMany(PoliceStation::class, 'neighborhood_police_station');
    }
}
