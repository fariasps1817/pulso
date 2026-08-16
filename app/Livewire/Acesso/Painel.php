<?php

declare(strict_types=1);

namespace App\Livewire\Acesso;

use App\Enums\SituacaoComando;
use App\Models\Acesso;
use App\Models\ComandoDispositivo;
use App\Models\DispositivoAcesso;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A tela do controle de acesso.
 *
 * Responde três perguntas, e nessa ordem de urgência: quem está na academia
 * agora, quem passou hoje, e o aparelho está falando com o Pulso.
 *
 * A terceira parece a menos importante e é a que evita o pior dos problemas:
 * um leitor mudo não dá erro em lugar nenhum. A catraca continua girando, os
 * alunos continuam entrando, e o Pulso simplesmente para de saber — e só se
 * descobre semanas depois, quando o Radar diz que a academia inteira sumiu.
 */
#[Layout('layouts.painel', ['secao' => 'acesso'])]
#[Title('Acesso')]
final class Painel extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('frequencia.ver'), 403);
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $podeVerAparelhos = $usuario->can('dispositivo.ver');

        return view('livewire.acesso.painel', [
            'presentes' => $this->presentes(),
            'movimento' => $this->movimentoDeHoje(),
            'aparelhos' => $podeVerAparelhos ? $this->aparelhos() : null,
            'pendencias' => $podeVerAparelhos ? $this->pendencias() : null,
        ]);
    }

    /**
     * Quem entrou e ainda não saiu.
     *
     * @return Collection<int, Acesso>
     */
    private function presentes()
    {
        return $this->naMinhaUnidade(Acesso::query())
            ->presentes()
            ->whereNotNull('aluno_id')
            ->with('aluno')
            ->orderByDesc('ocorreu_em')
            ->get();
    }

    /** @return Collection<int, Acesso> */
    private function movimentoDeHoje()
    {
        return $this->naMinhaUnidade(Acesso::query())
            ->where('ocorreu_em', '>=', CarbonImmutable::now()->startOfDay())
            ->with('aluno')
            ->orderByDesc('ocorreu_em')
            ->limit(30)
            ->get();
    }

    /** @return Collection<int, DispositivoAcesso> */
    private function aparelhos()
    {
        return $this->naMinhaUnidade(DispositivoAcesso::query())
            ->orderBy('nome')
            ->get();
    }

    /**
     * O que ainda não chegou ao aparelho, e o que ele recusou.
     *
     * Comando confirmado não aparece: a fila resolvida é histórico, e o que
     * a tela precisa mostrar é o que exige atenção.
     *
     * @return Collection<int, ComandoDispositivo>
     */
    private function pendencias()
    {
        return $this->naMinhaUnidade(ComandoDispositivo::query())
            ->whereIn('situacao', [
                SituacaoComando::Pendente,
                SituacaoComando::Entregue,
                SituacaoComando::Falhou,
            ])
            ->with('aluno')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    private function naMinhaUnidade(Builder $consulta): Builder
    {
        $usuario = auth()->user();

        return $consulta->when(
            ! $usuario->acessa_todas_unidades,
            fn (Builder $q) => $q->whereIn('unidade_id', $usuario->unidadesAcessiveis()->pluck('id')),
        );
    }
}
