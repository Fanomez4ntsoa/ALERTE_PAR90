<?php

namespace App\Import;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Lit le fichier modèle et applique les contrôles de la section 4 qui ne
 * dépendent que du fichier. Les contrôles qui comparent à la base
 * (mois manquant, mois déjà en base) sont faits par ServiceImport.
 */
class LecteurFichierModele
{
    public const ONGLET_MENSUEL = 'mensuel';

    public const ONGLETS_GENERATIONS = ['par30' => 'vintage_par30', 'par90' => 'vintage_par90'];

    /** Colonnes dont la valeur est obligatoire. */
    private const OBLIGATOIRES = ['encours', 'par30', 'par60', 'par90', 'par90_mt'];

    /** Colonnes facultatives : vide = absent. */
    private const FACULTATIVES = ['par180', 'par365', 'impaye', 'radiations', 'rr_61_90'];

    private const MONTANTS = ['encours', 'par90_mt', 'radiations'];

    /** par30 ≥ par60 ≥ par90 ≥ par180 ≥ par365. */
    private const ORDRE = ['par30', 'par60', 'par90', 'par180', 'par365'];

    private const AGE_MAX = 12;

    /** Écart toléré entre par90_mt et par90 × encours, en % (config/alerte.php). */
    private readonly float $tolerancePar90Mt;

    /** @var list<string> */
    private array $erreurs = [];

    /** @var list<string> */
    private array $avertissements = [];

    public function __construct(?float $tolerancePar90Mt = null)
    {
        $tolerance = $tolerancePar90Mt ?? config('alerte.tolerance_par90_mt');
        if (! is_numeric($tolerance) || $tolerance < 0) {
            throw new InvalidArgumentException('Paramètre absent ou invalide : tolerance_par90_mt.');
        }
        $this->tolerancePar90Mt = (float) $tolerance;
    }

    /**
     * @param  string|null  $dernierMoisEnBase  AAAA-MM, pour fixer le dernier mois de clôture
     *
     * @throws FichierRefuse
     */
    public function lire(string $chemin, DateTimeInterface $aujourdhui, ?string $dernierMoisEnBase = null): FichierModele
    {
        $this->erreurs = [];
        $this->avertissements = [];

        try {
            $lecteur = IOFactory::createReaderForFile($chemin);
            $lecteur->setReadDataOnly(true);
            $classeur = $lecteur->load($chemin);
        } catch (Throwable $e) {
            throw new FichierRefuse(["Fichier illisible : {$e->getMessage()}"]);
        }

        $onglets = [];
        foreach ([self::ONGLET_MENSUEL, ...self::ONGLETS_GENERATIONS] as $nom) {
            $feuille = $classeur->getSheetByName($nom);
            if ($feuille === null) {
                $this->erreurs[] = "Onglet obligatoire absent : {$nom}.";

                continue;
            }
            $onglets[$nom] = $feuille->toArray(null, false, false, false);
        }
        $this->arreterSiErreurs();

        $lignes = $this->lireMensuel($onglets[self::ONGLET_MENSUEL] ?? [], $aujourdhui);
        $this->arreterSiErreurs();

        $moisFichier = array_map(fn (LigneMensuelle $l) => $l->mois, $lignes);
        $dernierMois = max([$dernierMoisEnBase ?? '', ...$moisFichier]);

        [$generations, $ecartees] = $this->lireGenerations($onglets, $dernierMois, $aujourdhui);
        $this->arreterSiErreurs();

        return new FichierModele($lignes, $generations, $ecartees, $this->avertissements);
    }

    /**
     * @param  array<int, array<int, mixed>>  $tableau
     * @return list<LigneMensuelle>
     */
    private function lireMensuel(array $tableau, DateTimeInterface $aujourdhui): array
    {
        $onglet = self::ONGLET_MENSUEL;
        $colonnes = $this->entetes($tableau, $onglet, ['Mois', ...self::OBLIGATOIRES]);
        if ($colonnes === null) {
            return [];
        }
        foreach (self::FACULTATIVES as $nom) {
            if (! isset($colonnes[$nom])) {
                $this->avertissements[] = "Colonne facultative absente de l'onglet {$onglet} : {$nom}, enregistrée comme non renseignée.";
            }
        }

        $lignes = [];
        foreach (array_slice($tableau, 1, null, true) as $index => $cellules) {
            $numero = $index + 1;
            if ($this->ligneVide($cellules)) {
                continue;
            }

            $mois = $this->mois($cellules[$colonnes['Mois']] ?? null, "{$onglet}, ligne {$numero}", $aujourdhui);
            if ($mois !== null && isset($lignes[$mois])) {
                $this->erreurs[] = "{$onglet}, ligne {$numero} : mois {$mois} en double.";

                continue;
            }

            $valeurs = [];
            foreach ([...self::OBLIGATOIRES, ...self::FACULTATIVES] as $nom) {
                $brut = isset($colonnes[$nom]) ? ($cellules[$colonnes[$nom]] ?? null) : null;
                $obligatoire = in_array($nom, self::OBLIGATOIRES, true);
                $valeurs[$nom] = in_array($nom, self::MONTANTS, true)
                    ? $this->montant($brut, $nom, $obligatoire, "{$onglet}, ligne {$numero}")
                    : $this->taux($brut, $nom, $obligatoire, "{$onglet}, ligne {$numero}");
            }

            if ($mois === null) {
                continue;
            }
            $this->controlerOrdre($valeurs, $mois);
            $this->controlerMontantPar90($valeurs, $mois);
            if ($valeurs['radiations'] === null && isset($colonnes['radiations'])) {
                $this->avertissements[] = "{$mois} : radiations non renseignées (n.r.).";
            }

            $lignes[$mois] = new LigneMensuelle($mois, $valeurs);
        }

        if ($lignes === [] && $this->erreurs === []) {
            $this->erreurs[] = "Onglet {$onglet} sans aucune ligne.";
        }
        ksort($lignes);

        return array_values($lignes);
    }

    /**
     * Fusionne les deux onglets de générations. Toute cellule dont le mois
     * d'octroi plus l'âge dépasse le dernier mois de clôture est écartée,
     * même si elle vaut zéro.
     *
     * @param  array<string, array<int, array<int, mixed>>>  $onglets
     * @return array{0: list<CelluleGeneration>, 1: int}
     */
    private function lireGenerations(array $onglets, string $dernierMois, DateTimeInterface $aujourdhui): array
    {
        $ages = array_map(fn (int $a) => "age_{$a}", range(0, self::AGE_MAX));
        $rangDernier = self::rang($dernierMois);
        $cellules = [];
        $ecartees = [];

        foreach (self::ONGLETS_GENERATIONS as $taux => $onglet) {
            $tableau = $onglets[$onglet] ?? [];
            $colonnes = $this->entetes($tableau, $onglet, ['Mois', ...$ages]);
            if ($colonnes === null) {
                continue;
            }

            $vus = [];
            foreach (array_slice($tableau, 1, null, true) as $index => $ligne) {
                $numero = $index + 1;
                if ($this->ligneVide($ligne)) {
                    continue;
                }
                $mois = $this->mois($ligne[$colonnes['Mois']] ?? null, "{$onglet}, ligne {$numero}", $aujourdhui);
                if ($mois === null) {
                    continue;
                }
                if (isset($vus[$mois])) {
                    $this->erreurs[] = "{$onglet}, ligne {$numero} : mois d'octroi {$mois} en double.";

                    continue;
                }
                $vus[$mois] = true;

                foreach (range(0, self::AGE_MAX) as $age) {
                    if (self::rang($mois) + $age > $rangDernier) {
                        $ecartees["{$mois}|{$age}"] = true;

                        continue;
                    }
                    $valeur = $this->taux($ligne[$colonnes["age_{$age}"]] ?? null, $taux, false, "{$onglet}, ligne {$numero}, age_{$age}");
                    $cellules["{$mois}|{$age}"][$taux] = $valeur;
                }
            }
        }

        $generations = [];
        foreach ($cellules as $cle => $valeurs) {
            if (($valeurs['par30'] ?? null) === null && ($valeurs['par90'] ?? null) === null) {
                continue;
            }
            [$mois, $age] = explode('|', $cle);
            $generations[] = new CelluleGeneration($mois, (int) $age, $valeurs['par30'] ?? null, $valeurs['par90'] ?? null);
        }

        return [$generations, count($ecartees)];
    }

    /**
     * @param  array<int, array<int, mixed>>  $tableau
     * @param  list<string>  $requises
     * @return array<string, int>|null
     */
    private function entetes(array $tableau, string $onglet, array $requises): ?array
    {
        $colonnes = [];
        foreach ($tableau[0] ?? [] as $index => $nom) {
            if (is_string($nom) && trim($nom) !== '') {
                $colonnes[trim($nom)] ??= $index;
            }
        }

        $absentes = array_values(array_diff($requises, array_keys($colonnes)));
        foreach ($absentes as $nom) {
            $this->erreurs[] = "Colonne obligatoire absente de l'onglet {$onglet} : {$nom}.";
        }

        return $absentes === [] ? $colonnes : null;
    }

    /** Mois de clôture : premier jour du mois, pas après la date du jour. Rend AAAA-MM. */
    private function mois(mixed $brut, string $ou, DateTimeInterface $aujourdhui): ?string
    {
        $date = null;
        try {
            if (is_int($brut) || is_float($brut)) {
                $date = DateTimeImmutable::createFromMutable(Date::excelToDateTimeObject($brut));
            } elseif ($brut instanceof DateTimeInterface) {
                $date = DateTimeImmutable::createFromInterface($brut);
            } elseif (is_string($brut) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($brut))) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($brut)) ?: null;
                if ($date !== null && $date->format('Y-m-d') !== trim($brut)) {
                    $date = null;
                }
            }
        } catch (Throwable) {
            $date = null;
        }

        if ($date === null || $date->format('d H:i:s') !== '01 00:00:00') {
            $this->erreurs[] = "{$ou} : Mois invalide (attendu : premier jour du mois) : ".self::afficher($brut).'.';

            return null;
        }
        if ($date->format('Y-m-d') > $aujourdhui->format('Y-m-d')) {
            $this->erreurs[] = "{$ou} : mois {$date->format('Y-m')} postérieur à la date du jour.";

            return null;
        }

        return $date->format('Y-m');
    }

    /** Taux en %, en chaîne à 6 décimales comme en base, dans 0–100. Vide : null. */
    private function taux(mixed $brut, string $nom, bool $obligatoire, string $ou): ?string
    {
        $nombre = $this->nombre($brut, $nom, $obligatoire, $ou);
        if ($nombre === null) {
            return null;
        }

        $taux = number_format($nombre, 6, '.', '');
        if ((float) $taux < 0 || (float) $taux > 100) {
            $this->erreurs[] = "{$ou} : taux hors de l'intervalle 0–100 ({$nom}) : {$taux}.";
        }

        return $taux;
    }

    /** Montant en ariary entiers. Vide : null. */
    private function montant(mixed $brut, string $nom, bool $obligatoire, string $ou): ?int
    {
        $nombre = $this->nombre($brut, $nom, $obligatoire, $ou);

        return $nombre === null ? null : (int) round($nombre);
    }

    private function nombre(mixed $brut, string $nom, bool $obligatoire, string $ou): ?float
    {
        if (is_string($brut)) {
            $brut = trim($brut);
        }
        if ($brut === null || $brut === '') {
            if ($obligatoire) {
                $this->erreurs[] = "{$ou} : valeur obligatoire vide ({$nom}).";
            }

            return null;
        }
        if (! is_int($brut) && ! is_float($brut) && ! (is_string($brut) && is_numeric($brut))) {
            $this->erreurs[] = "{$ou} : valeur non numérique ({$nom}) : ".self::afficher($brut).'.';

            return null;
        }

        return (float) $brut;
    }

    /** @param  array<string, string|int|null>  $valeurs */
    private function controlerOrdre(array $valeurs, string $mois): void
    {
        $precedent = null;
        foreach (self::ORDRE as $nom) {
            if ($valeurs[$nom] === null) {
                continue;
            }
            if ($precedent !== null && (float) $valeurs[$nom] > (float) $valeurs[$precedent]) {
                $this->erreurs[] = "{$mois} : ordre non respecté, {$precedent} ({$valeurs[$precedent]}) < {$nom} ({$valeurs[$nom]}).";
            }
            $precedent = $nom;
        }
    }

    /** @param  array<string, string|int|null>  $valeurs */
    private function controlerMontantPar90(array $valeurs, string $mois): void
    {
        if ($valeurs['par90'] === null || $valeurs['encours'] === null || $valeurs['par90_mt'] === null) {
            return;
        }
        $attendu = (float) $valeurs['par90'] / 100 * (float) $valeurs['encours'];
        $ecart = abs((float) $valeurs['par90_mt'] - $attendu);
        if ($attendu == 0 ? $ecart > 0 : $ecart > $this->tolerancePar90Mt / 100 * $attendu) {
            $tolerance = str_replace('.', ',', (string) $this->tolerancePar90Mt);
            $this->avertissements[] = "{$mois} : par90_mt s'écarte de plus de {$tolerance} % de par90 × encours.";
        }
    }

    /** @param  array<int, mixed>  $cellules */
    private function ligneVide(array $cellules): bool
    {
        foreach ($cellules as $cellule) {
            if ($cellule !== null && ! (is_string($cellule) && trim($cellule) === '')) {
                return false;
            }
        }

        return true;
    }

    private function arreterSiErreurs(): void
    {
        if ($this->erreurs !== []) {
            throw new FichierRefuse($this->erreurs);
        }
    }

    private static function rang(string $mois): int
    {
        [$annee, $numero] = array_map('intval', explode('-', $mois));

        return $annee * 12 + $numero - 1;
    }

    private static function afficher(mixed $brut): string
    {
        return is_scalar($brut) ? (string) $brut : get_debug_type($brut);
    }
}
