<?php

namespace App\Http\Controllers;

use App\Models\Cloture;
use App\Models\Parametre;
use App\Tableau\Format;
use App\Tableau\TableauDeBord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TableauDeBordController extends Controller
{
    /** Onglets de la page, dans l'ordre ; le premier est celui par défaut. */
    public const ONGLETS = [
        'synthese' => 'Synthèse',
        'comprehension' => 'Compréhension',
        'generations' => 'Générations',
        'registre' => 'Registre',
    ];

    /** Affiche une clôture ; sans mois dans l'URL, la plus récente, sur l'onglet Synthèse. */
    public function __invoke(Request $request, ?string $mois = null, string $onglet = 'synthese'): Response
    {
        $clotures = Cloture::query()->with('parametres')->orderBy('mois')->get();

        $cloture = $mois === null
            ? $clotures->last()
            : $clotures->first(fn (Cloture $c) => $c->mois->format('Y-m') === $mois);

        if ($mois !== null && $cloture === null) {
            abort(404, "Aucune clôture pour {$mois}.");
        }

        $navigation = null;
        if ($cloture !== null) {
            $index = $clotures->search(fn (Cloture $c) => $c->is($cloture));
            $lien = fn (?Cloture $c) => $c === null ? null : [
                'valeur' => $c->mois->format('Y-m'),
                'libelle' => Format::mois($c->mois),
                'url' => self::adresse($c->mois->format('Y-m'), $onglet),
            ];
            $navigation = [
                'precedent' => $lien($clotures->get($index - 1)),
                'suivant' => $lien($clotures->get($index + 1)),
                'liste' => $clotures->reverse()->map($lien)->values()->all(),
            ];
        }

        $moisAffiche = $cloture?->mois->format('Y-m');

        return Inertia::render('TableauDeBord', [
            'institution' => Parametre::nomInstitution(),
            'onglet' => $onglet,
            'onglets' => array_map(fn (string $cle, string $libelle) => [
                'cle' => $cle,
                'libelle' => $libelle,
                'url' => $moisAffiche === null ? null : self::adresse($moisAffiche, $cle),
            ], array_keys(self::ONGLETS), self::ONGLETS),
            'navigation' => $navigation,
            'tableau' => $cloture === null ? null : (new TableauDeBord($cloture, $clotures, $request->user()))->donnees(),
        ]);
    }

    /** Adresse d'un mois sur un onglet ; Synthèse n'ajoute pas de suffixe. */
    public static function adresse(string $mois, string $onglet): string
    {
        return $onglet === array_key_first(self::ONGLETS)
            ? route('dashboard', ['mois' => $mois])
            : route('dashboard', ['mois' => $mois, 'onglet' => $onglet]);
    }
}
