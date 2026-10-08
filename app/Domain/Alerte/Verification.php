<?php

namespace App\Domain\Alerte;

/** Vérification à h mois d'un mois de clôture (section 9). */
final readonly class Verification
{
    public function __construct(
        public ?float $hausseReelle,
        public ?ResultatVerification $resultat,
    ) {}

    public function enAttente(): bool
    {
        return $this->hausseReelle === null;
    }
}
