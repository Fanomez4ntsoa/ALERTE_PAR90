<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Section 10. Une ligne n'est jamais modifiée, sauf ses deux colonnes de vérification.
        Schema::create('clotures', function (Blueprint $table) {
            $table->id();
            $table->date('mois')->unique();
            $table->foreignId('import_id')->constrained();
            $table->foreignId('parametres_id')->constrained('parametres');
            $table->string('mode_calcul', 20);

            // Importé
            $table->decimal('par30', 10, 6);
            $table->decimal('par60', 10, 6);
            $table->decimal('par90', 10, 6);
            $table->decimal('par180', 10, 6)->nullable();
            $table->decimal('par365', 10, 6)->nullable();
            $table->decimal('impaye', 10, 6)->nullable();
            $table->decimal('rr_61_90', 10, 6)->nullable();
            $table->bigInteger('encours');
            $table->bigInteger('par90_mt');
            $table->bigInteger('radiations')->nullable();

            // Calculé
            $table->decimal('tranche_61_90', 10, 6)->nullable();
            $table->decimal('bond', 10, 6)->nullable();
            $table->decimal('hausse_3m', 10, 6)->nullable();
            $table->decimal('seuil_bond', 12, 8)->nullable();
            $table->decimal('part_180', 10, 6)->nullable();
            $table->decimal('part_365', 10, 6)->nullable();
            $table->decimal('rr_connu', 10, 6)->nullable();
            $table->unsignedSmallInteger('nb_bonds');
            $table->string('niveau', 20);
            $table->string('statut', 20)->nullable();
            $table->string('motif');

            // Vérification, vide tant que t+3 n'est pas importé
            $table->decimal('hausse_reelle_3m', 10, 6)->nullable();
            $table->string('resultat_verif', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clotures');
    }
};
