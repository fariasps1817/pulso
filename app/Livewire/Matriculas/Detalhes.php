<?php

declare(strict_types=1);

namespace App\Livewire\Matriculas;

use App\Models\Matricula;
use App\Rules\DataBrasileira;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha da matrícula, com as transições do ciclo de vida.
 *
 * Cada ação confere de onde a matrícula está saindo. Sem isso, um duplo
 * clique ou uma aba antiga faria uma matrícula encerrada voltar a ativa sem
 * ninguém perceber.
 */
#[Layout('layouts.painel', ['secao' => 'matriculas'])]
final class Detalhes extends Component
{
    use AuthorizesRequests;

    public Matricula $matricula;

    /** Conversão de experiência em matrícula regular. */
    public string $contrato_assinado_em = '';

    public string $dia_vencimento = '5';

    public string $motivo_encerramento = '';

    public function mount(Matricula $matricula): void
    {
        $this->authorize('view', $matricula);

        $this->matricula = $matricula->load(['aluno', 'plano', 'unidade']);

        $hoje = CarbonImmutable::now();
        $this->contrato_assinado_em = $hoje->format('d/m/Y');
        $this->dia_vencimento = (string) $matricula->dia_vencimento;
    }

    public function converter(): void
    {
        $this->authorize('update', $this->matricula);

        if (! $this->matricula->podeSerConvertida()) {
            $this->addError('conversao', 'Esta matrícula não está em experiência.');

            return;
        }

        $this->validate([
            'contrato_assinado_em' => ['required', new DataBrasileira],
            'dia_vencimento' => ['required', 'integer', 'min:1', 'max:28'],
        ], [
            'contrato_assinado_em.required' => 'Informe a data em que o contrato foi assinado.',
            'dia_vencimento.max' => 'O dia de vencimento vai até 28: dia 31 não existe em fevereiro.',
        ]);

        $this->matricula->converterParaRegular(
            DataBrasileira::converter($this->contrato_assinado_em),
            (int) $this->dia_vencimento,
        );

        $this->recarregar('Experiência convertida em matrícula.');
    }

    public function suspender(): void
    {
        $this->authorize('update', $this->matricula);

        if (! $this->matricula->podeSerSuspensa()) {
            return;
        }

        $this->matricula->suspender();

        $this->recarregar('Matrícula trancada. Não gera mensalidade e não libera a catraca.');
    }

    public function reativar(): void
    {
        $this->authorize('update', $this->matricula);

        if (! $this->matricula->podeSerReativada()) {
            return;
        }

        $this->matricula->reativar();

        $this->recarregar('Matrícula reativada.');
    }

    public function encerrar(): void
    {
        $this->authorize('encerrar', $this->matricula);

        if (! $this->matricula->podeSerEncerrada()) {
            return;
        }

        $this->matricula->encerrar(
            trim($this->motivo_encerramento) !== '' ? trim($this->motivo_encerramento) : null,
        );

        $this->recarregar('Matrícula encerrada.');
    }

    private function recarregar(string $mensagem): void
    {
        $this->matricula = $this->matricula->fresh(['aluno', 'plano', 'unidade']);

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => $mensagem]);
    }

    public function render(): View
    {
        return view('livewire.matriculas.detalhes', [
            'mostraValores' => auth()->user()->can('verValores', Matricula::class),
            'experienciaEsgotada' => $this->matricula->podeSerConvertida()
                && $this->matricula->experienciaEsgotada(),
        ])->title('Matrícula de '.$this->matricula->aluno->nome);
    }
}
