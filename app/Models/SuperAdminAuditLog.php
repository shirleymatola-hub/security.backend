<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdminAuditLog extends Model
{
    protected $table = 'super_admin_audit_logs';

    protected $fillable = [
        'super_admin_user_id',
        'event',
        'entity_type',
        'entity_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function superAdminUser(): BelongsTo
    {
        return $this->belongsTo(SuperAdminUser::class);
    }
}
