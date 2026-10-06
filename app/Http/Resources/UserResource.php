<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Ordem de prioridade usada para decidir o painel do utilizador
     * (igual à ordem de redirecionamento do login original).
     */
    public const ROLE_PRIORITY = [
        'admin',
        'district_commander',
        'squad_commander',
        'post_commander',
        'manager',
        'police',
        'sernic_officer',
        'citizen',
    ];

    public static function primaryRole(User $user): ?string
    {
        $roles = $user->getRoleNames();

        foreach (self::ROLE_PRIORITY as $role) {
            if ($roles->contains($role)) {
                return $role;
            }
        }

        return $roles->first();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => self::primaryRole($this->resource),
            'roles' => $this->getRoleNames()->values(),
            'status' => $this->status,
            'theme' => $this->theme ?: 'light',
            'locale' => $this->locale,
            'auth_type' => $this->auth_type,
            'agent_number' => $this->agent_number,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'police_station_id' => $this->police_station_id,
            'district_id' => $this->district_id,
            'police_station' => $this->whenLoaded('policeStation', fn () => $this->policeStation ? [
                'id' => $this->policeStation->id,
                'name' => $this->policeStation->name,
            ] : null),
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->profile_photo
                ? Storage::disk('public')->url($this->profile_photo)
                : null,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at,
        ];
    }
}
