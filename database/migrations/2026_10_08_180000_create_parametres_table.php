<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jeux de paramètres du calcul (section 5) ; le jeu en vigueur est le plus récent à sa date d'effet.
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->decimal('limite_par90', 10, 6);
            $table->decimal('seuil_hausse_3m', 10, 6);
            $table->unsignedTinyInteger('centile_bond');
            $table->unsignedSmallInteger('minimum_bonds');
            $table->unsignedTinyInteger('horizon_verification');
            $table->string('nom_institution');
            $table->date('date_effet');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
