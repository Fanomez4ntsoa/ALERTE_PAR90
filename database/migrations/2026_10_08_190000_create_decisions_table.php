<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Section 9. Une décision ne se supprime jamais : elle se complète ou s'annule avec un motif.
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloture_id')->constrained();
            $table->text('action');
            $table->string('responsable');
            $table->date('echeance');
            $table->text('resultat')->nullable();
            $table->string('etat', 20);
            $table->text('motif_annulation')->nullable();
            $table->foreignId('auteur_id')->constrained('users');
            $table->foreignId('complete_par')->nullable()->constrained('users');
            $table->timestamp('complete_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users');
            $table->timestamp('annule_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
