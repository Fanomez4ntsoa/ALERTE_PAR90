<?php

namespace App\Import;

/** Une ligne contrôlée de l'onglet « mensuel », prête à stocker. */
final readonly class LigneMensuelle
{
    /**
     * @param  string  $mois  AAAA-MM
     * @param  array<string, string|int|null>  $valeurs  colonnes de la table clotures : taux en chaînes à 6 décimales, montants en entiers
     */
    public function __construct(
        public string $mois,
        public array $valeurs,
    ) {}
}
