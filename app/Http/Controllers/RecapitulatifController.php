<?php

namespace App\Http\Controllers;

use App\Enums\EtatDecision;
use App\Models\Cloture;
use App\Models\Parametre;
use App\Registre\LibelleVerification;
use App\Tableau\Format;
use App\Tableau\TableauDeBord;
use Inertia\Inertia;
use Inertia\Response;

/** Tableau récapitulatif de la vérification à trois mois, tous les mois (section 9). */
class RecapitulatifController extends Controller
{
    public function __invoke(): Response
    {
        $clotures = Cloture::query()->withCount([
            'decisions',
            'decisions as decisions_annulees_count' => fn ($q) => $q->where('etat', EtatDecision::ANNULEE),
        ])->orderBy('mois')->get();

        $lignes = $clotures->map(function (Cloture $c) {
            $code = LibelleVerification::code($c);

            return [
                'mois' => Format::mois($c->mois),
                'url' => route('dashboard', ['mois' => $c->mois->format('Y-m')]),
                'niveau' => $c->niveau->libelle(),
                'couleur' => TableauDeBord::COULEURS[$c->niveau->value],
                'statut' => $c->statut?->libelle() ?? Format::NON_RENSEIGNE,
                'hausseReelle' => Format::ecart($c->hausse_reelle_3m),
                'resultatCode' => $code,
                'resultat' => LibelleVerification::libelle($code),
                'decisions' => $c->decisions_count,
                'decisionsAnnulees' => match ($n = (int) $c->decisions_annulees_count) {
                    0 => null,
                    1 => '(dont 1 annulée)',
                    default => "(dont {$n} annulées)",
                },
            ];
        });

        $parCode = $lignes->countBy('resultatCode');
        $totaux = array_map(fn (string $code) => [
            'code' => $code,
            'libelle' => LibelleVerification::libelle($code),
            'nombre' => $parCode->get($code, 0),
            'estResultat' => LibelleVerification::estResultat($code),
        ], LibelleVerification::ORDRE);

        return Inertia::render('Recapitulatif', [
            'institution' => Parametre::nomInstitution(),
            'lignes' => $lignes->all(),
            'totaux' => $totaux,
            'urlTableau' => route('dashboard'),
        ]);
    }
}
