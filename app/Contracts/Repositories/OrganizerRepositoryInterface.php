<?php

namespace App\Contracts\Repositories;

use App\Models\Organizer;
use Illuminate\Support\Collection;

interface OrganizerRepositoryInterface
{
    public function pendingWithUser(): Collection;

    public function approvedWithUser(): Collection;

    public function findOrFail(int $id): Organizer;
}
