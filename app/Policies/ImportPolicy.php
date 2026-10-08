<?php

namespace App\Policies;

use App\Models\User;

/** Section 11 : l'analyste importe et annule le dernier import. */
class ImportPolicy
{
    public function create(User $user): bool
    {
        return $user->estAnalyste();
    }

    public function annuler(User $user): bool
    {
        return $user->estAnalyste();
    }
}
