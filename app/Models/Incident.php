<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        $year = date('Y');
        $lastIncident = self::whereYear('created_at', $year)->latest('id')->first();
        $number = $lastIncident ? intval(substr($lastIncident->reference_code, -4)) + 1 : 1;
        return sprintf('MC-%s-%04d', $year, $number);
    }
}