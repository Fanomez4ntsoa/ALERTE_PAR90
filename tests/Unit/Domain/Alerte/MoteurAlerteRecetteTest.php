<?php

/*
 * Tests de recette sur les données réelles (cahier des charges, section 12).
 * Seules les valeurs attendues des 12 dates sont écrites ici ; les données
 * viennent de data/donnees_chap3.xlsx, hors git. Fichier absent : tests ignorés.
 */

use App\Domain\Alerte\MoisCloture;
use App\Domain\Alerte\MoteurAlerte;
use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\ResultatMois;
use App\Domain\Alerte\Statut;
use Tests\Support\DonneesReelles;

/**
 * @param  list<MoisCloture>  $mois
 * @return array<string, ResultatMois>
 */
function recetteCalculer(array $mois): array
{
    $resultats = (new MoteurAlerte(DonneesReelles::parametres()))->calculer($mois);

    return array_column(array_map(fn (ResultatMois $r) => ['cle' => $r->mois, 'r' => $r], $resultats), 'r', 'cle');
}

function recetteArrondi(?float $valeur, int $decimales): ?string
{
    return $valeur === null ? null : number_format($valeur, $decimales, '.', '');
}

$sansFichier = ! DonneesReelles::disponibles();
$raison = 'data/donnees_chap3.xlsx absent : tests sur données réelles ignorés.';

it('redonne les 12 dates du mémoire', function (string $mois, string $hausse, string $bond, string $seuil, Niveau $niveau, string $hausseReelle) {
    $r = recetteCalculer(DonneesReelles::mois())[$mois];

    expect($r->niveau)->toBe($niveau)
        ->and(recetteArrondi($r->seuil, 3))->toBe($seuil)
        ->and(recetteArrondi($r->hausse3m, 2))->toBe($hausse)
        ->and(recetteArrondi($r->bond, 3))->toBe($bond)
        ->and(recetteArrondi($r->hausseReelle, 2))->toBe($hausseReelle);
})->with([
    'mai 2025' => ['2025-05', '0.79', '-0.564', '0.131', Niveau::ALERTE, '0.08'],
    'juin 2025' => ['2025-06', '0.54', '0.314', '0.123', Niveau::ALERTE, '0.66'],
    'juil. 2025' => ['2025-07', '-0.12', '-0.037', '0.133', Niveau::NORMAL, '1.13'],
    'août 2025' => ['2025-08', '0.08', '0.202', '0.132', Niveau::VIGILANCE, '0.74'],
    'sept. 2025' => ['2025-09', '0.66', '0.075', '0.135', Niveau::ALERTE, '-0.45'],
    'oct. 2025' => ['2025-10', '1.13', '-0.185', '0.134', Niveau::ALERTE, '-0.77'],
    'nov. 2025' => ['2025-11', '0.74', '0.231', '0.132', Niveau::ALERTE, '-0.91'],
    'déc. 2025' => ['2025-12', '-0.45', '-0.320', '0.141', Niveau::NORMAL, '0.52'],
    'janv. 2026' => ['2026-01', '-0.77', '-0.001', '0.134', Niveau::NORMAL, '1.03'],
    'févr. 2026' => ['2026-02', '-0.91', '0.463', '0.133', Niveau::VIGILANCE, '1.28'],
    'mars 2026' => ['2026-03', '0.52', '0.343', '0.146', Niveau::ALERTE, '0.43'],
    'avr. 2026' => ['2026-04', '1.03', '-0.536', '0.183', Niveau::ALERTE, '-0.25'],
])->skip($sansFichier, $raison);

it('redonne les totaux sur les 33 mois', function () {
    $resultats = recetteCalculer(DonneesReelles::mois());
    $compter = fn (callable $filtre) => count(array_filter($resultats, $filtre));

    expect($resultats)->toHaveCount(33)
        ->and($compter(fn ($r) => $r->niveau === Niveau::ALERTE))->toBe(10)
        ->and($compter(fn ($r) => $r->niveau === Niveau::VIGILANCE))->toBe(3)
        ->and($compter(fn ($r) => $r->niveau === Niveau::NORMAL))->toBe(17)
        ->and($compter(fn ($r) => $r->niveau === Niveau::NON_EVALUE))->toBe(3)
        ->and($compter(fn ($r) => $r->statut === Statut::HORS_LIMITE))->toBe(28);
})->skip($sansFichier, $raison);

it("n'évalue pas les trois premiers mois", function () {
    $resultats = recetteCalculer(DonneesReelles::mois());

    foreach (['2023-11', '2023-12', '2024-01'] as $mois) {
        expect($resultats[$mois]->niveau)->toBe(Niveau::NON_EVALUE)
            ->and($resultats[$mois]->hausse3m)->toBeNull();
    }
})->skip($sansFichier, $raison);

it('traite mars 2025 retiré du fichier comme un trou', function () {
    $sansMars = array_values(array_filter(DonneesReelles::mois(), fn (MoisCloture $m) => $m->mois !== '2025-03'));
    $resultats = recetteCalculer($sansMars);

    expect($resultats)->not->toHaveKey('2025-03')
        ->and($resultats['2025-04']->bond)->toBeNull()
        ->and($resultats['2025-06']->hausse3m)->toBeNull()
        ->and($resultats['2025-06']->niveau)->toBe(Niveau::NON_EVALUE);
})->skip($sansFichier, $raison);

it('donne le premier seuil en décembre 2024, avec 12 bonds', function () {
    $resultats = recetteCalculer(DonneesReelles::mois());

    expect($resultats['2024-11']->seuil)->toBeNull()
        ->and($resultats['2024-11']->nombreBonds)->toBe(11)
        ->and(recetteArrondi($resultats['2024-12']->seuil, 3))->toBe('0.134')
        ->and($resultats['2024-12']->nombreBonds)->toBe(12);
})->skip($sansFichier, $raison);

it('met mars 2025 en Vigilance', function () {
    $r = recetteCalculer(DonneesReelles::mois())['2025-03'];

    expect($r->niveau)->toBe(Niveau::VIGILANCE)
        ->and(recetteArrondi($r->bond, 3))->toBe('0.996')
        ->and(recetteArrondi($r->seuil, 3))->toBe('0.116');
})->skip($sansFichier, $raison);

it('redonne juillet 2026', function () {
    $r = recetteCalculer(DonneesReelles::mois())['2026-07'];

    expect($r->niveau)->toBe(Niveau::NORMAL)
        ->and($r->statut)->toBe(Statut::HORS_LIMITE)
        ->and(recetteArrondi($r->seuil, 3))->toBe('0.135')
        ->and($r->nombreBonds)->toBe(31)
        ->and(recetteArrondi($r->tauxPassage, 2))->toBe('73.44')
        ->and(recetteArrondi($r->part180, 1))->toBe('78.5')
        ->and(recetteArrondi($r->part365, 1))->toBe('41.1');
})->skip($sansFichier, $raison);

it('laisse mai, juin et juillet 2026 en attente de vérification', function () {
    $resultats = recetteCalculer(DonneesReelles::mois());

    foreach (['2026-05', '2026-06', '2026-07'] as $mois) {
        expect($resultats[$mois]->hausseReelle)->toBeNull()
            ->and($resultats[$mois]->resultatVerification)->toBeNull();
    }
    expect($resultats['2026-04']->hausseReelle)->not->toBeNull();
})->skip($sansFichier, $raison);
