<?php

/*
 * Cas limites du moteur (cahier des charges, sections 5 à 7), sur de petites
 * séries inventées. Les paramètres sont fixés ici pour que chaque série reste
 * lisible ; ils reprennent les valeurs par défaut de la section 5.
 */

use App\Domain\Alerte\MoisCloture;
use App\Domain\Alerte\MoteurAlerte;
use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\Parametres;
use App\Domain\Alerte\ResultatMois;
use App\Domain\Alerte\ResultatVerification;
use App\Domain\Alerte\Statut;

function casParametres(int $horizon = 3): Parametres
{
    return new Parametres(limitePar90: 5, seuilHausse3m: 0.5, centileBond: 70, minimumBonds: 12, horizonVerification: $horizon);
}

/** Mois AAAA-MM décalé de $k mois à partir de janvier 2024. */
function casMois(int $k): string
{
    return sprintf('%04d-%02d', 2024 + intdiv($k, 12), $k % 12 + 1);
}

/**
 * @param  list<MoisCloture>  $mois
 * @return array<string, ResultatMois>
 */
function casCalculer(array $mois, int $horizon = 3): array
{
    $resultats = (new MoteurAlerte(casParametres($horizon)))->calculer($mois);

    return array_column(array_map(fn (ResultatMois $r) => ['cle' => $r->mois, 'r' => $r], $resultats), 'r', 'cle');
}

/**
 * Série dont la tranche part de 1 point et varie des bonds donnés ; PAR90 constant.
 * Les valeurs passent en chaînes à six décimales, comme en base.
 *
 * @param  list<float>  $bonds
 * @return list<MoisCloture>
 */
function casSerieDepuisBonds(array $bonds, float $par90 = 4.0): array
{
    $tranche = 1.0;
    $serie = [new MoisCloture(casMois(0), sprintf('%.6F', $par90 + $tranche), sprintf('%.6F', $par90))];
    foreach ($bonds as $k => $bond) {
        $tranche += $bond;
        $serie[] = new MoisCloture(casMois($k + 1), sprintf('%.6F', $par90 + $tranche), sprintf('%.6F', $par90));
    }

    return $serie;
}

/** Les 12 bonds 0,01 à 0,12 dans le désordre : 70e centile type 7 = 0,087. */
function casDouzeBonds(): array
{
    return [0.05, 0.12, 0.01, 0.09, 0.03, 0.11, 0.07, 0.02, 0.10, 0.04, 0.08, 0.06];
}

// --- Moins de 4 mois d'historique ---

it("rend Non évalué tant que t−3 n'existe pas", function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.2),
        new MoisCloture('2024-03', 7.0, 5.4),
        new MoisCloture('2024-04', 7.0, 5.6),
    ]);

    foreach (['2024-01', '2024-02', '2024-03'] as $mois) {
        expect($r[$mois]->hausse3m)->toBeNull()
            ->and($r[$mois]->niveau)->toBe(Niveau::NON_EVALUE)
            ->and($r[$mois]->motif)->toContain('non calculable');
    }
    expect($r['2024-04']->hausse3m)->toBe(0.6)
        ->and($r['2024-04']->niveau)->toBe(Niveau::ALERTE)
        ->and($r['2024-04']->motif)->toBe('Hausse du PAR90 de +0,60 point sur trois mois.');
});

// --- Moins de 12 bonds passés ---

it('ne donne ni seuil ni Vigilance avec moins de 12 bonds passés', function () {
    // 11 bonds passés, puis un bond énorme au 13e mois.
    $r = casCalculer(casSerieDepuisBonds([...array_fill(0, 11, 0.01), 3.0]));
    $dernier = $r[casMois(12)];

    expect($dernier->seuil)->toBeNull()
        ->and($dernier->nombreBonds)->toBe(11)
        ->and($dernier->bond)->toBe(3.0)
        ->and($dernier->niveau)->toBe(Niveau::NORMAL)
        ->and($dernier->motif)->toContain('Données insuffisantes pour le signal avancé');

    expect(array_map(fn (ResultatMois $m) => $m->niveau->value, $r))->not->toContain(Niveau::VIGILANCE->value);
});

it('calcule le seuil dès 12 bonds passés, au 70e centile type 7', function () {
    $r = casCalculer(casSerieDepuisBonds([...casDouzeBonds(), 0.0]));
    $mois = $r[casMois(13)];

    // rang = 0,70 × 11 = 7,7 → 0,08 + 0,7 × (0,09 − 0,08)
    expect($mois->nombreBonds)->toBe(12)
        ->and($mois->seuil)->toEqualWithDelta(0.087, 1e-12);
});

it('allume la Vigilance quand le bond égale exactement le seuil', function () {
    $egal = casCalculer(casSerieDepuisBonds([...casDouzeBonds(), 0.087]))[casMois(13)];
    $juste = casCalculer(casSerieDepuisBonds([...casDouzeBonds(), 0.086999]))[casMois(13)];

    expect($egal->niveau)->toBe(Niveau::VIGILANCE)
        ->and($egal->motif)->toBe('Bond de la tranche 61–90 jours de +0,087 point pour un seuil de 0,087 point.')
        ->and($juste->niveau)->toBe(Niveau::NORMAL);
});

it('compare le bond au seuil arrondi à 8 décimales, exact ici', function () {
    // Bonds en millionièmes : 0 à 7, puis 9 à 12. Seuil 7 + 0,7 × 2 = 8,4 millionièmes,
    // soit 0,00000840 : il tient en 8 décimales, alors qu'un arrondi à 6 le ramènerait à 8.
    $bonds = array_map(fn ($i) => $i / 1_000_000, [0, 1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12]);
    $huit = casCalculer(casSerieDepuisBonds([...$bonds, 0.000008]))[casMois(13)];
    $neuf = casCalculer(casSerieDepuisBonds([...$bonds, 0.000009]))[casMois(13)];

    expect($huit->seuil)->toEqualWithDelta(0.0000084, 1e-12)
        ->and($huit->niveau)->toBe(Niveau::NORMAL)
        ->and($neuf->niveau)->toBe(Niveau::VIGILANCE);
});

// --- Seuil de hausse et ordre des règles ---

it('déclenche Alerte pour une hausse de 0,5 point exactement', function () {
    // En flottant, 2,00078 − 1,50078 vaut 0,49999999999999978.
    expect(2.00078 - 1.50078)->toBeLessThan(0.5);

    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 1.50078),
        new MoisCloture('2024-02', 7.0, 1.6),
        new MoisCloture('2024-03', 7.0, 1.7),
        new MoisCloture('2024-04', 7.0, 2.00078),
        new MoisCloture('2024-05', 7.0, '2.099999'),
    ]);

    expect($r['2024-04']->hausse3m)->toBe(0.5)
        ->and($r['2024-04']->niveau)->toBe(Niveau::ALERTE)
        ->and($r['2024-05']->hausse3m)->toBe(0.499999)
        ->and($r['2024-05']->niveau)->not->toBe(Niveau::ALERTE);
});

it("donne la priorité à l'Alerte sur la Vigilance", function () {
    $serie = casSerieDepuisBonds([...casDouzeBonds(), 0.5]);
    // Le dernier mois gagne 0,6 point de PAR90 en trois mois (tranche conservée).
    $dernier = array_pop($serie);
    $serie[] = new MoisCloture($dernier->mois, (float) $dernier->par60 + 0.6, (float) $dernier->par90 + 0.6);

    $mois = casCalculer($serie)[casMois(13)];

    expect($mois->bond)->toEqualWithDelta(0.5, 1e-9)
        ->and($mois->seuil)->not->toBeNull()
        ->and($mois->niveau)->toBe(Niveau::ALERTE);
});

// --- Mois manquant ---

it("n'a pas de bond quand le mois t−1 est absent", function () {
    $serie = casSerieDepuisBonds([...casDouzeBonds(), 0.01, 2.0]);
    // On retire le 14e mois : le 15e mois n'a plus de mois précédent.
    unset($serie[13]);
    $r = casCalculer(array_values($serie));
    $mois = $r[casMois(14)];

    expect($r)->not->toHaveKey(casMois(13))
        ->and($mois->tranche)->not->toBeNull()
        ->and($mois->bond)->toBeNull()
        ->and($mois->seuil)->not->toBeNull()
        ->and($mois->niveau)->toBe(Niveau::NORMAL)
        ->and($mois->motif)->toContain('Bond non calculable');
});

it('rend Non évalué quand le mois t−3 est absent', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-03', 7.0, 5.0),
        new MoisCloture('2024-04', 7.0, 5.0),
        new MoisCloture('2024-05', 7.0, 6.0),
        new MoisCloture('2024-06', 7.0, 6.0),
    ]);

    expect($r['2024-04']->hausse3m)->toBe(0.0)
        ->and($r['2024-04']->niveau)->toBe(Niveau::NORMAL)
        ->and($r['2024-05']->hausse3m)->toBeNull()
        ->and($r['2024-05']->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($r['2024-06']->hausse3m)->toBe(1.0)
        ->and($r['2024-06']->niveau)->toBe(Niveau::ALERTE);
});

it('exclut un bond absent de la liste qui sert au seuil', function () {
    // Le 14e mois est retiré : le 15e n'a pas de bond et ne compte pas pour le 16e.
    $serie = casSerieDepuisBonds([...casDouzeBonds(), 0.0, 0.0, 0.0]);
    unset($serie[13]);
    $r = casCalculer(array_values($serie));

    expect($r[casMois(14)]->bond)->toBeNull()
        ->and($r[casMois(15)]->nombreBonds)->toBe(12);
});

it('trie la liste reçue et refuse un mois en double', function () {
    $r = (new MoteurAlerte(casParametres()))->calculer([
        new MoisCloture('2024-03', 7.0, 5.0),
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.0),
    ]);
    expect(array_map(fn (ResultatMois $m) => $m->mois, $r))->toBe(['2024-01', '2024-02', '2024-03']);

    (new MoteurAlerte(casParametres()))->calculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-01', 7.0, 5.1),
    ]);
})->throws(InvalidArgumentException::class, 'Mois en double : 2024-01.');

// --- Valeur nulle ---

it('ne remplace jamais un par60 nul par 0', function () {
    $serie = casSerieDepuisBonds([...casDouzeBonds(), 0.0, 0.0]);
    $serie[13] = new MoisCloture($serie[13]->mois, null, $serie[13]->par90);
    $r = casCalculer($serie);

    expect($r[casMois(13)]->tranche)->toBeNull()
        ->and($r[casMois(13)]->bond)->toBeNull()
        ->and($r[casMois(13)]->statut)->toBe(Statut::DANS_LIMITE)
        ->and($r[casMois(13)]->niveau)->toBe(Niveau::NORMAL)
        ->and($r[casMois(14)]->bond)->toBeNull()
        ->and($r[casMois(14)]->nombreBonds)->toBe(12);
});

it('ne remplace jamais un par90 nul par 0', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.0),
        new MoisCloture('2024-03', 7.0, 5.0),
        new MoisCloture('2024-04', 7.0, null),
        new MoisCloture('2024-05', 7.0, 5.0),
        new MoisCloture('2024-06', 7.0, 5.0),
        new MoisCloture('2024-07', 7.0, 5.0),
        new MoisCloture('2024-08', 7.0, 5.0),
    ]);

    expect($r['2024-04']->tranche)->toBeNull()
        ->and($r['2024-04']->hausse3m)->toBeNull()
        ->and($r['2024-04']->statut)->toBeNull()
        ->and($r['2024-04']->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($r['2024-05']->bond)->toBeNull()
        // par90(t−3) nul : pas de hausse, au lieu d'une hausse de 5 points.
        ->and($r['2024-07']->hausse3m)->toBeNull()
        ->and($r['2024-07']->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($r['2024-08']->hausse3m)->toBe(0.0)
        ->and($r['2024-08']->niveau)->toBe(Niveau::NORMAL);
});

// --- Statut ---

it('fixe le statut sur la limite stricte, indépendamment du niveau', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, '5.000001'),
    ]);

    expect($r['2024-01']->statut)->toBe(Statut::DANS_LIMITE)
        ->and($r['2024-02']->statut)->toBe(Statut::HORS_LIMITE)
        ->and($r['2024-02']->niveau)->toBe(Niveau::NON_EVALUE);
});

// --- Horizon ---

it("utilise l'horizon pour la hausse et pour la vérification", function () {
    $serie = [
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.2),
        new MoisCloture('2024-03', 7.0, 5.6),
        new MoisCloture('2024-04', 7.0, 5.7),
    ];
    $deux = casCalculer($serie, horizon: 2);
    $trois = casCalculer($serie);

    expect($deux['2024-03']->hausse3m)->toBe(0.6)
        ->and($deux['2024-03']->niveau)->toBe(Niveau::ALERTE)
        ->and($deux['2024-03']->motif)->toBe('Hausse du PAR90 de +0,60 point sur deux mois.')
        ->and($deux['2024-02']->hausseReelle)->toBe(0.5)
        ->and($deux['2024-03']->hausseReelle)->toBeNull()
        ->and($trois['2024-03']->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($trois['2024-01']->hausseReelle)->toBe(0.7);
});

// --- Taux de passage connu et parts des retards anciens ---

it('affiche en t le taux de passage de la ligne t−1', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0, rr6190: 40.0),
        new MoisCloture('2024-02', 7.0, 5.0, rr6190: 50.0),
        new MoisCloture('2024-03', 7.0, 5.0, rr6190: null),
        new MoisCloture('2024-04', 7.0, 5.0, rr6190: 60.0),
        new MoisCloture('2024-06', 7.0, 5.0, rr6190: 70.0),
    ]);

    expect($r['2024-01']->tauxPassage)->toBeNull()
        ->and($r['2024-02']->tauxPassage)->toBe(40.0)
        ->and($r['2024-03']->tauxPassage)->toBe(50.0)
        ->and($r['2024-04']->tauxPassage)->toBeNull()
        ->and($r['2024-06']->tauxPassage)->toBeNull();
});

it('calcule les parts des retards de plus de 180 et 365 jours dans le PAR90', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 4.0, par180: 3.0, par365: 1.0),
        new MoisCloture('2024-02', 7.0, 4.0, par180: null, par365: 0.0),
        new MoisCloture('2024-03', 0.0, 0.0, par180: 0.0, par365: 0.0),
    ]);

    expect($r['2024-01']->part180)->toBe(75.0)
        ->and($r['2024-01']->part365)->toBe(25.0)
        ->and($r['2024-02']->part180)->toBeNull()
        ->and($r['2024-02']->part365)->toBe(0.0)
        ->and($r['2024-03']->part180)->toBeNull()
        ->and($r['2024-03']->part365)->toBeNull();
});

// --- Vérification à trois mois ---

it('classe chaque niveau selon la hausse réelle', function (Niveau $niveau, float $apres, ?ResultatVerification $attendu) {
    $v = (new MoteurAlerte(casParametres()))->verifier($niveau, '5.000000', $apres);

    expect($v->resultat)->toBe($attendu)
        ->and($v->enAttente())->toBeFalse();
})->with([
    'Vigilance, hausse' => [Niveau::VIGILANCE, 5.5, ResultatVerification::HAUSSE_CONFIRMEE],
    'Vigilance, sans hausse' => [Niveau::VIGILANCE, 5.499999, ResultatVerification::SIGNAL_NON_SUIVI],
    'Alerte, hausse' => [Niveau::ALERTE, 6.0, ResultatVerification::HAUSSE_PROLONGEE],
    'Alerte, sans hausse' => [Niveau::ALERTE, 4.0, ResultatVerification::PAS_DE_NOUVELLE_HAUSSE],
    'Normal, hausse' => [Niveau::NORMAL, 5.5, ResultatVerification::HAUSSE_NON_SIGNALEE],
    'Normal, sans hausse' => [Niveau::NORMAL, 5.1, ResultatVerification::RIEN_A_SIGNALER],
    'Non évalué : sans objet' => [Niveau::NON_EVALUE, 6.0, null],
]);

it('garde la hausse réelle d\'un mois Non évalué, sans résultat', function () {
    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.0),
        new MoisCloture('2024-03', 7.0, 5.0),
        new MoisCloture('2024-04', 7.0, 5.8),
    ]);

    expect($r['2024-01']->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($r['2024-01']->hausseReelle)->toBe(0.8)
        ->and($r['2024-01']->resultatVerification)->toBeNull();
});

it('laisse la vérification en attente sans le mois t+3', function () {
    $moteur = new MoteurAlerte(casParametres());

    expect($moteur->verifier(Niveau::ALERTE, 5.0, null)->enAttente())->toBeTrue()
        ->and($moteur->verifier(Niveau::ALERTE, 5.0, null)->resultat)->toBeNull()
        ->and($moteur->verifier(Niveau::ALERTE, null, 6.0)->enAttente())->toBeTrue();

    $r = casCalculer([
        new MoisCloture('2024-01', 7.0, 5.0),
        new MoisCloture('2024-02', 7.0, 5.0),
        new MoisCloture('2024-03', 7.0, 5.0),
        new MoisCloture('2024-05', 7.0, 5.0),
    ]);
    // 2024-04 manque : janvier attend ; février (Non évalué) a sa hausse réelle, sans résultat.
    expect($r['2024-01']->hausseReelle)->toBeNull()
        ->and($r['2024-02']->hausseReelle)->toBe(0.0)
        ->and($r['2024-02']->resultatVerification)->toBeNull();
});

// --- Paramètres ---

it('lit les cinq paramètres dans config/alerte.php', function () {
    $p = Parametres::depuisConfig(require __DIR__.'/../../../../config/alerte.php');

    expect($p)->toEqual(casParametres());
});

it('refuse une configuration incomplète', function () {
    Parametres::depuisConfig(['limite_par90' => 5, 'seuil_hausse_3m' => 0.5, 'centile_bond' => 70, 'minimum_bonds' => 12]);
})->throws(InvalidArgumentException::class, 'horizon_verification');
