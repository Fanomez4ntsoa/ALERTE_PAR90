<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->date('mois_octroi');
            $table->unsignedTinyInteger('age_mois');
            $table->decimal('par30', 10, 6)->nullable();
            $table->decimal('par90', 10, 6)->nullable();
            $table->foreignId('import_id')->constrained();
            $table->timestamps();

            $table->unique(['mois_octroi', 'age_mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
