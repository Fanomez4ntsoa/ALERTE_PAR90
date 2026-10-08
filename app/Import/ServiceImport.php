<?php

namespace App\Import;

use App\Domain\Alerte\MoisCloture;
use App\Domain\Alerte\MoteurAlerte;
use App\Domain\Alerte\ResultatMois;
use App\Enums\EtatImport;
use App\Enums\ModeCalcul;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\Generation;
use App\Models\Import;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Import du fichier modèle (sections 4, 7 et 10) et annulation du dernier import.
 */
class ServiceImport
{
    public const DISQUE_ARCHIVE = 'local';

    public const DOSSIER_ARCHIVE = 'imports';

    public function __construct(private readonly LecteurFichierModele $lecteur) {}

    /**
     * Seuls les mois et les cellules de générations absents de la base sont ajoutés.
     * Chaque nouveau mois est calculé par le moteur avec les seuls mois antérieurs,
     * puis figé. Si rien n'est à ajouter, aucun import n'est enregistré.
     *
     * @throws FichierRefuse
     */
    public function importer(string $chemin, ?User $utilisateur = null, ?string $nomFichier = null): RapportImport
    {
        $nomFichier ??= basename($chemin);
        $enBase = Cloture::query()->orderBy('mois')->get()->keyBy(fn (Cloture $c) => $c->mois->format('Y-m'));

        $fichier = $this->lecteur->lire($chemin, Carbon::today(), $enBase->keys()->last());
        $avertissements = $fichier->avertissements;

        $nouvelles = [];
        foreach ($fichier->lignes as $ligne) {
            $existante = $enBase->get($ligne->mois);
            if ($existante === null) {
                $nouvelles[] = $ligne;
            } elseif (! self::memesValeurs($existante, $ligne)) {
                $avertissements[] = "{$ligne->mois} : déjà en base avec des valeurs différentes ; la ligne en base n'est pas modifiée.";
            }
        }

        $tousLesMois = array_values(array_unique([...$enBase->keys()->all(), ...array_map(fn (LigneMensuelle $l) => $l->mois, $fichier->lignes)]));
        $avertissements = [...$avertissements, ...self::moisManquants($tousLesMois)];

        $generationsEnBase = Generation::query()->get()->keyBy(fn (Generation $g) => $g->mois_octroi->format('Y-m').'|'.$g->age_mois);
        $nouvellesGenerations = [];
        foreach ($fichier->generations as $cellule) {
            $existante = $generationsEnBase->get("{$cellule->moisOctroi}|{$cellule->ageMois}");
            if ($existante === null) {
                $nouvellesGenerations[] = $cellule;
            } elseif ($existante->par30 !== $cellule->par30 || $existante->par90 !== $cellule->par90) {
                $avertissements[] = "Génération {$cellule->moisOctroi}, âge {$cellule->ageMois} : déjà en base avec des valeurs différentes ; non modifiée.";
            }
        }

        if ($nouvelles === [] && $nouvellesGenerations === []) {
            return new RapportImport(null, [], 0, $fichier->cellulesEcartees, $avertissements);
        }

        $empreinte = hash_file('sha256', $chemin) ?: throw new RuntimeException("Lecture impossible du fichier {$nomFichier}.");
        $archive = $this->archiver($chemin, $nomFichier, $empreinte);

        try {
            $import = DB::transaction(fn () => $this->enregistrer(
                $enBase->all(), $nouvelles, $nouvellesGenerations, $avertissements, $utilisateur, $nomFichier, $archive, $empreinte,
            ));
        } catch (Throwable $e) {
            Storage::disk(self::DISQUE_ARCHIVE)->delete($archive);

            throw $e;
        }

        return new RapportImport($import, $import->mois_ajoutes, count($nouvellesGenerations), $fichier->cellulesEcartees, $avertissements);
    }

    /**
     * Annule le dernier import actif : ses mois et ses générations sont retirés,
     * ainsi que les vérifications des mois antérieurs qui reposaient sur eux.
     */
    public function annulerDernier(?User $utilisateur = null): Import
    {
        $import = Import::query()->where('etat', EtatImport::ACTIF)->orderByDesc('id')->first()
            ?? throw new RuntimeException('Aucun import actif à annuler.');

        // Section 7 : refusée si une décision est inscrite au registre pour l'un de ces mois.
        $decisions = Decision::query()
            ->whereIn('cloture_id', $import->clotures()->select('id'))
            ->with('cloture')
            ->get();
        if ($decisions->isNotEmpty()) {
            $mois = $decisions->map(fn (Decision $d) => $d->cloture->mois->format('Y-m'))->unique()->sort()->implode(', ');

            throw new AnnulationRefusee(
                "Annulation refusée : {$decisions->count()} décision(s) inscrite(s) au registre pour {$mois}. L'accord de la direction est nécessaire."
            );
        }

        DB::transaction(function () use ($import, $utilisateur) {
            $retires = $import->clotures()->get()->map(fn (Cloture $c) => $c->mois->format('Y-m'))->all();

            $verifiees = Cloture::query()->with('parametres')->where('import_id', '!=', $import->id)->whereNotNull('hausse_reelle_3m')->get();
            foreach ($verifiees as $cloture) {
                $moisVerification = $cloture->mois->copy()->addMonthsNoOverflow($cloture->parametres->horizon_verification)->format('Y-m');
                if (in_array($moisVerification, $retires, true)) {
                    $cloture->update(['hausse_reelle_3m' => null, 'resultat_verif' => null]);
                }
            }

            $import->clotures()->delete();
            $import->generations()->delete();
            $import->update(['etat' => EtatImport::ANNULE, 'annule_le' => Carbon::now(), 'annule_par' => $utilisateur?->id]);

            activity('import')
                ->event('annulation')
                ->performedOn($import)
                ->causedBy($utilisateur)
                ->withProperties(['fichier' => $import->nom_fichier, 'mois' => $retires])
                ->log("Annulation de l'import {$import->id} ({$import->nom_fichier})");
        });

        return $import->refresh();
    }

    /**
     * @param  array<string, Cloture>  $enBase
     * @param  list<LigneMensuelle>  $nouvelles
     * @param  list<CelluleGeneration>  $nouvellesGenerations
     * @param  list<string>  $avertissements
     */
    private function enregistrer(
        array $enBase,
        array $nouvelles,
        array $nouvellesGenerations,
        array $avertissements,
        ?User $utilisateur,
        string $nomFichier,
        string $archive,
        string $empreinte,
    ): Import {
        $parametres = Parametre::enVigueur();
        $mode = $enBase === [] ? ModeCalcul::RETROSPECTIF : ModeCalcul::A_LA_CLOTURE;
        $moisAjoutes = array_map(fn (LigneMensuelle $l) => $l->mois, $nouvelles);

        $import = Import::create([
            'user_id' => $utilisateur?->id,
            'nom_fichier' => $nomFichier,
            'chemin_archive' => $archive,
            'empreinte' => $empreinte,
            'avertissements' => $avertissements,
            'mois_ajoutes' => $moisAjoutes,
            'mode_calcul' => $mode,
            'etat' => EtatImport::ACTIF,
        ]);

        // Les mois en base entrent tels qu'ils sont stockés ; le moteur ne regarde
        // que les mois antérieurs pour calculer chaque nouveau mois.
        $liste = [
            ...array_map(fn (Cloture $c) => $c->versMoisCloture(), array_values($enBase)),
            ...array_map(fn (LigneMensuelle $l) => new MoisCloture(
                mois: $l->mois,
                par60: $l->valeurs['par60'],
                par90: $l->valeurs['par90'],
                par180: $l->valeurs['par180'],
                par365: $l->valeurs['par365'],
                rr6190: $l->valeurs['rr_61_90'],
            ), $nouvelles),
        ];
        $resultats = collect((new MoteurAlerte($parametres->versDomaine()))->calculer($liste))
            ->keyBy(fn (ResultatMois $r) => $r->mois);

        foreach ($nouvelles as $ligne) {
            $r = $resultats[$ligne->mois];
            Cloture::create([
                ...$ligne->valeurs,
                'mois' => "{$ligne->mois}-01",
                'import_id' => $import->id,
                'parametres_id' => $parametres->id,
                'mode_calcul' => $mode,
                'tranche_61_90' => self::decimal($r->tranche, 6),
                'bond' => self::decimal($r->bond, 6),
                'hausse_3m' => self::decimal($r->hausse3m, 6),
                'seuil_bond' => self::decimal($r->seuil, 8),
                'part_180' => self::decimal($r->part180, 6),
                'part_365' => self::decimal($r->part365, 6),
                'rr_connu' => self::decimal($r->tauxPassage, 6),
                'nb_bonds' => $r->nombreBonds,
                'niveau' => $r->niveau,
                'statut' => $r->statut,
                'motif' => $r->motif,
            ]);
        }

        foreach ($nouvellesGenerations as $cellule) {
            Generation::create([
                'mois_octroi' => "{$cellule->moisOctroi}-01",
                'age_mois' => $cellule->ageMois,
                'par30' => $cellule->par30,
                'par90' => $cellule->par90,
                'import_id' => $import->id,
            ]);
        }

        $this->completerVerifications();

        activity('import')
            ->event('import')
            ->performedOn($import)
            ->causedBy($utilisateur)
            ->withProperties([
                'fichier' => $nomFichier,
                'mois' => $moisAjoutes,
                'generations' => count($nouvellesGenerations),
                'avertissements' => $avertissements,
            ])
            ->log("Import du fichier {$nomFichier}");

        return $import;
    }

    /**
     * Vérification à t+h de chaque mois encore en attente dont le mois t+h est
     * en base. Le niveau et les paramètres sont ceux figés du mois t.
     */
    private function completerVerifications(): void
    {
        $clotures = Cloture::query()->with('parametres')->get();
        $par90 = $clotures->mapWithKeys(fn (Cloture $c) => [$c->mois->format('Y-m') => $c->par90]);

        foreach ($clotures->whereNull('hausse_reelle_3m') as $cloture) {
            $horizon = $cloture->parametres->horizon_verification;
            $apres = $par90->get($cloture->mois->copy()->addMonthsNoOverflow($horizon)->format('Y-m'));
            if ($apres === null) {
                continue;
            }

            $verification = (new MoteurAlerte($cloture->parametres->versDomaine()))->verifier($cloture->niveau, $cloture->par90, $apres);
            $cloture->update([
                'hausse_reelle_3m' => self::decimal($verification->hausseReelle, 6),
                'resultat_verif' => $verification->resultat,
            ]);
        }
    }

    private function archiver(string $chemin, string $nomFichier, string $empreinte): string
    {
        $nom = Carbon::now()->format('Ymd-His').'-'.substr($empreinte, 0, 8).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $nomFichier);
        $archive = Storage::disk(self::DISQUE_ARCHIVE)->putFileAs(self::DOSSIER_ARCHIVE, new File($chemin), $nom);
        if ($archive === false) {
            throw new RuntimeException("Archivage impossible du fichier {$nomFichier}.");
        }

        return $archive;
    }

    private static function memesValeurs(Cloture $cloture, LigneMensuelle $ligne): bool
    {
        foreach ($ligne->valeurs as $colonne => $valeur) {
            if ($cloture->{$colonne} !== $valeur) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $mois  AAAA-MM
     * @return list<string>
     */
    private static function moisManquants(array $mois): array
    {
        sort($mois);
        $avertissements = [];
        for ($i = 1; $i < count($mois); $i++) {
            $attendu = Carbon::createFromFormat('!Y-m', $mois[$i - 1])->addMonthNoOverflow();
            $manquants = [];
            while ($attendu->format('Y-m') < $mois[$i]) {
                $manquants[] = $attendu->format('Y-m');
                $attendu->addMonthNoOverflow();
            }
            if ($manquants !== []) {
                $avertissements[] = 'Mois manquant entre deux mois importés : '.implode(', ', $manquants).'.';
            }
        }

        return $avertissements;
    }

    private static function decimal(?float $valeur, int $decimales): ?string
    {
        return $valeur === null ? null : number_format($valeur, $decimales, '.', '');
    }
}
