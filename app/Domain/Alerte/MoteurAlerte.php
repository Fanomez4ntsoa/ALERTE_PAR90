<?php

namespace App\Domain\Alerte;

use InvalidArgumentException;

/**
 * Moteur de calcul du niveau d'alerte PAR90 (cahier des charges, sections 5 à 7 et 9).
 *
 * Classe PHP pure : ni Eloquent, ni façade Laravel.
 *
 * Les taux sont convertis en millionièmes de point, la précision de stockage
 * DECIMAL(10,6). Tous les écarts et toutes les comparaisons se font ensuite
 * en arithmétique entière : une hausse de 0,5 point exactement est bien
 * « supérieure ou égale à 0,5 », sans erreur d'arrondi flottant.
 */
final class MoteurAlerte
{
    /** Millionièmes de point par point. */
    private const ECHELLE = 1_000_000;

    /** Cent-millionièmes de point par point : précision du seuil, DECIMAL(12,8). */
    private const ECHELLE_SEUIL = 100_000_000;

    private const NOMBRES = [1 => 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze'];

    public function __construct(private readonly Parametres $parametres) {}

    /**
     * Calcule chaque mois avec les seuls mois antérieurs de la liste ; seule la
     * vérification regarde le mois t+h, quand il est dans la liste.
     * Un mois absent de la liste est un trou : il n'est jamais comblé.
     *
     * @param  list<MoisCloture>  $listeMois  dans n'importe quel ordre, sans doublon
     * @return list<ResultatMois> triés par mois croissant
     */
    public function calculer(array $listeMois): array
    {
        /** @var array<int, MoisCloture> $parRang */
        $parRang = [];
        foreach ($listeMois as $mois) {
            if (isset($parRang[$mois->rang()])) {
                throw new InvalidArgumentException("Mois en double : {$mois->mois}.");
            }
            $parRang[$mois->rang()] = $mois;
        }
        ksort($parRang);

        $par90 = [];
        $tranche = [];
        foreach ($parRang as $rang => $mois) {
            $par90[$rang] = self::versMillioniemes($mois->par90);
            $par60 = self::versMillioniemes($mois->par60);
            $tranche[$rang] = ($par60 === null || $par90[$rang] === null) ? null : $par60 - $par90[$rang];
        }

        $horizon = $this->parametres->horizonVerification;
        $limite = self::versMillioniemes($this->parametres->limitePar90);
        $seuilHausse = self::versMillioniemes($this->parametres->seuilHausse3m);

        $resultats = [];
        $bondsPasses = [];
        foreach ($parRang as $rang => $mois) {
            $trancheAvant = $tranche[$rang - 1] ?? null;
            $bond = ($tranche[$rang] === null || $trancheAvant === null) ? null : $tranche[$rang] - $trancheAvant;

            $par90Avant = $par90[$rang - $horizon] ?? null;
            $hausse = ($par90[$rang] === null || $par90Avant === null) ? null : $par90[$rang] - $par90Avant;

            // Seuil en cent-millionièmes de point, arrondi à 8 décimales.
            $seuil = $this->seuilDuBond($bondsPasses);

            $niveau = match (true) {
                $hausse === null => Niveau::NON_EVALUE,
                $hausse >= $seuilHausse => Niveau::ALERTE,
                $seuil !== null && $bond !== null && $bond * 100 >= $seuil => Niveau::VIGILANCE,
                default => Niveau::NORMAL,
            };

            $statut = match (true) {
                $par90[$rang] === null => null,
                $par90[$rang] > $limite => Statut::HORS_LIMITE,
                default => Statut::DANS_LIMITE,
            };

            $verification = $this->verifier($niveau, $mois->par90, ($parRang[$rang + $horizon] ?? null)?->par90);

            $resultats[] = new ResultatMois(
                mois: $mois->mois,
                tranche: self::enPoints($tranche[$rang]),
                bond: self::enPoints($bond),
                hausse3m: self::enPoints($hausse),
                seuil: $seuil === null ? null : $seuil / self::ECHELLE_SEUIL,
                nombreBonds: count($bondsPasses),
                niveau: $niveau,
                statut: $statut,
                motif: $this->motif($niveau, $hausse, $bond, $seuil),
                tauxPassage: self::valeur(($parRang[$rang - 1] ?? null)?->rr6190),
                part180: self::part($mois->par180, $par90[$rang]),
                part365: self::part($mois->par365, $par90[$rang]),
                hausseReelle: $verification->hausseReelle,
                resultatVerification: $verification->resultat,
            );

            // Un bond absent est simplement exclu de la liste des bonds passés.
            if ($bond !== null) {
                $bondsPasses[] = $bond;
            }
        }

        return $resultats;
    }

    /**
     * Vérification à h mois (section 9) : hausse réelle par90(t+h) − par90(t)
     * et classement selon le niveau du mois t. Le niveau est celui figé au
     * moment de la clôture. Sans par90(t+h), tout reste absent : « En attente ».
     * Un mois Non évalué garde sa hausse réelle, sans résultat (« Sans objet »).
     */
    public function verifier(Niveau $niveau, float|string|null $par90, float|string|null $par90Apres): Verification
    {
        $avant = self::versMillioniemes($par90);
        $apres = self::versMillioniemes($par90Apres);
        if ($avant === null || $apres === null) {
            return new Verification(null, null);
        }

        $hausse = $apres - $avant;
        $haussee = $hausse >= self::versMillioniemes($this->parametres->seuilHausse3m);

        $resultat = match ($niveau) {
            Niveau::VIGILANCE => $haussee ? ResultatVerification::HAUSSE_CONFIRMEE : ResultatVerification::SIGNAL_NON_SUIVI,
            Niveau::ALERTE => $haussee ? ResultatVerification::HAUSSE_PROLONGEE : ResultatVerification::PAS_DE_NOUVELLE_HAUSSE,
            Niveau::NORMAL => $haussee ? ResultatVerification::HAUSSE_NON_SIGNALEE : ResultatVerification::RIEN_A_SIGNALER,
            Niveau::NON_EVALUE => null,
        };

        return new Verification(self::enPoints($hausse), $resultat);
    }

    /**
     * Centile des bonds passés, interpolation linéaire (type 7 de R) :
     * rang = centile/100 × (n − 1), index à partir de 0.
     * Rend le seuil en cent-millionièmes de point, ou null si n < minimum.
     *
     * Bonds en millionièmes et centile entier : (rang − bas) est un multiple
     * de 0,01, donc la valeur interpolée tient exactement en 8 décimales.
     * L'arrondi à 8 décimales du cahier des charges ne change rien ici.
     *
     * @param  list<int>  $bonds  en millionièmes de point
     */
    private function seuilDuBond(array $bonds): ?int
    {
        $n = count($bonds);
        if ($n < $this->parametres->minimumBonds) {
            return null;
        }

        sort($bonds);
        $rangCentuple = $this->parametres->centileBond * ($n - 1);
        $bas = intdiv($rangCentuple, 100);
        $fraction = $rangCentuple % 100;

        if ($fraction === 0) {
            return $bonds[$bas] * 100;
        }

        return $bonds[$bas] * 100 + $fraction * ($bonds[$bas + 1] - $bonds[$bas]);
    }

    private function motif(Niveau $niveau, ?int $hausse, ?int $bond, ?int $seuil): string
    {
        $periode = 'sur '.(self::NOMBRES[$this->parametres->horizonVerification] ?? $this->parametres->horizonVerification).' mois';

        return match ($niveau) {
            Niveau::NON_EVALUE => "Hausse du PAR90 {$periode} non calculable : historique insuffisant ou donnée manquante.",
            Niveau::ALERTE => 'Hausse du PAR90 de '.self::formater($hausse / self::ECHELLE, 2, true)." point {$periode}.",
            Niveau::VIGILANCE => 'Bond de la tranche 61–90 jours de '.self::formater($bond / self::ECHELLE, 3, true)
                .' point pour un seuil de '.self::formater($seuil / self::ECHELLE_SEUIL, 3, false).' point.',
            Niveau::NORMAL => match (true) {
                $seuil === null => "Pas de hausse du PAR90 {$periode}. Données insuffisantes pour le signal avancé.",
                $bond === null => "Pas de hausse du PAR90 {$periode}. Bond non calculable ce mois-ci.",
                default => "Ni hausse du PAR90 {$periode}, ni signal avancé.",
            },
        };
    }

    /** Mise en forme française, pour le texte du motif uniquement. */
    private static function formater(float $valeur, int $decimales, bool $signeToujours): string
    {
        $texte = number_format(abs($valeur), $decimales, ',', ' ');
        $nul = (float) str_replace(',', '.', $texte) === 0.0;

        return match (true) {
            $valeur < 0 && ! $nul => '−'.$texte,
            $signeToujours => '+'.$texte,
            default => $texte,
        };
    }

    /** Part d'un retard dans le PAR90, en % ; absente si une entrée manque ou si le PAR90 est nul. */
    private static function part(float|string|null $retard, ?int $par90): ?float
    {
        $retard = self::versMillioniemes($retard);

        return ($retard === null || $par90 === null || $par90 === 0) ? null : $retard * 100 / $par90;
    }

    private static function versMillioniemes(float|string|null $taux): ?int
    {
        return $taux === null ? null : (int) round((float) $taux * self::ECHELLE);
    }

    private static function valeur(float|string|null $taux): ?float
    {
        return self::enPoints(self::versMillioniemes($taux));
    }

    private static function enPoints(?int $millioniemes): ?float
    {
        return $millioniemes === null ? null : $millioniemes / self::ECHELLE;
    }
}
