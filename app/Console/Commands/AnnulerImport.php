<?php

namespace App\Console\Commands;

use App\Enums\EtatImport;
use App\Import\ServiceImport;
use App\Models\Import;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerte:annuler-import {--utilisateur= : E-mail de l\'utilisateur qui annule} {--force : Ne pas demander de confirmation}')]
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

        $utilisateur = null;
        if ($email = $this->option('utilisateur')) {
            $utilisateur = User::query()->where('email', $email)->first();
            if ($utilisateur === null) {
                $this->error("Utilisateur inconnu : {$email}");

                return self::FAILURE;
            }
        }

        $mois = $import->mois_ajoutes;
        $resume = "Import {$import->id} ({$import->nom_fichier}, {$import->created_at->format('d/m/Y H:i')}) : ".count($mois).' mois';
        if (! $this->option('force') && ! $this->confirm("Annuler {$resume} ?")) {
            $this->line('Annulation abandonnée.');

            return self::FAILURE;
        }

        $service->annulerDernier($utilisateur);
        $this->info("Annulé : {$resume}.");

        return self::SUCCESS;
    }
}
