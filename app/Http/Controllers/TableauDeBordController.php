<?php

namespace App\Http\Controllers;

use App\Models\Cloture;
use App\Models\Parametre;
use App\Tableau\Format;
use App\Tableau\TableauDeBord;
use Inertia\Inertia;
use Inertia\Response;

class TableauDeBordController extends Controller
{
    /** Affiche une clôture ; sans mois dans l'URL, la plus récente. */
    public function __invoke(?string $mois = null): Response
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
                'url' => route('dashboard', ['mois' => $c->mois->format('Y-m')]),
            ];
            $navigation = [
                'precedent' => $lien($clotures->get($index - 1)),
                'suivant' => $lien($clotures->get($index + 1)),
                'liste' => $clotures->reverse()->map($lien)->values()->all(),
            ];
        }

        return Inertia::render('TableauDeBord', [
            'institution' => Parametre::nomInstitution(),
            'navigation' => $navigation,
            'tableau' => $cloture === null ? null : (new TableauDeBord($cloture, $clotures))->donnees(),
        ]);
    }
}
