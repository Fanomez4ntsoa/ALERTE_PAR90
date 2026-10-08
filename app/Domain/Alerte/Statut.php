<?php

namespace App\Domain\Alerte;

enum Statut: string
{
    case DANS_LIMITE = 'DANS_LIMITE';
    case HORS_LIMITE = 'HORS_LIMITE';

    public function libelle(): string
    {
        return match ($this) {
            self::DANS_LIMITE => 'Dans la limite',
            self::HORS_LIMITE => 'Hors limite',
        };
    }
}
