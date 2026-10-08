<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Comptes de démonstration, pour la soutenance hors ligne : un analyste et
 * une direction. Ils remplacent l'ancien compte unique de l'étape 3.
 */
class UtilisateurDemoSeeder extends Seeder
{
    public const ANCIEN_EMAIL = 'demo@alerte-par90.local';

    /** @var array<string, array{nom: string, role: Role, mot_de_passe: string}> */
    public const COMPTES = [
        'analyste@alerte-par90.local' => ['nom' => 'Analyste', 'role' => Role::ANALYSTE, 'mot_de_passe' => 'analyste-par90'],
        'direction@alerte-par90.local' => ['nom' => 'Direction', 'role' => Role::DIRECTION, 'mot_de_passe' => 'direction-par90'],
    ];

    public function run(): void
    {
        $ancien = User::query()->where('email', self::ANCIEN_EMAIL)->first();
        if ($ancien !== null) {
            // L'ancien compte n'a pu ni importer ni décider avec un rôle : on le retire,
            // sauf s'il a laissé une trace (décision ou import), auquel cas il perd tout accès.
            $ancien->decisionsSaisies()->exists() || $ancien->imports()->exists()
                ? $ancien->forceFill(['role' => null])->save()
                : $ancien->delete();
        }

        foreach (self::COMPTES as $email => $compte) {
            $utilisateur = User::query()->firstOrNew(['email' => $email]);
            $utilisateur->name = $compte['nom'];
            $utilisateur->role = $compte['role'];
            $utilisateur->password = $compte['mot_de_passe'];
            $utilisateur->email_verified_at ??= Carbon::now();
            $utilisateur->save();
        }
    }
}
