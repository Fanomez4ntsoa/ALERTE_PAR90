<?php

namespace Tests\Support;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Fabrique à la volée des fichiers modèles à partir de données inventées.
 * Jamais à partir du fichier réel.
 */
final class FabriqueFichier
{
    public const COLONNES = ['Mois', 'encours', 'par30', 'par60', 'par90', 'par180', 'par365', 'par90_mt', 'impaye', 'radiations', 'rr_61_90'];

    /** @var list<string> */
    private static array $fichiers = [];

    /**
     * Série mensuelle inventée, cohérente avec les contrôles.
     *
     * @return array<string, array<string, mixed>> lignes indexées par mois AAAA-MM
     */
    public static function serie(string $debut = '2024-01', int $nombre = 20): array
    {
        $lignes = [];
        $mois = new DateTimeImmutable("{$debut}-01");
        for ($k = 0; $k < $nombre; $k++) {
            $par90 = 4.0 + 0.3 * ($k % 4) + 0.01 * $k;
            $par60 = $par90 + 1.0 + 0.2 * ($k % 3);
            $encours = 10_000_000_000 + 50_000_000 * $k;
            $lignes[$mois->format('Y-m')] = [
                'Mois' => $mois->format('Y-m-d'),
                'encours' => $encours,
                'par30' => $par60 + 2.0,
                'par60' => $par60,
                'par90' => $par90,
                'par180' => $par90 * 0.6,
                'par365' => $par90 * 0.3,
                'par90_mt' => round($par90 / 100 * $encours),
                'impaye' => 3.5,
                'radiations' => 25_000_000,
                'rr_61_90' => 50.0 + $k,
            ];
            $mois = $mois->modify('+1 month');
        }

        return $lignes;
    }

    /**
     * Générations inventées : chaque mois d'octroi a ses 13 âges remplis,
     * y compris au-delà du dernier mois (zéros), pour vérifier qu'ils sont écartés.
     *
     * @param  list<string>  $moisOctroi  AAAA-MM
     * @return array<string, list<float>>
     */
    public static function generations(array $moisOctroi, string $dernierMois): array
    {
        $rangDernier = self::rang($dernierMois);
        $lignes = [];
        foreach ($moisOctroi as $mois) {
            foreach (range(0, 12) as $age) {
                $lignes[$mois][$age] = self::rang($mois) + $age > $rangDernier ? 0.0 : 0.5 + 0.25 * $age;
            }
        }

        return $lignes;
    }

    /**
     * Écrit un fichier .xlsx temporaire.
     *
     * @param  array<string, array<string, mixed>>  $mensuel
     * @param  array<string, list<float>>|null  $generations  par défaut : une génération par mois de $mensuel
     * @param  list<string>  $sansColonnes  colonnes de « mensuel » à omettre
     * @param  list<string>  $sansOnglets
     */
    public static function ecrire(array $mensuel, ?array $generations = null, array $sansColonnes = [], array $sansOnglets = []): string
    {
        $mois = [];
        foreach ($mensuel as $ligne) {
            if (is_string($ligne['Mois']) && preg_match('/^\d{4}-\d{2}-01$/', $ligne['Mois'])) {
                $mois[substr($ligne['Mois'], 0, 7)] = substr($ligne['Mois'], 0, 7);
            }
        }
        $mois = array_values($mois);
        $generations ??= self::generations($mois, max($mois));

        $classeur = new Spreadsheet;
        $classeur->removeSheetByIndex(0);

        if (! in_array('mensuel', $sansOnglets, true)) {
            $colonnes = array_values(array_diff(self::COLONNES, $sansColonnes));
            $lignes = [$colonnes];
            foreach ($mensuel as $ligne) {
                $lignes[] = array_map(fn (string $c) => $c === 'Mois' ? self::date($ligne['Mois']) : ($ligne[$c] ?? null), $colonnes);
            }
            $classeur->createSheet()->setTitle('mensuel')->fromArray($lignes, null, 'A1', true);
        }

        foreach (['vintage_par30' => 1.0, 'vintage_par90' => 0.5] as $onglet => $facteur) {
            if (in_array($onglet, $sansOnglets, true)) {
                continue;
            }
            $lignes = [['Mois', ...array_map(fn ($a) => "age_{$a}", range(0, 12))]];
            foreach ($generations as $moisOctroi => $valeurs) {
                $lignes[] = [self::date("{$moisOctroi}-01"), ...array_map(fn ($v) => $v === null ? null : $v * $facteur, $valeurs)];
            }
            $classeur->createSheet()->setTitle($onglet)->fromArray($lignes, null, 'A1', true);
        }

        $chemin = sys_get_temp_dir().'/alerte-test-'.bin2hex(random_bytes(6)).'.xlsx';
        (new Xlsx($classeur))->save($chemin);
        self::$fichiers[] = $chemin;

        return $chemin;
    }

    public static function nettoyer(): void
    {
        foreach (self::$fichiers as $chemin) {
            @unlink($chemin);
        }
        self::$fichiers = [];
    }

    /** Une date AAAA-MM-JJ devient un numéro de série Excel ; toute autre valeur est écrite telle quelle. */
    private static function date(mixed $valeur): mixed
    {
        if (is_string($valeur) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return Date::PHPToExcel(new DateTimeImmutable($valeur));
        }

        return $valeur;
    }

    private static function rang(string $mois): int
    {
        [$annee, $numero] = array_map('intval', explode('-', substr($mois, 0, 7)));

        return $annee * 12 + $numero - 1;
    }
}
