<?php

declare(strict_types=1);

namespace App\Livewire\Alunos;

use App\Models\Aluno;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha do aluno.
 *
 * Conteúdo em blocos, não em formulário desabilitado: quem abre a ficha quer
 * ler, e campo cinza sugere que dá para editar ali.
 */
#[Layout('layouts.painel', ['secao' => 'alunos'])]
final class Detalhes extends Component
{
    use AuthorizesRequests;

    public Aluno $aluno;

    public function mount(Aluno $aluno): void
    {
        $this->authorize('view', $aluno);

        $this->aluno = $aluno->load('matriculaVigente.plano', 'matriculaVigente.unidade');
    }

    /**
     * Exclusão lógica: o aluno some das listas, mas a mensalidade que ele
     * pagou em março continua existindo. O template biométrico, esse sim, é
     * apagado de verdade — junto, e não em passo separado que alguém pode
     * esquecer de encadear.
     */
    public function excluir(): void
    {
        $this->authorize('delete', $this->aluno);

        $this->aluno->credenciais()->whereNotNull('template')->get()
            ->each(fn ($credencial) => $credencial->apagarTemplate());

        $this->aluno->delete();

        session()->flash('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => 'Aluno excluído. O histórico financeiro foi preservado.',
        ]);

        $this->redirectRoute('alunos.lista', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.alunos.detalhes')->title($this->aluno->nome);
    }
}
