<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code',
        'title',
        'description',
        'category_id',
        'priority',
        'status',
        'latitude',
        'longitude',
        'address_detail',
        'neighborhood',
        'neighborhood_id',
        'reported_by',
        'assigned_to',
        'police_station_id',
        'incident_date',
        'resolved_at',
        'is_anonymous',
        'is_public',
    ];

    protected $casts = [
        'incident_date' => 'datetime',
        'resolved_at' => 'datetime',
        'is_anonymous' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Get the category of the incident.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who reported the incident.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the officer assigned to the incident.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the police station responsible for the incident.
     */
    public function policeStation(): BelongsTo
    {
        return $this->belongsTo(PoliceStation::class);
    }

    public function neighborhoodRel(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class, 'neighborhood_id');
    }

    /**
     * Get the updates for this incident.
     */
    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class);
    }

    /**
     * Get the attachments for this incident.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class);
    }

    public function logEvent(string $type, ?string $description = null, ?int $userId = null, ?string $oldValue = null, ?string $newValue = null, ?array $metadata = null): IncidentEvent
    {
        return $this->events()->create([
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate a unique reference code for the incident.
     */
    public static function generateReferenceCode(): string
    {
        $year = (int) date('Y');
        $prefix = sprintf('MC-%d-', $year);

        $maxExisting = (int) DB::table('incidents')
            ->where('reference_code', 'like', $prefix . '%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(reference_code FROM '[0-9]+$') AS INTEGER)), 0) AS max_number")
            ->first()
            ->max_number;

        $row = DB::selectOne('
            INSERT INTO incident_reference_counters (year, last_number, created_at, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT (year) DO UPDATE
            SET last_number = GREATEST(incident_reference_counters.last_number + 1, EXCLUDED.last_number),
                updated_at = CURRENT_TIMESTAMP
            RETURNING last_number
        ', [$year, $maxExisting + 1]);

        return sprintf('%s%04d', $prefix, (int) $row->last_number);
    }
}