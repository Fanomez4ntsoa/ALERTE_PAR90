<?php

namespace App\Models;

use App\Domain\Alerte\MoisCloture;
use App\Domain\Alerte\Niveau;
use App\Domain\Alerte\ResultatVerification;
use App\Domain\Alerte\Statut;
use App\Enums\ModeCalcul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Un mois de clôture : valeurs importées et calculées, figées à l'import.
 *
 * @property int $id
 * @property Carbon $mois
 * @property int $import_id
 * @property int $parametres_id
 * @property ModeCalcul $mode_calcul
 * @property string $par30
 * @property string $par60
 * @property string $par90
 * @property string|null $par180
 * @property string|null $par365
 * @property string|null $impaye
 * @property string|null $rr_61_90
 * @property int $encours
 * @property int $par90_mt
 * @property int|null $radiations
 * @property string|null $tranche_61_90
 * @property string|null $bond
 * @property string|null $hausse_3m
 * @property string|null $seuil_bond
 * @property string|null $part_180
 * @property string|null $part_365
 * @property string|null $rr_connu
 * @property int $nb_bonds
 * @property Niveau $niveau
 * @property Statut|null $statut
 * @property string $motif
 * @property string|null $hausse_reelle_3m
 * @property ResultatVerification|null $resultat_verif
 * @property int|null $decisions_count avec withCount('decisions')
 * @property int|null $decisions_annulees_count avec withCount, décisions annulées
 */
class Cloture extends Model
{
    /** Seules colonnes modifiables après création (règle 5). */
    public const COLONNES_VERIFICATION = ['hausse_reelle_3m', 'resultat_verif'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Cloture $cloture) {
            $interdites = array_diff(array_keys($cloture->getDirty()), [...self::COLONNES_VERIFICATION, 'updated_at']);
            if ($interdites !== []) {
                throw new LogicException("Clôture {$cloture->mois->format('Y-m')} figée : colonnes non modifiables ".implode(', ', $interdites).'.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'mois' => 'date',
            'mode_calcul' => ModeCalcul::class,
            'par30' => 'decimal:6',
            'par60' => 'decimal:6',
            'par90' => 'decimal:6',
            'par180' => 'decimal:6',
            'par365' => 'decimal:6',
            'impaye' => 'decimal:6',
            'rr_61_90' => 'decimal:6',
            'encours' => 'integer',
            'par90_mt' => 'integer',
            'radiations' => 'integer',
            'tranche_61_90' => 'decimal:6',
            'bond' => 'decimal:6',
            'hausse_3m' => 'decimal:6',
            'seuil_bond' => 'decimal:8',
            'part_180' => 'decimal:6',
            'part_365' => 'decimal:6',
            'rr_connu' => 'decimal:6',
            'nb_bonds' => 'integer',
            'niveau' => Niveau::class,
            'statut' => Statut::class,
            'hausse_reelle_3m' => 'decimal:6',
            'resultat_verif' => ResultatVerification::class,
        ];
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /** @return HasMany<Decision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    /** @return BelongsTo<Parametre, $this> */
    public function parametres(): BelongsTo
    {
        return $this->belongsTo(Parametre::class, 'parametres_id');
    }

    /** Valeurs stockées, telles quelles, pour le moteur de calcul. */
    public function versMoisCloture(): MoisCloture
    {
        return new MoisCloture(
            mois: $this->mois->format('Y-m'),
            par60: $this->par60,
            par90: $this->par90,
            par180: $this->par180,
            par365: $this->par365,
            rr6190: $this->rr_61_90,
        );
    }
}
