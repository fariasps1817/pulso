<?php

declare(strict_types=1);

use App\Http\Middleware\DefinirAcademiaAtual;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * A academia atual é definida logo depois da sessão iniciar e antes
         * de qualquer consulta ao domínio — é ela que alimenta as políticas
         * de Row Level Security no PostgreSQL.
         */
        $middleware->web(append: [
            DefinirAcademiaAtual::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
