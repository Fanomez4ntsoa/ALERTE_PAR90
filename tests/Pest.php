<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Les tests Feature au format Pest utilisent le TestCase de Laravel.
| Les tests Unit restent purs : le moteur de calcul ne dépend pas du framework.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Comptes de test par rôle (étape 5)
|--------------------------------------------------------------------------
*/

function analyste(): User
{
    return User::query()->where('role', Role::ANALYSTE)->first()
        ?? User::factory()->analyste()->create(['name' => 'Analyste de test']);
}

function direction(): User
{
    return User::query()->where('role', Role::DIRECTION)->first()
        ?? User::factory()->direction()->create(['name' => 'Direction de test']);
}
