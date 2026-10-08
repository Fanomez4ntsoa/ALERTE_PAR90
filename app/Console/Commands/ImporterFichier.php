<?php

namespace App\Console\Commands;

use App\Import\FichierRefuse;
use App\Import\ServiceImport;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerte:importer {fichier : Chemin du fichier modèle .xlsx} {--utilisateur= : E-mail de l\'utilisateur qui importe}')]
#[Description('Importe un fichier modèle : contrôles, archivage, calcul et création des clôtures')]
class ImporterFichier extends Command
{
    public function handle(ServiceImport $service): int
    {
        $chemin = (string) $this->argument('fichier');
        if (! is_file($chemin)) {
            $this->error("Fichier introuvable : {$chemin}");

            return self::FAILURE;
        }

        $utilisateur = null;
        if ($email = $this->option('utilisateur')) {
            $utilisateur = User::query()->where('email', $email)->first();
            if ($utilisateur === null) {
                $this->error("Utilisateur inconnu : {$email}");

                return self::FAILURE;
            }
        }

        try {
            $rapport = $service->importer($chemin, $utilisateur);
        } catch (FichierRefuse $e) {
            $this->error('Fichier refusé, aucune ligne créée :');
            foreach ($e->erreurs as $erreur) {
                $this->line("  - {$erreur}");
            }

            return self::FAILURE;
        }

        foreach ($rapport->avertissements as $avertissement) {
            $this->warn("Avertissement : {$avertissement}");
        }

        if ($rapport->import === null) {
            $this->info('Aucun mois ni aucune génération à ajouter : la base est inchangée.');

            return self::SUCCESS;
        }

        $mois = $rapport->moisAjoutes;
        $this->info(sprintf(
            'Import %d (%s) : %d mois ajouté(s)%s, %d cellule(s) de générations ajoutée(s), %d écartée(s) au-delà du dernier mois.',
            $rapport->import->id,
            $rapport->import->mode_calcul->libelle(),
            count($mois),
            $mois === [] ? '' : ' de '.reset($mois).' à '.end($mois),
            $rapport->generationsAjoutees,
            $rapport->cellulesEcartees,
        ));
        $this->line("Fichier archivé : {$rapport->import->chemin_archive}");

        return self::SUCCESS;
    }
}
