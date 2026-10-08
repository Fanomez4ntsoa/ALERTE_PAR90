<?php

namespace Tests\Support;

use App\Domain\Alerte\MoisCloture;
use App\Domain\Alerte\Parametres;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Accès aux données réelles pour les tests de recette.
 * Le fichier est confidentiel et hors de git : s'il manque, les tests sont ignorés.
 */
final class DonneesReelles
{
    public const CHEMIN = __DIR__.'/../../data/donnees_chap3.xlsx';

    /** @var list<MoisCloture>|null */
    private static ?array $cache = null;

    public static function disponibles(): bool
    {
        return is_file(self::CHEMIN);
    }

    /** Paramètres lus dans config/alerte.php, sans démarrer Laravel. */
    public static function parametres(): Parametres
    {
        return Parametres::depuisConfig(require __DIR__.'/../../config/alerte.php');
    }

    /**
     * Onglet « mensuel » : une MoisCloture par ligne.
     *
     * @return list<MoisCloture>
     */
    public static function mois(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $lecteur = IOFactory::createReader('Xlsx');
        $lecteur->setReadDataOnly(true);
        $lecteur->setLoadSheetsOnly(['mensuel']);
        $lignes = $lecteur->load(self::CHEMIN)->getSheetByName('mensuel')->toArray(null, false, false, false);

        $entetes = array_flip(array_shift($lignes));
        foreach (['Mois', 'par60', 'par90', 'par180', 'par365', 'rr_61_90'] as $colonne) {
            if (! isset($entetes[$colonne])) {
                throw new RuntimeException("Colonne absente de l'onglet mensuel : {$colonne}.");
            }
        }

        $mois = [];
        foreach ($lignes as $ligne) {
            if ($ligne[$entetes['Mois']] === null) {
                continue;
            }
            $mois[] = new MoisCloture(
                mois: Date::excelToDateTimeObject($ligne[$entetes['Mois']])->format('Y-m'),
                par60: self::taux($ligne[$entetes['par60']]),
                par90: self::taux($ligne[$entetes['par90']]),
                par180: self::taux($ligne[$entetes['par180']]),
                par365: self::taux($ligne[$entetes['par365']]),
                rr6190: self::taux($ligne[$entetes['rr_61_90']]),
            );
        }

        return self::$cache = $mois;
    }

    private static function taux(mixed $cellule): float|string|null
    {
        return ($cellule === null || $cellule === '') ? null : $cellule;
    }
}
