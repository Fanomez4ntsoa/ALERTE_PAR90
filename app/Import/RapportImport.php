<?php

namespace App\Import;

use App\Models\Import;

/** Ce qu'un import a fait ; $import est null quand rien n'était à ajouter. */
final readonly class RapportImport
{
    /**
     * @param  list<string>  $moisAjoutes
     * @param  list<string>  $avertissements
     */
    public function __construct(
        public ?Import $import,
        public array $moisAjoutes,
        public int $generationsAjoutees,
        public int $cellulesEcartees,
        public array $avertissements,
    ) {}
}
