<?php

namespace App\Policies;

use App\Models\PoliceStation;
use App\Models\User;

class PoliceStationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, PoliceStation $policeStation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->police_station_id === $policeStation->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PoliceStation $policeStation): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PoliceStation $policeStation): bool
    {
        return $user->hasRole('admin');
    }
}
