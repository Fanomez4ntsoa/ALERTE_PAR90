<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Utilisateur de démonstration, pour la soutenance hors ligne. */
class UtilisateurDemoSeeder extends Seeder
{
    public const EMAIL = 'demo@alerte-par90.local';

    public const MOT_DE_PASSE = 'demo-par90';

    public function run(): void
    {
        $utilisateur = User::query()->firstOrNew(['email' => self::EMAIL]);
        $utilisateur->name = 'Démonstration';
        $utilisateur->password = self::MOT_DE_PASSE;
        $utilisateur->email_verified_at ??= Carbon::now();
        $utilisateur->save();
    }
}
