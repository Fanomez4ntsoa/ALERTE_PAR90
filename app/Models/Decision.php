<?php

namespace App\Models;

use App\Enums\EtatDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Une décision de la direction pour un mois de clôture (section 9).
 *
 * @property int $id
 * @property int $cloture_id
 * @property string $action
 * @property string $responsable
 * @property Carbon $echeance
 * @property string|null $resultat
 * @property EtatDecision $etat
 * @property string|null $motif_annulation
 * @property int $auteur_id
 * @property int|null $complete_par
 * @property Carbon|null $complete_le
 * @property int|null $annule_par
 * @property Carbon|null $annule_le
 * @property Carbon $created_at
 */
#[Fillable(['cloture_id', 'action', 'responsable', 'echeance', 'resultat', 'etat', 'motif_annulation', 'auteur_id', 'complete_par', 'complete_le', 'annule_par', 'annule_le'])]
class Decision extends Model
{
    protected static function booted(): void
    {
        static::deleting(function () {
            throw new LogicException('Une décision ne se supprime pas : elle se complète ou s\'annule avec un motif.');
        });
    }

    protected function casts(): array
    {
        return [
            'echeance' => 'date',
            'etat' => EtatDecision::class,
            'complete_le' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cloture, $this> */
    public function cloture(): BelongsTo
    {
        return $this->belongsTo(Cloture::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complete_par');
    }

    /** @return BelongsTo<User, $this> */
    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }
}
