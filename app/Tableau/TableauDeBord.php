<?php

namespace App\Tableau;

use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\Statut;
use App\Enums\EtatDecision;
use App\Enums\ModeCalcul;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\Generation;
use App\Models\User;
use App\Registre\LibelleVerification;
use Illuminate\Support\Collection;

/**
 * Prépare les données des blocs 1 à 5 (sections 6 et 8) pour une clôture.
 * Tout ce qui compare ou met en forme se fait ici : la page Vue affiche.
 * Les valeurs viennent des colonnes stockées, figées à l'import.
 */
final class TableauDeBord
{
    /** Bloc 1 : action attendue par niveau (section 6). */
    private const ACTIONS = [
        'NORMAL' => 'Suivi mensuel habituel',
        'VIGILANCE' => 'Revue ciblée par la direction avant la clôture suivante : créances en 61–90 jours, générations récentes',
        'ALERTE' => 'Revue prioritaire par la direction avant la clôture suivante : action, responsable, échéance',
        'NON_EVALUE' => 'Aucune',
    ];

    public const COULEURS = [
        'NORMAL' => 'vert',
        'VIGILANCE' => 'jaune',
        'ALERTE' => 'rouge',
        'NON_EVALUE' => 'gris',
    ];

    /**
     * Teinte des points de la courbe par niveau ; neutre pour Normal et Non évalué.
     * Ce sont des noms : le navigateur les associe aux couleurs du thème.
     */
    private const COULEURS_POINTS = [
        'ALERTE' => 'alerte',
        'VIGILANCE' => 'vigilance',
        'NORMAL' => 'neutre',
        'NON_EVALUE' => 'neutre',
    ];

    private const NOMBRE_GENERATIONS = 12;

    private const AGE_MAX = 12;

    /**
     * @param  Collection<int, Cloture>  $clotures  toutes les clôtures, triées par mois, avec leurs paramètres
     */
    public function __construct(
        private readonly Cloture $cloture,
        private readonly Collection $clotures,
        private readonly User $utilisateur,
    ) {}

    /** @return array<string, mixed> */
    public function donnees(): array
    {
        return [
            'mois' => ['valeur' => $this->cle($this->cloture), 'libelle' => Format::mois($this->cloture->mois)],
            'bandeau' => $this->bandeau(),
            'risque' => $this->risque(),
            'trajectoire' => $this->trajectoire(),
            'signal' => $this->signal(),
            'comprehension' => $this->comprehension(),
            'generations' => $this->generations(),
            'registre' => $this->registre(),
        ];
    }

    /** @return array<string, mixed> */
    private function bandeau(): array
    {
        $c = $this->cloture;
        $horsLimite = $c->statut === Statut::HORS_LIMITE;

        return [
            'niveau' => $c->niveau->value,
            'libelle' => $c->niveau->libelle(),
            'couleur' => self::COULEURS[$c->niveau->value],
            'motif' => $c->motif,
            'action' => self::ACTIONS[$c->niveau->value],
            'statut' => [
                'code' => $c->statut?->value,
                'libelle' => $c->statut?->libelle() ?? Format::NON_RENSEIGNE,
                'texte' => $horsLimite
                    ? 'Le PAR90 dépasse la limite de '.$this->limite().'. Point sur le plan de traitement du stock ancien.'
                    : null,
            ],
            'mention' => $c->mode_calcul === ModeCalcul::RETROSPECTIF ? 'calcul rétrospectif' : null,
        ];
    }

    /** @return array<string, mixed> */
    private function risque(): array
    {
        $index = $this->clotures->search(fn (Cloture $c) => $c->is($this->cloture));

        return [
            'par90' => Format::taux($this->cloture->par90),
            'montant' => Format::milliards($this->cloture->par90_mt),
            'limite' => $this->limite(),
            'courbe' => [
                'etiquettes' => $this->clotures->map(fn (Cloture $c) => Format::moisCourt($c->mois))->values()->all(),
                'valeurs' => $this->clotures->map(fn (Cloture $c) => (float) $c->par90)->values()->all(),
                'infobulles' => $this->clotures->map(fn (Cloture $c) => Format::mois($c->mois).' : '.Format::taux($c->par90).', '.mb_strtolower($c->niveau->libelle()))->values()->all(),
                'limite' => (float) $this->cloture->parametres->limite_par90,
                'libelleLimite' => 'Limite '.$this->limite(),
                'indexMois' => $index === false ? null : $index,
                'couleurs' => $this->clotures->map(fn (Cloture $c) => self::COULEURS_POINTS[$c->niveau->value])->values()->all(),
                'legende' => [
                    ['libelle' => 'Alerte', 'couleur' => self::COULEURS_POINTS['ALERTE']],
                    ['libelle' => 'Vigilance', 'couleur' => self::COULEURS_POINTS['VIGILANCE']],
                    ['libelle' => 'Normal ou non évalué', 'couleur' => self::COULEURS_POINTS['NORMAL']],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function trajectoire(): array
    {
        $c = $this->cloture;
        $seuil = $c->parametres->seuil_hausse_3m;
        $atteint = $c->niveau === Niveau::ALERTE;

        $jauge = null;
        if ($c->hausse_3m !== null) {
            $borne = $this->borneJauge();
            $position = fn (float $v) => round(($v + $borne) / (2 * $borne) * 100, 2);
            $jauge = [
                'hausse' => $position((float) $c->hausse_3m),
                'seuil' => $position((float) $seuil),
                'zero' => 50.0,
            ];
        }

        return [
            'hausse' => Format::ecart($c->hausse_3m),
            'seuil' => Format::ecart($seuil),
            'atteint' => $atteint,
            'texte' => match (true) {
                $c->hausse_3m === null => 'Hausse non calculable : historique insuffisant ou donnée manquante.',
                $atteint => 'Hausse au niveau du seuil ou au-dessus.',
                default => 'Hausse sous le seuil.',
            },
            'jauge' => $jauge,
        ];
    }

    /** @return array<string, mixed> */
    private function signal(): array
    {
        $c = $this->cloture;
        if ($c->seuil_bond === null) {
            return [
                'disponible' => false,
                'message' => 'Données insuffisantes pour le signal avancé',
                'nombreBonds' => $c->nb_bonds,
                'libelleBonds' => $c->nb_bonds.($c->nb_bonds > 1 ? ' bonds passés' : ' bond passé'),
            ];
        }

        // Même comparaison que le moteur : bond stocké contre seuil stocké à 8 décimales.
        $allume = $c->bond !== null && bccomp(Format::decimal($c->bond), Format::decimal($c->seuil_bond), 8) >= 0;

        return [
            'disponible' => true,
            'bond' => Format::ecart($c->bond, 3),
            'seuil' => Format::points($c->seuil_bond, 3),
            'voyant' => $allume,
            'libelleVoyant' => $allume ? 'Allumé' : 'Éteint',
            'nombreBonds' => $c->nb_bonds,
            'message' => $c->bond === null ? 'Bond non calculable ce mois-ci' : null,
        ];
    }

    /**
     * Bloc 5 : huit indicateurs sans seuil, valeur du mois et h mois plus tôt.
     *
     * @return array<string, mixed>
     */
    private function comprehension(): array
    {
        $avant = $this->clotureDecalee(-$this->cloture->parametres->horizon_verification);
        $taux = fn (string $colonne, int $decimales = 2) => [
            fn (?Cloture $c) => $c?->{$colonne},
            fn (?string $v) => Format::taux($v, $decimales),
        ];

        $definitions = [
            'PAR30' => $taux('par30'),
            'PAR60' => $taux('par60'),
            "Taux d'impayés" => $taux('impaye'),
            'Tranche 61–90' => $taux('tranche_61_90'),
            'Part des retards de plus de 180 jours dans le PAR90' => $taux('part_180', 1),
            "Part des retards de plus d'un an dans le PAR90" => $taux('part_365', 1),
            'Taux de passage connu' => $taux('rr_connu'),
            'Radiations du mois' => [
                fn (?Cloture $c) => $c?->radiations === null ? null : (string) $c->radiations,
                fn (?string $v) => Format::milliards($v === null ? null : (int) $v),
            ],
        ];

        $indicateurs = [];
        foreach ($definitions as $libelle => [$lire, $formater]) {
            $maintenant = $lire($this->cloture);
            $precedent = $lire($avant);
            $evolution = ($maintenant === null || $precedent === null) ? null : match (bccomp(Format::decimal($maintenant), Format::decimal($precedent), 8)) {
                1 => 'hausse',
                -1 => 'baisse',
                default => 'stable',
            };
            $indicateurs[] = [
                'libelle' => $libelle,
                'valeur' => $formater($maintenant),
                'precedente' => $formater($precedent),
                'evolution' => $evolution,
                'symbole' => match ($evolution) {
                    'hausse' => '↑',
                    'baisse' => '↓',
                    'stable' => '=',
                    null => '·',
                },
            ];
        }

        return [
            'moisPrecedent' => Format::mois($this->cloture->mois->copy()->subMonthsNoOverflow($this->cloture->parametres->horizon_verification)),
            'indicateurs' => $indicateurs,
        ];
    }

    /**
     * Les 12 derniers mois d'octroi jusqu'au mois affiché ; seules les cellules
     * connues à cette clôture (octroi + âge ≤ mois affiché) sont montrées.
     *
     * @return array<string, mixed>
     */
    private function generations(): array
    {
        $fin = $this->cloture->mois->copy()->startOfMonth();
        $debut = $fin->copy()->subMonthsNoOverflow(self::NOMBRE_GENERATIONS - 1);
        $cellules = Generation::query()
            ->whereDate('mois_octroi', '>=', $debut)
            ->whereDate('mois_octroi', '<=', $fin)
            ->get()
            ->keyBy(fn (Generation $g) => $g->mois_octroi->format('Y-m').'|'.$g->age_mois);

        $lignes = [];
        for ($octroi = $debut->toImmutable(); $octroi <= $fin; $octroi = $octroi->addMonthNoOverflow()) {
            $valeurs = [];
            foreach (range(0, self::AGE_MAX) as $age) {
                if ($octroi->copy()->addMonthsNoOverflow($age) > $fin) {
                    $valeurs[] = null;

                    continue;
                }
                $generation = $cellules->get($octroi->format('Y-m').'|'.$age);
                $valeurs[] = $generation?->par30 === null ? Format::NON_RENSEIGNE : Format::nombre($generation->par30, 2);
            }
            $lignes[] = ['mois' => Format::moisCourt($octroi), 'cellules' => $valeurs];
        }

        return ['ages' => range(0, self::AGE_MAX), 'lignes' => $lignes];
    }

    /**
     * Bloc 6 : décisions de la clôture affichée, et signal émis h mois plus tôt
     * avec sa vérification et les décisions prises à l'époque (section 9).
     *
     * @return array<string, mixed>
     */
    private function registre(): array
    {
        $horizon = $this->cloture->parametres->horizon_verification;
        $avant = $this->clotureDecalee(-$horizon);

        $signal = null;
        if ($avant !== null) {
            $code = LibelleVerification::code($avant);
            $signal = [
                'mois' => Format::mois($avant->mois),
                'url' => route('dashboard', ['mois' => $this->cle($avant)]),
                'niveau' => $avant->niveau->libelle(),
                'couleur' => self::COULEURS[$avant->niveau->value],
                'par90Alors' => Format::taux($avant->par90),
                'par90Aujourdhui' => Format::taux($this->cloture->par90),
                'hausseReelle' => Format::ecart($avant->hausse_reelle_3m),
                'resultatCode' => $code,
                'resultat' => LibelleVerification::libelle($code),
                'decisions' => $this->decisions($avant, false),
            ];
        }

        return [
            'decisions' => $this->decisions($this->cloture, true),
            'urlAjout' => $this->utilisateur->can('create', Decision::class)
                ? route('decisions.store', ['mois' => $this->cle($this->cloture)])
                : null,
            'moisSignal' => Format::mois($this->cloture->mois->subMonthsNoOverflow($horizon)),
            'signal' => $signal,
            'urlRecapitulatif' => route('recapitulatif'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function decisions(Cloture $cloture, bool $modifiables): array
    {

        return array_values($cloture->decisions()
            ->with(['auteur', 'completeur', 'annulateur'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Decision $d) => [
                'id' => $d->id,
                'action' => $d->action,
                'responsable' => $d->responsable,
                'echeance' => Format::date($d->echeance),
                'etat' => $d->etat->value,
                'libelleEtat' => $d->etat->libelle(),
                'resultat' => $d->resultat,
                'motifAnnulation' => $d->motif_annulation,
                'saisie' => 'Saisie le '.Format::horodatage($d->created_at, false).' par '.$d->auteur->name,
                'complement' => $d->complete_le === null ? null : 'Complétée le '.Format::horodatage($d->complete_le, false).' par '.$d->completeur?->name,
                'annulation' => $d->annule_le === null ? null : 'Annulée le '.Format::horodatage($d->annule_le, false).' par '.$d->annulateur?->name,
                'urlCompleter' => $modifiables && $d->etat === EtatDecision::OUVERTE && $this->utilisateur->can('completer', $d)
                    ? route('decisions.completer', $d) : null,
                'urlCorriger' => $modifiables && $d->etat === EtatDecision::COMPLETEE && $this->utilisateur->can('corriger', $d)
                    ? route('decisions.corriger', $d) : null,
                'urlAnnuler' => $modifiables && $d->etat === EtatDecision::OUVERTE && $this->utilisateur->can('annuler', $d)
                    ? route('decisions.annuler', $d) : null,
            ])
            ->all());
    }

    /**
     * Borne de la jauge du bloc 3, la même pour toutes les clôtures : la plus
     * grande hausse de l'historique ou le plus grand seuil, arrondie au quart
     * de point supérieur, avec une marge. Le repère du seuil ne bouge donc pas.
     */
    private function borneJauge(): float
    {
        $maximum = $this->clotures->reduce(
            fn (float $max, Cloture $c) => max($max, abs((float) $c->hausse_3m), (float) $c->parametres->seuil_hausse_3m),
            0.0,
        );

        return ceil($maximum * 1.1 * 4) / 4;
    }

    private function clotureDecalee(int $mois): ?Cloture
    {
        $cible = $this->cloture->mois->copy()->addMonthsNoOverflow($mois)->format('Y-m');

        return $this->clotures->first(fn (Cloture $c) => $this->cle($c) === $cible);
    }

    private function limite(): string
    {
        $nombre = (string) preg_replace('/,?0+$/', '', Format::nombre($this->cloture->parametres->limite_par90, 2));

        return $nombre."\u{00A0}%";
    }

    private function cle(Cloture $cloture): string
    {
        return $cloture->mois->format('Y-m');
    }
}
