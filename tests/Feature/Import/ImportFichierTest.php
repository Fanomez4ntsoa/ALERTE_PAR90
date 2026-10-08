<?php

/*
 * Import du fichier modèle sur des fichiers fabriqués à la volée
 * avec des données inventées (sections 4, 7 et 10).
 */

use App\Domain\Alerte\Niveau;
use App\Enums\EtatImport;
use App\Enums\ModeCalcul;
use App\Import\FichierRefuse;
use App\Import\RapportImport;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Generation;
use App\Models\Import;
use App\Models\Parametre;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

function importer(string $chemin): RapportImport
{
    return app(ServiceImport::class)->importer($chemin, analyste());
}

/** État complet des tables écrites par l'import. */
function etatBase(): array
{
    return [
        'clotures' => Cloture::query()->orderBy('id')->get()->map->getAttributes()->all(),
        'generations' => Generation::query()->orderBy('id')->get()->map->getAttributes()->all(),
        'imports' => Import::query()->orderBy('id')->get()->map->getAttributes()->all(),
        'parametres' => Parametre::count(),
    ];
}

function attendreRefus(string $chemin, string $message): void
{
    try {
        importer($chemin);
        test()->fail('Le fichier aurait dû être refusé.');
    } catch (FichierRefuse $e) {
        expect(implode("\n", $e->erreurs))->toContain($message);
    }

    expect(Cloture::count())->toBe(0)
        ->and(Generation::count())->toBe(0)
        ->and(Import::count())->toBe(0)
        ->and(Storage::disk(ServiceImport::DISQUE_ARCHIVE)->allFiles())->toBe([]);
}

it('importe un fichier, calcule et fige chaque mois en mode rétrospectif', function () {
    $rapport = importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 20)));

    $import = $rapport->import;
    expect(Cloture::count())->toBe(20)
        ->and($import->mode_calcul)->toBe(ModeCalcul::RETROSPECTIF)
        ->and($import->etat)->toBe(EtatImport::ACTIF)
        ->and($import->mois_ajoutes)->toHaveCount(20)
        ->and(Storage::disk(ServiceImport::DISQUE_ARCHIVE)->exists($import->chemin_archive))->toBeTrue()
        ->and(Parametre::count())->toBe(1);

    $premier = Cloture::query()->orderBy('mois')->first();
    $dernier = Cloture::query()->orderByDesc('mois')->first();
    expect($premier->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($premier->seuil_bond)->toBeNull()
        ->and($premier->mode_calcul)->toBe(ModeCalcul::RETROSPECTIF)
        ->and($premier->parametres_id)->toBe(Parametre::first()->id)
        // 18 bonds passés avant le 20e mois : seuil disponible, stocké à 8 décimales.
        ->and($dernier->nb_bonds)->toBe(18)
        ->and($dernier->seuil_bond)->toMatch('/^-?\d+\.\d{8}$/')
        ->and($dernier->par90)->toMatch('/^\d+\.\d{6}$/')
        ->and($dernier->rr_connu)->not->toBeNull();

    expect(Activity::query()->where('event', 'import')->count())->toBe(1);
});

it('ne crée rien pour un fichier sans colonne par60', function () {
    attendreRefus(
        FabriqueFichier::ecrire(FabriqueFichier::serie(), sansColonnes: ['par60']),
        "Colonne obligatoire absente de l'onglet mensuel : par60.",
    );
});

it('traite un fichier sans mars 2025 comme un trou', function () {
    $serie = FabriqueFichier::serie('2024-01', 20);
    unset($serie['2025-03']);

    $rapport = importer(FabriqueFichier::ecrire($serie));
    $avril = Cloture::query()->whereDate('mois', '2025-04-01')->first();
    $juin = Cloture::query()->whereDate('mois', '2025-06-01')->first();

    expect(Cloture::count())->toBe(19)
        ->and($avril->bond)->toBeNull()
        ->and($avril->tranche_61_90)->not->toBeNull()
        ->and($juin->hausse_3m)->toBeNull()
        ->and($juin->niveau)->toBe(Niveau::NON_EVALUE)
        ->and($rapport->avertissements)->toContain('Mois manquant entre deux mois importés : 2025-03.');
});

it("n'ajoute ni ne modifie rien au réimport du même fichier", function () {
    $chemin = FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 20));
    importer($chemin);
    $avant = etatBase();
    $this->travel(5)->minutes();

    $rapport = importer($chemin);

    expect($rapport->import)->toBeNull()
        ->and(etatBase())->toBe($avant)
        ->and(Storage::disk(ServiceImport::DISQUE_ARCHIVE)->allFiles())->toHaveCount(1);
});

it('écarte les cellules de générations au-delà du dernier mois, même nulles', function () {
    $serie = FabriqueFichier::serie('2024-01', 6);
    $rapport = importer(FabriqueFichier::ecrire($serie));

    // 6 mois d'octroi : 6 + 5 + 4 + 3 + 2 + 1 cellules retenues sur 6 × 13.
    expect(Generation::count())->toBe(21)
        ->and($rapport->cellulesEcartees)->toBe(78 - 21)
        ->and(Generation::query()->get()->filter(
            fn (Generation $g) => $g->mois_octroi->copy()->addMonths($g->age_mois)->format('Y-m') > '2024-06'
        ))->toBeEmpty()
        ->and(Generation::query()->whereDate('mois_octroi', '2024-01-01')->where('age_mois', 5)->value('par30'))->toBe('1.750000')
        ->and(Generation::query()->whereDate('mois_octroi', '2024-01-01')->where('age_mois', 5)->value('par90'))->toBe('0.875000');
});

it('ajoute seulement les nouveaux mois, calculés à la clôture, et complète la vérification', function () {
    $serie = FabriqueFichier::serie('2024-01', 21);
    importer(FabriqueFichier::ecrire(array_slice($serie, 0, 20, true)));
    $avant = Cloture::query()->orderBy('mois')->get()->map->getAttributes()->all();
    expect(Cloture::query()->whereDate('mois', '2025-06-01')->value('hausse_reelle_3m'))->toBeNull();

    $rapport = importer(FabriqueFichier::ecrire($serie));

    $nouveau = Cloture::query()->whereDate('mois', '2025-09-01')->first();
    $verifie = Cloture::query()->whereDate('mois', '2025-06-01')->first();
    expect($rapport->moisAjoutes)->toBe(['2025-09'])
        ->and($rapport->import->mode_calcul)->toBe(ModeCalcul::A_LA_CLOTURE)
        ->and($nouveau->mode_calcul)->toBe(ModeCalcul::A_LA_CLOTURE)
        ->and($nouveau->rr_connu)->toBe(Cloture::query()->whereDate('mois', '2025-08-01')->value('rr_61_90'))
        ->and($verifie->hausse_reelle_3m)->toBe(number_format((float) $nouveau->par90 - (float) $verifie->par90, 6, '.', ''))
        ->and($verifie->resultat_verif)->not->toBeNull();

    // Les 20 premiers mois ne changent que dans leurs colonnes de vérification.
    $apres = Cloture::query()->orderBy('mois')->limit(20)->get()->map->getAttributes()->all();
    $sansVerification = fn (array $lignes) => array_map(
        fn (array $l) => array_diff_key($l, array_flip([...Cloture::COLONNES_VERIFICATION, 'updated_at'])), $lignes,
    );
    expect($sansVerification($apres))->toBe($sansVerification($avant));
});

it('refuse les fichiers qui violent un contrôle bloquant', function (Closure $modifier, string $message) {
    attendreRefus(FabriqueFichier::ecrire($modifier(FabriqueFichier::serie('2024-01', 6))), $message);
})->with([
    'mois en double' => [fn (array $s) => [...array_values($s), $s['2024-03']], 'mois 2024-03 en double'],
    'mois postérieur à la date du jour' => [fn (array $s) => [...$s, '2099-01' => [...$s['2024-06'], 'Mois' => '2099-01-01']], 'postérieur à la date du jour'],
    'mois qui ne commence pas le 1er' => [fn (array $s) => [...$s, '2024-07' => [...$s['2024-06'], 'Mois' => '2024-07-15']], 'Mois invalide'],
    'mois illisible' => [fn (array $s) => [...$s, 'x' => [...$s['2024-06'], 'Mois' => 'juillet']], 'Mois invalide'],
    'valeur obligatoire vide' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['par90' => null]]), 'valeur obligatoire vide (par90)'],
    'valeur non numérique' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['encours' => 'beaucoup']]), 'valeur non numérique (encours)'],
    'taux au-dessus de 100' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['rr_61_90' => 120]]), "taux hors de l'intervalle 0–100 (rr_61_90)"],
    'taux négatif' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['impaye' => -1]]), "taux hors de l'intervalle 0–100 (impaye)"],
    'ordre par60 < par90' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['par60' => 1.0]]), 'ordre non respecté, par60'],
    'ordre par180 < par365' => [fn (array $s) => array_replace_recursive($s, ['2024-02' => ['par365' => 4.0]]), 'ordre non respecté, par180'],
]);

it('refuse un fichier auquel il manque un onglet', function () {
    attendreRefus(FabriqueFichier::ecrire(FabriqueFichier::serie(), sansOnglets: ['vintage_par90']), 'Onglet obligatoire absent : vintage_par90.');
});

it('importe malgré les avertissements, sans jamais écrire 0 pour une valeur vide', function () {
    $serie = FabriqueFichier::serie('2024-01', 6);
    $serie['2024-02']['radiations'] = null;
    $serie['2024-03']['par180'] = null;
    $serie['2024-04']['par90_mt'] = $serie['2024-04']['par90_mt'] * 1.02;

    $rapport = importer(FabriqueFichier::ecrire($serie));
    $fevrier = Cloture::query()->whereDate('mois', '2024-02-01')->first();
    $mars = Cloture::query()->whereDate('mois', '2024-03-01')->first();

    expect(Cloture::count())->toBe(6)
        ->and($fevrier->radiations)->toBeNull()
        ->and($mars->par180)->toBeNull()
        ->and($mars->part_180)->toBeNull()
        ->and($rapport->avertissements)->toContain('2024-02 : radiations non renseignées (n.r.).')
        ->and($rapport->avertissements)->toContain("2024-04 : par90_mt s'écarte de plus de 1 % de par90 × encours.")
        ->and($rapport->import->avertissements)->toBe($rapport->avertissements);
});

it('lit la tolérance sur par90_mt dans la configuration', function () {
    config(['alerte.tolerance_par90_mt' => 3]);
    $serie = FabriqueFichier::serie('2024-01', 6);
    $serie['2024-04']['par90_mt'] = $serie['2024-04']['par90_mt'] * 1.02;
    $serie['2024-05']['par90_mt'] = $serie['2024-05']['par90_mt'] * 1.04;

    $avertissements = importer(FabriqueFichier::ecrire($serie))->avertissements;

    expect($avertissements)->toContain("2024-05 : par90_mt s'écarte de plus de 3 % de par90 × encours.")
        ->and(implode("\n", $avertissements))->not->toContain('2024-04 : par90_mt');
});

it('ne modifie pas un mois déjà en base avec des valeurs différentes', function () {
    $serie = FabriqueFichier::serie('2024-01', 6);
    importer(FabriqueFichier::ecrire($serie));
    $avant = etatBase();

    $serie['2024-03']['par30'] += 0.5;
    $rapport = importer(FabriqueFichier::ecrire($serie));

    expect($rapport->import)->toBeNull()
        ->and($rapport->avertissements)->toContain("2024-03 : déjà en base avec des valeurs différentes ; la ligne en base n'est pas modifiée.")
        ->and(etatBase())->toBe($avant);
});

it('fige une clôture, sauf ses deux colonnes de vérification', function () {
    importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)));
    $cloture = Cloture::query()->orderBy('mois')->first();

    $cloture->update(['hausse_reelle_3m' => '0.100000', 'resultat_verif' => null]);
    expect($cloture->refresh()->hausse_reelle_3m)->toBe('0.100000');

    expect(fn () => $cloture->update(['par90' => '9.000000']))->toThrow(LogicException::class, 'par90');
    expect($cloture->refresh()->par90)->not->toBe('9.000000');
});

it('passe par la commande artisan', function () {
    $this->artisan('alerte:importer', ['--utilisateur' => analyste()->email, 'fichier' => FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6))])
        ->expectsOutputToContain('6 mois ajouté(s) de 2024-01 à 2024-06')
        ->assertSuccessful();

    $this->artisan('alerte:importer', ['--utilisateur' => analyste()->email, 'fichier' => FabriqueFichier::ecrire(FabriqueFichier::serie(), sansColonnes: ['par60'])])
        ->expectsOutputToContain('Fichier refusé, aucune ligne créée')
        ->assertFailed();

    expect(Cloture::count())->toBe(6);
});
