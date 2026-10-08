<?php

namespace App\Domain\Alerte;

/** Classement d'un mois après h mois (section 9). */
enum ResultatVerification: string
{
    case HAUSSE_CONFIRMEE = 'HAUSSE_CONFIRMEE';
    case SIGNAL_NON_SUIVI = 'SIGNAL_NON_SUIVI';
    case HAUSSE_PROLONGEE = 'HAUSSE_PROLONGEE';
    case PAS_DE_NOUVELLE_HAUSSE = 'PAS_DE_NOUVELLE_HAUSSE';
    case HAUSSE_NON_SIGNALEE = 'HAUSSE_NON_SIGNALEE';
    case RIEN_A_SIGNALER = 'RIEN_A_SIGNALER';

    public function libelle(): string
    {
        return match ($this) {
            self::HAUSSE_CONFIRMEE => 'Hausse confirmée',
            self::SIGNAL_NON_SUIVI => 'Signal non suivi d\'une hausse',
            self::HAUSSE_PROLONGEE => 'La hausse s\'est prolongée',
            self::PAS_DE_NOUVELLE_HAUSSE => 'Pas de nouvelle hausse',
            self::HAUSSE_NON_SIGNALEE => 'Hausse non signalée',
            self::RIEN_A_SIGNALER => 'Rien à signaler',
        };
    }
}
