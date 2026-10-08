<?php

/*
 * Import du fichier réel (section 12). Aucune donnée réelle n'est écrite ici,
 * seulement les totaux attendus. Fichier absent : tests ignorés.
 */

use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\Statut;
use App\Enums\ModeCalcul;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Generation;
use App\Models\Import;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DonneesReelles;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
});

// Ne laisse aucune copie du fichier confidentiel sur le disque de test.
afterEach(function () {
    Storage::disk(ServiceImport::DISQUE_ARCHIVE)->deleteDirectory(ServiceImport::DOSSIER_ARCHIVE);
});

$sansFichier = ! DonneesReelles::disponibles();
$raison = 'data/donnees_chap3.xlsx absent : tests sur données réelles ignorés.';

it('charge les 33 mois du fichier réel avec leurs niveaux', function () {
    $this->artisan('alerte:importer', ['--utilisateur' => analyste()->email, 'fichier' => DonneesReelles::CHEMIN])->assertSuccessful();

    $niveaux = Cloture::query()->get()->countBy(fn (Cloture $c) => $c->niveau->value);

    expect(Cloture::count())->toBe(33)
        ->and($niveaux[Niveau::ALERTE->value])->toBe(10)
        ->and($niveaux[Niveau::VIGILANCE->value])->toBe(3)
        ->and($niveaux[Niveau::NORMAL->value])->toBe(17)
        ->and($niveaux[Niveau::NON_EVALUE->value])->toBe(3)
        ->and(Cloture::query()->where('statut', Statut::HORS_LIMITE)->count())->toBe(28)
        ->and(Cloture::query()->whereNull('radiations')->count())->toBe(10)
        ->and(Cloture::query()->where('radiations', 0)->count())->toBe(0)
        ->and(Cloture::query()->where('mode_calcul', '!=', ModeCalcul::RETROSPECTIF)->count())->toBe(0);

    $enAttente = Cloture::query()->whereNull('hausse_reelle_3m')->orderBy('mois')->get()->map(fn (Cloture $c) => $c->mois->format('Y-m'))->all();
    expect($enAttente)->toBe(['2026-05', '2026-06', '2026-07'])
        ->and(Cloture::query()->whereNull('resultat_verif')->where('niveau', '!=', Niveau::NON_EVALUE)->count())->toBe(3);

    expect(Generation::count())->toBeGreaterThan(0)
        ->and(Generation::query()->get()->filter(
            fn (Generation $g) => $g->mois_octroi->copy()->addMonths($g->age_mois)->format('Y-m') > '2026-07'
        ))->toBeEmpty();
})->skip($sansFichier, $raison);

it('stocke pour chaque mois les valeurs rendues par le moteur', function () {
    app(ServiceImport::class)->importer(DonneesReelles::CHEMIN, analyste());
    $juillet = Cloture::query()->whereDate('mois', '2026-07-01')->first();

    expect($juillet->niveau)->toBe(Niveau::NORMAL)
        ->and($juillet->statut)->toBe(Statut::HORS_LIMITE)
        ->and(round((float) $juillet->seuil_bond, 3))->toBe(0.135)
        ->and($juillet->nb_bonds)->toBe(31)
        ->and(round((float) $juillet->rr_connu, 2))->toBe(73.44);
})->skip($sansFichier, $raison);

it("n'ajoute ni ne modifie rien au réimport du fichier réel", function () {
    app(ServiceImport::class)->importer(DonneesReelles::CHEMIN, analyste());
    $avant = [
        Cloture::query()->orderBy('id')->get()->map->getAttributes()->all(),
        Generation::query()->orderBy('id')->get()->map->getAttributes()->all(),
    ];
    $this->travel(5)->minutes();

    $this->artisan('alerte:importer', ['--utilisateur' => analyste()->email, 'fichier' => DonneesReelles::CHEMIN])
        ->expectsOutputToContain('la base est inchangée')
        ->assertSuccessful();

    expect(Import::count())->toBe(1)
        ->and([
            Cloture::query()->orderBy('id')->get()->map->getAttributes()->all(),
            Generation::query()->orderBy('id')->get()->map->getAttributes()->all(),
        ])->toBe($avant);
})->skip($sansFichier, $raison);
