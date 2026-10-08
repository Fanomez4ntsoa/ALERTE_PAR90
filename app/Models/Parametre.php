<?php

namespace App\Models;

use App\Domain\Alerte\Parametres;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un jeu de paramètres du calcul (section 5), avec sa date d'effet.
 *
 * @property int $id
 * @property string $limite_par90
 * @property string $seuil_hausse_3m
 * @property int $centile_bond
 * @property int $minimum_bonds
 * @property int $horizon_verification
 * @property string $nom_institution
 * @property Carbon $date_effet
 */
#[Fillable(['limite_par90', 'seuil_hausse_3m', 'centile_bond', 'minimum_bonds', 'horizon_verification', 'nom_institution', 'date_effet'])]
class Parametre extends Model
{
    protected $table = 'parametres';

    protected function casts(): array
    {
        return [
            'limite_par90' => 'decimal:6',
            'seuil_hausse_3m' => 'decimal:6',
            'centile_bond' => 'integer',
            'minimum_bonds' => 'integer',
            'horizon_verification' => 'integer',
            'date_effet' => 'date',
        ];
    }

    /**
     * Jeu en vigueur aujourd'hui. S'il n'en existe aucun, le premier est créé
     * à partir de config/alerte.php.
     */
    public static function enVigueur(): self
    {
        return self::query()
            ->whereDate('date_effet', '<=', Carbon::today())
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->first()
            ?? self::create([...self::depuisConfig(), 'date_effet' => Carbon::today()]);
    }

    public function versDomaine(): Parametres
    {
        return Parametres::depuisConfig($this->only(['limite_par90', 'seuil_hausse_3m', 'centile_bond', 'minimum_bonds', 'horizon_verification']));
    }

    /** @return array<string, mixed> */
    private static function depuisConfig(): array
    {
        $config = config('alerte');
        Parametres::depuisConfig($config);

        return [
            'limite_par90' => $config['limite_par90'],
            'seuil_hausse_3m' => $config['seuil_hausse_3m'],
            'centile_bond' => $config['centile_bond'],
            'minimum_bonds' => $config['minimum_bonds'],
            'horizon_verification' => $config['horizon_verification'],
            'nom_institution' => $config['nom_institution'],
        ];
    }
}
