<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\PageErreur;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Pages d'erreur en français, dans le style du produit (étape 6).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $toujours = [403, 404, 419, 429];
            $horsDeveloppement = [500, 503];
            $statut = $response->getStatusCode();

            if ($request->expectsJson()
                || ! (in_array($statut, $toujours, true)
                    || (in_array($statut, $horsDeveloppement, true) && ! app()->environment(['local', 'testing'])))) {
                return $response;
            }

            return Inertia::render('Erreur', PageErreur::pour($statut))
                ->toResponse($request)
                ->setStatusCode($statut);
        });
    })->create();
