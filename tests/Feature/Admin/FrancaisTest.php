<?php

/*
 * Étape 6 : aucune page ne contient de texte anglais du kit. On analyse les
 * textes affichés par les gabarits Vue (texte, titres, libellés, attributs
 * d'accessibilité), puis on vérifie les pages d'erreur rendues par le serveur.
 */

use App\Import\ServiceImport;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FabriqueFichier;

/** Libellés anglais du kit de démarrage, qui ne doivent plus apparaître. */
const LIBELLES_ANGLAIS = [
    'Log in', 'Log out', 'Sign up', 'Settings', 'Profile', 'Password', 'Email',
    'Remember me', 'Forgot', 'Save', 'Saved', 'Dashboard', 'Platform',
    'Repository', 'Documentation', 'Appearance', 'Light', 'Dark', 'System',
    'Forbidden', 'Not Found', 'Confirm', 'Reset', 'Resend', 'Delete', 'Cancel',
    'Close', 'Loading', 'Toggle', 'Update', 'Name', 'Sidebar', 'Two-factor',
    'Passkey', 'Verify', 'Continue', 'Back', 'Search',
];

/** Textes affichés d'un fichier Vue : contenu du gabarit et attributs visibles. */
function textesAffiches(string $fichier): array
{
    $source = file_get_contents($fichier);
    preg_match('/<template>(.*)<\/template>/s', $source, $gabarit);
    $html = preg_replace('/\{\{.*?\}\}/s', ' ', $gabarit[1] ?? '');

    preg_match_all('/>([^<>]+)</', (string) $html, $textes);
    preg_match_all('/\b(?:title|description|placeholder|aria-label|label|alt)="([^"]+)"/', (string) $html, $attributs);
    preg_match_all("/\b(?:title|description|label|libelle):\s*'([^']+)'/", $source, $options);

    return array_values(array_filter(array_map('trim', [...$textes[1], ...$attributs[1], ...$options[1]])));
}

it("n'affiche aucun libellé anglais du kit dans les gabarits", function () {
    $fichiers = [
        ...glob(resource_path('js/pages/*.vue')),
        ...glob(resource_path('js/pages/*/*.vue')),
        ...glob(resource_path('js/layouts/*.vue')),
        ...glob(resource_path('js/layouts/*/*.vue')),
        ...glob(resource_path('js/components/*.vue')),
        ...glob(resource_path('js/components/*/*.vue')),
        ...glob(resource_path('js/components/ui/*/*.vue')),
    ];
    expect($fichiers)->not->toBeEmpty();

    $trouves = [];
    foreach ($fichiers as $fichier) {
        foreach (textesAffiches($fichier) as $texte) {
            foreach (LIBELLES_ANGLAIS as $anglais) {
                if (preg_match('/\b'.preg_quote($anglais, '/').'\b/', $texte)) {
                    $trouves[] = str_replace(resource_path('js/'), '', $fichier)." : « {$texte} »";
                }
            }
        }
    }

    expect($trouves)->toBe([]);
});

it('affiche les pages 403 et 404 en français', function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 4)), analyste());
    FabriqueFichier::nettoyer();

    $this->actingAs(direction())
        ->get(route('admin.imports'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Erreur')
            ->where('statut', 403)
            ->where('titre', 'Accès refusé')
            ->where('message', 'Votre rôle ne donne pas accès à cette page ou à cette action.'));

    $this->get('/adresse-inconnue')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Erreur')
            ->where('statut', 404)
            ->where('titre', 'Page introuvable'));

    $this->get('/dashboard/2030-01')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Erreur')->where('titre', 'Page introuvable'));
});

it('envoie les messages du serveur en français', function () {
    expect(__('auth.failed'))->toBe('Adresse e-mail ou mot de passe incorrect.')
        ->and(__('passwords.sent'))->toBe('Un lien de réinitialisation vous a été envoyé par e-mail.')
        ->and(__('Profile updated.'))->toBe('Profil enregistré.')
        ->and(__('Password updated.'))->toBe('Mot de passe enregistré.');

    $this->post(route('login.store'), ['email' => 'inconnu@exemple.mg', 'password' => 'faux'])
        ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
});
