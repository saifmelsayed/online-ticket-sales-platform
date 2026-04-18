<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrganizerRepositoryInterface;
use App\Models\Organizer;
use Illuminate\Support\Collection;

class OrganizerRepository implements OrganizerRepositoryInterface
{
    public function pendingWithUser(): Collection
    {
        return Organizer::with('user')
            ->where('approval_status', 'pending')
            ->get();
    }

    public function approvedWithUser(): Collection
    {
        return Organizer::with('user')
            ->where('approval_status', 'approved')
            ->get();
    }

    public function findOrFail(int $id): Organizer
    {
        return Organizer::findOrFail($id);
    }
}
