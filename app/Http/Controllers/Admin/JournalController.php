<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Tableau\Format;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Section Journal du back-office, en lecture seule, du plus récent au plus ancien. */
class JournalController extends Controller
{
    private const ACTIONS = [
        'import.import' => 'Import',
        'import.refus' => 'Fichier refusé',
        'import.annulation' => "Annulation d'import",
        'decision.ajout' => 'Décision ajoutée',
        'decision.complement' => 'Décision complétée',
        'decision.correction' => 'Résultat corrigé',
        'decision.annulation' => 'Décision annulée',
    ];

    public function __invoke(): Response
    {
        $page = Activity::query()->with('causer')->orderByDesc('id')->paginate(30);

        return Inertia::render('admin/Journal', [
            'entrees' => collect($page->items())->map(fn (Activity $a) => [
                'id' => $a->id,
                'date' => Format::horodatage($a->created_at),
                'qui' => $a->causer?->getAttribute('name') ?? Format::NON_RENSEIGNE,
                'action' => self::ACTIONS["{$a->log_name}.{$a->event}"] ?? (string) $a->description,
                'mois' => self::mois($a->properties['mois'] ?? null),
                'detail' => self::detail($a),
            ])->values()->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'pages' => $page->lastPage(),
                'precedente' => $page->previousPageUrl(),
                'suivante' => $page->nextPageUrl(),
            ],
        ]);
    }

    /** Un mois AAAA-MM ou une liste de mois, en clair. */
    private static function mois(mixed $mois): string
    {
        if (is_string($mois)) {
            return Format::mois(Carbon::createFromFormat('!Y-m', $mois));
        }
        if (is_array($mois) && $mois !== []) {
            return ImportController::periode($mois);
        }

        return Format::NON_RENSEIGNE;
    }

    private static function detail(Activity $a): string
    {
        $p = $a->properties;

        return match ("{$a->log_name}.{$a->event}") {
            'import.import' => "Fichier {$p['fichier']} ; ".count($p['avertissements'] ?? []).' avertissement(s) ; '.($p['generations'] ?? 0).' cellule(s) de générations',
            'import.refus' => "Fichier {$p['fichier']} ; ".implode(' ', $p['erreurs'] ?? []),
            'import.annulation' => "Fichier {$p['fichier']}",
            'decision.ajout' => "« {$p['action']} » ; responsable : {$p['responsable']} ; échéance : {$p['echeance']}",
            'decision.complement' => "Résultat : « {$p['resultat']} »",
            'decision.correction' => "Ancien résultat : « {$p['ancien_resultat']} » ; nouveau résultat : « {$p['nouveau_resultat']} »",
            'decision.annulation' => "Motif : « {$p['motif']} »",
            default => (string) $a->description,
        };
    }
}
