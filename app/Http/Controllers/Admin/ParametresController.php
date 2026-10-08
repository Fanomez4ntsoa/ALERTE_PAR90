<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Tableau\Format;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Section Paramètres du back-office : jeu en vigueur, en lecture seule. */
class ParametresController extends Controller
{
    public function __invoke(): Response
    {
        $jeu = Parametre::query()
            ->whereDate('date_effet', '<=', Carbon::today())
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->first();

        // Avant le premier import, le jeu n'existe pas encore : on montre la configuration.
        $valeurs = $jeu?->only(['limite_par90', 'seuil_hausse_3m', 'centile_bond', 'minimum_bonds', 'horizon_verification', 'nom_institution'])
            ?? config('alerte');

        return Inertia::render('admin/Parametres', [
            'enregistre' => $jeu !== null,
            'dateEffet' => $jeu === null ? null : Format::date($jeu->date_effet),
            'institution' => (string) $valeurs['nom_institution'],
            'parametres' => [
                ['libelle' => 'Limite de niveau du PAR90', 'valeur' => Format::taux(Format::decimal((string) $valeurs['limite_par90']), 2)],
                ['libelle' => 'Seuil de hausse sur trois mois', 'valeur' => Format::points(Format::decimal((string) $valeurs['seuil_hausse_3m']), 2)],
                ['libelle' => 'Centile du bond', 'valeur' => "{$valeurs['centile_bond']}e centile"],
                ['libelle' => 'Nombre minimum de bonds passés', 'valeur' => "{$valeurs['minimum_bonds']} bonds"],
                ['libelle' => 'Horizon de vérification', 'valeur' => "{$valeurs['horizon_verification']} mois"],
            ],
        ]);
    }
}
