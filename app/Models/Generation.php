<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une cellule des onglets de générations : mois d'octroi × âge.
 *
 * @property int $id
 * @property Carbon $mois_octroi
 * @property int $age_mois
 * @property string|null $par30
 * @property string|null $par90
 * @property int $import_id
 */
#[Fillable(['mois_octroi', 'age_mois', 'par30', 'par90', 'import_id'])]
class Generation extends Model
{
    protected function casts(): array
    {
        return [
            'mois_octroi' => 'date',
            'age_mois' => 'integer',
            'par30' => 'decimal:6',
            'par90' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
