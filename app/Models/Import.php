<?php

namespace App\Models;

use App\Enums\EtatImport;
use App\Enums\ModeCalcul;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un fichier chargé, archivé hors git avec ses avertissements.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nom_fichier
 * @property string $chemin_archive
 * @property string $empreinte
 * @property list<string> $avertissements
 * @property list<string> $mois_ajoutes
 * @property ModeCalcul $mode_calcul
 * @property EtatImport $etat
 * @property Carbon|null $annule_le
 * @property int|null $annule_par
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'nom_fichier', 'chemin_archive', 'empreinte', 'avertissements', 'mois_ajoutes', 'mode_calcul', 'etat', 'annule_le', 'annule_par'])]
class Import extends Model
{
    protected function casts(): array
    {
        return [
            'avertissements' => 'array',
            'mois_ajoutes' => 'array',
            'mode_calcul' => ModeCalcul::class,
            'etat' => EtatImport::class,
            'annule_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Cloture, $this> */
    public function clotures(): HasMany
    {
        return $this->hasMany(Cloture::class);
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
