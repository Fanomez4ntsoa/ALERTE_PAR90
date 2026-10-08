<?php

namespace App\Enums;

enum EtatDecision: string
{
    case OUVERTE = 'ouverte';
    case COMPLETEE = 'completee';
    case ANNULEE = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::OUVERTE => 'Ouverte',
            self::COMPLETEE => 'Complétée',
            self::ANNULEE => 'Annulée',
        };
    }
}
