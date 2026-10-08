<?php

namespace App\Enums;

/** Section 7 « Chargement initial ». */
enum ModeCalcul: string
{
    case RETROSPECTIF = 'retrospectif';
    case A_LA_CLOTURE = 'a_la_cloture';

    public function libelle(): string
    {
        return match ($this) {
            self::RETROSPECTIF => 'Calcul rétrospectif',
            self::A_LA_CLOTURE => 'Calcul à la clôture',
        };
    }
}
