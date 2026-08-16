<?php

declare(strict_types=1);

namespace App\Livewire\Planos;

use App\Models\Plano;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.painel', ['secao' => 'planos'])]
final class Detalhes extends Component
{
    use AuthorizesRequests;

    public Plano $plano;

    public function mount(Plano $plano): void
    {
        $this->authorize('view', $plano);

        $this->plano = $plano;
    }

    /**
     * Desativar é o caminho normal; excluir é exceção.
     *
     * Plano desativado some da hora de matricular, mas continua existindo para
     * as matrículas que já apontam para ele — o histórico precisa saber o que
     * foi contratado.
     */
    public function alternarAtivo(): void
    {
        $this->authorize('update', $this->plano);

        $this->plano->update(['ativo' => ! $this->plano->ativo]);

        session()->flash('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => $this->plano->ativo
                ? 'Plano reativado.'
                : 'Plano desativado. Some das novas matrículas; as atuais seguem valendo.',
        ]);
    }

    public function excluir(): void
    {
        $this->authorize('delete', $this->plano);

        $this->plano->delete();

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Plano excluído.']);

        $this->redirectRoute('planos.lista', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.planos.detalhes', [
            'matriculasVigentes' => $this->plano->matriculas()->vigentes()->count(),
            'matriculasTotal' => $this->plano->matriculas()->count(),
        ])->title($this->plano->nome);
    }
}
