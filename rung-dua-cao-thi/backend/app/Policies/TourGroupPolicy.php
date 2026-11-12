<?php

namespace App\Policies;

use App\Models\TourGroup;
use App\Models\User;
use App\Role;

class TourGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TourGroup $tour): bool
    {
        return $user->role !== Role::Staff || $tour->tour_date->isToday();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TourGroup $tour): bool
    {
        if ($tour->status === 'LOCKED') {
            return false;
        }

        return $user->role !== Role::Staff
            || ($tour->created_by === $user->id && in_array($tour->status, ['DRAFT', 'PENDING'], true));
    }

    public function delete(User $user, TourGroup $tour): bool
    {
        return $user->role !== Role::Staff && $tour->status !== 'LOCKED';
    }
}
