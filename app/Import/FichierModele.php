<?php

namespace App\Import;

/** Contenu contrôlé d'un fichier modèle. */
final readonly class FichierModele
{
    /**
     * @param  list<LigneMensuelle>  $lignes  triées par mois
     * @param  list<CelluleGeneration>  $generations
     * @param  list<string>  $avertissements
     */
    public function __construct(
        public array $lignes,
        public array $generations,
        public int $cellulesEcartees,
        public array $avertissements,
    ) {}
}
