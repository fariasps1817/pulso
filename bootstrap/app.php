<?php

declare(strict_types=1);

use App\Http\Middleware\DefinirAcademiaAtual;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            DefinirAcademiaAtual::class,
        ]);

        /*
         * A ORDEM AQUI NÃO É DETALHE.
         *
         * `SubstituteBindings` é quem transforma /alunos/1 no model Aluno — e
         * essa busca já passa pelas políticas de Row Level Security. Se a
         * academia atual ainda não estiver definida quando ela roda, o banco
         * devolve zero linhas e a tela vira 404, mesmo o aluno existindo e
         * pertencendo a quem está logado.
         *
         * Era exatamente o que acontecia: `append` colocava este middleware
         * DEPOIS do SubstituteBindings.
         */
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: DefinirAcademiaAtual::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
