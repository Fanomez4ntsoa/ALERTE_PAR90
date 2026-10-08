<?php

namespace App\Registre;

use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\ResultatVerification;
use App\Models\Cloture;

/**
 * Résultat de vérification à afficher pour une clôture (section 9) :
 * l'un des six résultats, « Sans objet » pour un mois Non évalué (toujours),
 * sinon « En attente » tant que t+3 n'est pas importé.
 */
final class LibelleVerification
{
    public const SANS_OBJET = 'SANS_OBJET';

    public const EN_ATTENTE = 'EN_ATTENTE';

    /** Ordre d'affichage des totaux ; les deux derniers ne sont pas des résultats. */
    public const ORDRE = [
        'HAUSSE_CONFIRMEE',
        'SIGNAL_NON_SUIVI',
        'HAUSSE_PROLONGEE',
        'PAS_DE_NOUVELLE_HAUSSE',
        'HAUSSE_NON_SIGNALEE',
        'RIEN_A_SIGNALER',
        self::SANS_OBJET,
        self::EN_ATTENTE,
    ];

    public static function code(Cloture $cloture): string
    {
        return match (true) {
            $cloture->niveau === Niveau::NON_EVALUE => self::SANS_OBJET,
            $cloture->hausse_reelle_3m === null => self::EN_ATTENTE,
            $cloture->resultat_verif === null => self::SANS_OBJET,
            default => $cloture->resultat_verif->value,
        };
    }

    public static function libelle(string $code): string
    {
        return match ($code) {
            self::SANS_OBJET => 'Sans objet',
            self::EN_ATTENTE => 'En attente',
            default => ResultatVerification::from($code)->libelle(),
        };
    }

    public static function estResultat(string $code): bool
    {
        return ! in_array($code, [self::SANS_OBJET, self::EN_ATTENTE], true);
    }
}
