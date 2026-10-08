<?php

namespace App\Domain\Alerte;

use InvalidArgumentException;

/**
 * Les valeurs d'un mois de clôture utiles au calcul.
 * Taux en % ; null = donnée absente, jamais remplacée par 0.
 */
final readonly class MoisCloture
{
    /**
     * @param  string  $mois  mois de clôture au format AAAA-MM
     */
    public function __construct(
        public string $mois,
        public float|string|null $par60,
        public float|string|null $par90,
        public float|string|null $par180 = null,
        public float|string|null $par365 = null,
        public float|string|null $rr6190 = null,
    ) {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            throw new InvalidArgumentException("Mois invalide, format attendu AAAA-MM : {$mois}.");
        }
        $valeurs = ['par60' => $par60, 'par90' => $par90, 'par180' => $par180, 'par365' => $par365, 'rr_61_90' => $rr6190];
        foreach ($valeurs as $nom => $valeur) {
            if (is_string($valeur) && ! is_numeric($valeur)) {
                throw new InvalidArgumentException("{$nom} non numérique pour {$mois} : {$valeur}.");
            }
        }
    }

    /** Rang du mois sur une échelle continue : deux mois consécutifs diffèrent de 1. */
    public function rang(): int
    {
        [$annee, $mois] = array_map('intval', explode('-', $this->mois));

        return $annee * 12 + $mois - 1;
    }
}
