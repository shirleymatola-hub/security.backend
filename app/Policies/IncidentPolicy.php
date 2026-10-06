<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('district_commander')) {
            return $user->district_id !== null;
        }

        if ($user->hasRole('squad_commander')) {
            return $user->police_station_id !== null;
        }

        if ($user->hasRole('post_commander')) {
            return $user->police_station_id !== null;
        }

        if ($user->hasRole('manager')) {
            return $user->police_station_id !== null;
        }

        if ($user->hasRole('police')) {
            return $user->police_station_id !== null;
        }

        if ($user->hasRole('sernic_officer')) {
            return true;
        }

        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('district_commander') && $user->district_id) {
            return $incident->policeStation
                && $incident->policeStation->district_id === $user->district_id;
        }

        if ($user->hasRole('squad_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('post_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('manager')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('police')) {
            return $incident->assigned_to === $user->id
                || $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('sernic_officer')) {
            return true;
        }

        return $incident->is_public || $incident->reported_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Incident $incident): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('district_commander') && $user->district_id) {
            return $incident->policeStation
                && $incident->policeStation->district_id === $user->district_id;
        }

        if ($user->hasRole('squad_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('post_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('manager')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('police')) {
            return $incident->assigned_to === $user->id;
        }

        return false;
    }

    public function assign(User $user, Incident $incident): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('district_commander') && $user->district_id) {
            return $incident->policeStation
                && $incident->policeStation->district_id === $user->district_id;
        }

        if ($user->hasRole('squad_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('post_commander')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        if ($user->hasRole('manager')) {
            return $incident->police_station_id === $user->police_station_id;
        }

        return false;
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->hasRole('admin');
    }
}
