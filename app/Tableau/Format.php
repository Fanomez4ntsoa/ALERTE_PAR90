<?php

namespace App\Tableau;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Mise en forme française pour l'affichage (section 5, « Précision »).
 * L'arrondi se fait ici seulement, sur les valeurs stockées, en décimal exact.
 */
final class Format
{
    public const NON_RENSEIGNE = 'n.r.';

    private const MOINS = '−';

    private const ESPACE = "\u{00A0}";

    /** Taux en % : « 6,25 % ». */
    public static function taux(?string $valeur, int $decimales = 2): string
    {
        return $valeur === null ? self::NON_RENSEIGNE : self::nombre($valeur, $decimales).self::ESPACE.'%';
    }

    /** Écart en points, signé : « −0,25 point ». */
    public static function ecart(?string $valeur, int $decimales = 2): string
    {
        if ($valeur === null) {
            return self::NON_RENSEIGNE;
        }
        $texte = self::nombre($valeur, $decimales);
        $signe = str_starts_with($texte, self::MOINS) || self::estNul($valeur, $decimales) ? '' : '+';

        return $signe.$texte.self::ESPACE.'point';
    }

    /** Valeur en points, sans signe imposé : « 0,135 point ». */
    public static function points(?string $valeur, int $decimales): string
    {
        return $valeur === null ? self::NON_RENSEIGNE : self::nombre($valeur, $decimales).self::ESPACE.'point';
    }

    /** Montant en ariary, affiché en milliards : « 12,79 Md Ar ». */
    public static function milliards(?int $ariary): string
    {
        return $ariary === null ? self::NON_RENSEIGNE : self::nombre(bcdiv((string) $ariary, '1000000000', 12), 2).self::ESPACE.'Md'.self::ESPACE.'Ar';
    }

    /** « juillet 2026 ». */
    public static function mois(CarbonInterface $mois): string
    {
        return $mois->copy()->locale('fr')->translatedFormat('F Y');
    }

    /** « juil. 26 », pour l'axe de la courbe. */
    public static function moisCourt(CarbonInterface $mois): string
    {
        return $mois->copy()->locale('fr')->translatedFormat('M y');
    }

    /** Nombre décimal arrondi au plus proche (demi vers l'extérieur), virgule décimale. */
    public static function nombre(string $valeur, int $decimales): string
    {
        $arrondi = self::arrondir($valeur, $decimales);
        $negatif = str_starts_with($arrondi, '-');
        $absolu = ltrim($arrondi, '-');
        [$entier, $fraction] = array_pad(explode('.', $absolu), 2, '');
        $entier = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', self::ESPACE, $entier);
        $texte = $fraction === '' ? $entier : "{$entier},{$fraction}";

        return ($negatif && ! self::estNul($valeur, $decimales) ? self::MOINS : '').$texte;
    }

    /**
     * Valeur décimale exacte, telle que stockée ; refuse tout ce qui n'est pas un nombre.
     *
     * @return numeric-string
     */
    public static function decimal(string $valeur): string
    {
        if (! is_numeric($valeur)) {
            throw new InvalidArgumentException("Valeur non numérique : {$valeur}.");
        }

        return $valeur;
    }

    private static function arrondir(string $valeur, int $decimales): string
    {
        $valeur = self::decimal($valeur);
        $demi = self::decimal('0.'.str_repeat('0', $decimales).'5');
        $decale = str_starts_with($valeur, '-') ? bcsub($valeur, $demi, $decimales + 1) : bcadd($valeur, $demi, $decimales + 1);

        return bcadd($decale, '0', $decimales);
    }

    private static function estNul(string $valeur, int $decimales): bool
    {
        return bccomp(self::decimal(ltrim(self::arrondir($valeur, $decimales), '-')), '0', $decimales) === 0;
    }
}
