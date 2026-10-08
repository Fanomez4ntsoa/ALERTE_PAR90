<?php

namespace App\Domain\Alerte;

/**
 * Résultat du calcul pour un mois. Valeurs brutes, non arrondies :
 * taux en %, écarts en points, null quand une entrée manque.
 * Le seuil est arrondi à 8 décimales, valeur comparée au bond.
 */
final readonly class ResultatMois
{
    public function __construct(
        public string $mois,
        public ?float $tranche,
        public ?float $bond,
        public ?float $hausse3m,
        public ?float $seuil,
        public int $nombreBonds,
        public Niveau $niveau,
        public ?Statut $statut,
        public string $motif,
        /** Taux de passage connu : rr_61_90 de la ligne t−1. */
        public ?float $tauxPassage,
        /** par180 ÷ par90 × 100. */
        public ?float $part180,
        /** par365 ÷ par90 × 100. */
        public ?float $part365,
        /** par90(t+h) − par90(t) ; null tant que t+h manque. */
        public ?float $hausseReelle,
        /** null : en attente, ou sans objet pour un mois Non évalué. */
        public ?ResultatVerification $resultatVerification,
    ) {}
}
