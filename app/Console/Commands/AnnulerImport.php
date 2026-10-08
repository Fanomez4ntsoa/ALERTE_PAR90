<?php

namespace App\Console\Commands;

use App\Enums\EtatImport;
use App\Import\AnnulationRefusee;
use App\Import\ServiceImport;
use App\Models\Import;
use App\Models\User;
use App\Tableau\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerte:annuler-import {--utilisateur= : E-mail de l\'analyste qui annule (obligatoire)} {--force : Ne pas demander de confirmation}')]
#[Description('Annule le dernier import actif : ses mois, ses générations et les vérifications qui en dépendent')]
class AnnulerImport extends Command
{
    public function handle(ServiceImport $service): int
    {
        $import = Import::query()->where('etat', EtatImport::ACTIF)->orderByDesc('id')->first();
        if ($import === null) {
            $this->error('Aucun import actif à annuler.');

            return self::FAILURE;
        }

        $email = (string) $this->option('utilisateur');
        $utilisateur = $email === '' ? null : User::query()->where('email', $email)->first();
        if ($utilisateur === null) {
            $this->error($email === '' ? 'Indiquez l\'utilisateur : --utilisateur=adresse@exemple' : "Utilisateur inconnu : {$email}");

            return self::FAILURE;
        }

        $mois = $import->mois_ajoutes;
        $resume = "Import {$import->id} ({$import->nom_fichier}, ".Format::horodatage($import->created_at).') : '.count($mois).' mois';
        if (! $this->option('force') && ! $this->confirm("Annuler {$resume} ?")) {
            $this->line('Annulation abandonnée.');

            return self::FAILURE;
        }

        try {
            $service->annulerDernier($utilisateur);
        } catch (AuthorizationException) {
            $this->error("{$email} n'a pas le rôle analyste : annulation refusée.");

            return self::FAILURE;
        } catch (AnnulationRefusee $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Annulé : {$resume}.");

        return self::SUCCESS;
    }
}
