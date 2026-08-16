<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Academia\ContextoAcademia;
use App\Support\Catraca\AparelhoAtual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uma requisição, uma academia.
        $this->app->singleton(ContextoAcademia::class);

        // Uma requisição do aparelho biométrico, um aparelho.
        $this->app->singleton(AparelhoAtual::class);
    }

    public function boot(): void
    {
        /*
         * Acessar um relacionamento não carregado passa a estourar em vez de
         * disparar uma consulta silenciosa. Numa lista de 300 alunos, o
         * problema N+1 não aparece no ambiente local com 5 registros — aparece
         * na academia do cliente, no horário de pico.
         *
         * Só fora de produção: em produção, é melhor a página lenta do que a
         * página quebrada.
         */
        Model::preventLazyLoading(! $this->app->isProduction());

        // Atribuir coluna que não existe no model deixa de ser ignorado em
        // silêncio. Erro de digitação em `update()` é achado no mesmo dia.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
