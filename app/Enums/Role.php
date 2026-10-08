<?php

namespace App\Enums;

/** Section 11 : l'analyste importe et annule ; la direction décide ; les deux consultent. */
enum Role: string
{
    case ANALYSTE = 'analyste';
    case DIRECTION = 'direction';

    public function libelle(): string
    {
        return match ($this) {
            self::ANALYSTE => 'Analyste',
            self::DIRECTION => 'Direction',
        };
    }
}
