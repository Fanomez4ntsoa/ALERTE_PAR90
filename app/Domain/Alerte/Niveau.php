<?php

namespace App\Domain\Alerte;

enum Niveau: string
{
    case NORMAL = 'NORMAL';
    case VIGILANCE = 'VIGILANCE';
    case ALERTE = 'ALERTE';
    case NON_EVALUE = 'NON_EVALUE';

    public function libelle(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::VIGILANCE => 'Vigilance',
            self::ALERTE => 'Alerte',
            self::NON_EVALUE => 'Non évalué',
        };
    }
}
