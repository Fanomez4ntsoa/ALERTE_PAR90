<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EtatImport;
use App\Http\Controllers\Controller;
use App\Import\AnnulationRefusee;
use App\Import\FichierRefuse;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Import;
use App\Tableau\Format;
use App\Tableau\TableauDeBord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Section Imports du back-office (analyste). Toutes les règles d'import sont
 * dans ServiceImport, le même service que la commande artisan.
 */
class ImportController extends Controller
{
    public function __construct(private readonly ServiceImport $service) {}

    public function index(): Response
    {
        $imports = Import::query()->with(['user', 'annulateur'])->orderByDesc('id')->get();
        $dernier = $imports->first(fn (Import $i) => $i->etat === EtatImport::ACTIF);

        return Inertia::render('admin/Imports', [
            'imports' => $imports->map(fn (Import $i) => [
                'id' => $i->id,
                'date' => Format::horodatage($i->created_at),
                'utilisateur' => $i->user->name ?? Format::NON_RENSEIGNE,
                'fichier' => $i->nom_fichier,
                'mois' => self::periode($i->mois_ajoutes),
                'mode' => $i->mode_calcul->libelle(),
                'avertissements' => count($i->avertissements),
                'etat' => $i->etat === EtatImport::ACTIF
                    ? 'Actif'
                    : 'Annulé le '.Format::horodatage($i->annule_le).' par '.($i->annulateur->name ?? Format::NON_RENSEIGNE),
                'actif' => $i->etat === EtatImport::ACTIF,
            ])->values()->all(),
            'dernier' => $dernier === null ? null : [
                'id' => $dernier->id,
                'description' => "Import {$dernier->id} du ".Format::horodatage($dernier->created_at)." ({$dernier->nom_fichier}) : ".self::periode($dernier->mois_ajoutes),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['fichier' => ['required', 'file', 'extensions:xlsx', 'max:10240']],
            [
                'fichier.required' => 'Choisissez un fichier Excel (.xlsx).',
                'fichier.extensions' => 'Le fichier doit être un classeur Excel .xlsx.',
                'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            ],
        );
        $fichier = $request->file('fichier');

        try {
            $rapport = $this->service->importer($fichier->getRealPath(), $request->user(), $fichier->getClientOriginalName());
        } catch (FichierRefuse $e) {
            Inertia::flash('compteRendu', [
                'statut' => 'refuse',
                'fichier' => $fichier->getClientOriginalName(),
                'erreurs' => $e->erreurs,
            ]);

            return to_route('admin.imports');
        }

        $clotures = Cloture::query()
            ->whereIn('mois', array_map(fn (string $m) => "{$m}-01", $rapport->moisAjoutes))
            ->orderBy('mois')
            ->get();

        Inertia::flash('compteRendu', [
            'statut' => $rapport->import === null ? 'inchange' : 'importe',
            'fichier' => $fichier->getClientOriginalName(),
            'mode' => $rapport->import?->mode_calcul->libelle(),
            'mois' => $clotures->map(fn (Cloture $c) => [
                'mois' => Format::mois($c->mois),
                'niveau' => $c->niveau->libelle(),
                'couleur' => TableauDeBord::COULEURS[$c->niveau->value],
            ])->values()->all(),
            'generations' => $rapport->generationsAjoutees,
            'ecartees' => $rapport->cellulesEcartees,
            'avertissements' => $rapport->avertissements,
        ]);

        return to_route('admin.imports');
    }

    public function annuler(Request $request): RedirectResponse
    {
        try {
            $import = $this->service->annulerDernier($request->user());
            Inertia::flash('annulation', [
                'statut' => 'annule',
                'message' => "Import {$import->id} annulé : ".self::periode($import->mois_ajoutes).' retiré(s).',
            ]);
        } catch (AnnulationRefusee $e) {
            Inertia::flash('annulation', ['statut' => 'refuse', 'message' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            Inertia::flash('annulation', ['statut' => 'refuse', 'message' => $e->getMessage()]);
        }

        return to_route('admin.imports');
    }

    /**
     * « novembre 2023 à juillet 2026 (33 mois) ».
     *
     * @param  array<int, string>  $mois  AAAA-MM
     */
    public static function periode(array $mois): string
    {
        if ($mois === []) {
            return 'aucun mois (générations seulement)';
        }
        sort($mois);
        $debut = Format::mois(Carbon::createFromFormat('!Y-m', reset($mois)));
        $fin = Format::mois(Carbon::createFromFormat('!Y-m', end($mois)));
        $nombre = count($mois);

        return $nombre === 1 ? "{$debut} (1 mois)" : "{$debut} à {$fin} ({$nombre} mois)";
    }
}
