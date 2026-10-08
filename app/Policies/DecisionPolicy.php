<?php

namespace App\Policies;

use App\Models\Decision;
use App\Models\User;

/** Section 11 : la direction ajoute, complète, corrige et annule les décisions. */
class DecisionPolicy
{
    public function create(User $user): bool
    {
        return $user->estDirection();
    }

    public function completer(User $user, Decision $decision): bool
    {
        return $user->estDirection();
    }

    public function corriger(User $user, Decision $decision): bool
    {
        return $user->estDirection();
    }

    public function annuler(User $user, Decision $decision): bool
    {
        return $user->estDirection();
    }
}
