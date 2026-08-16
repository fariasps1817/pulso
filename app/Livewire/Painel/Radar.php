<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Services\Radar\Radar as Numeros;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O Radar — a primeira tela depois do login.
 *
 * Não é um painel de gráficos: é uma lista de coisas a fazer hoje. Quem
 * cobrar, quem ligar, quem parabenizar. Cada número leva à tela onde se
 * resolve o problema; número que não vira ação não entra aqui.
 */
#[Layout('layouts.painel', ['secao' => 'radar'])]
#[Title('Radar')]
final class Radar extends Component
{
    public function mount(): void
    {
        // Professor não entra: o Radar é, na maior parte, dinheiro.
        abort_unless(auth()->user()->can('radar.ver'), 403);
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $numeros = new Numeros(
            academia: $usuario->academia,
            unidades: $usuario->acessa_todas_unidades
                ? null
                : $usuario->unidadesAcessiveis()->pluck('id')->all(),
        );

        return view('livewire.painel.radar', [
            'vencidas' => $numeros->vencidas(),
            'vencemHoje' => $numeros->vencemHoje(),
            'listaDeVencidas' => $numeros->listaDeVencidas(),
            'catracaEmUso' => $numeros->catracaEmUso(),
            'dias' => $numeros->diasDeBaixaFrequencia(),
            'totalDeSumidos' => $numeros->totalDeSumidos(),
            'sumidos' => $numeros->sumidos(),
            'aniversariantes' => $numeros->aniversariantesDeHoje(),
            // Faturamento é outra pergunta, e não é de todo mundo.
            'recebido' => $usuario->can('relatorio_financeiro.ver')
                ? $numeros->recebidoNoMes()
                : null,
        ]);
    }
}
