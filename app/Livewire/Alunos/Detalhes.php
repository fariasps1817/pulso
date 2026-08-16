<?php

declare(strict_types=1);

namespace App\Livewire\Alunos;

use App\Enums\ResultadoAcesso;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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
        return view('livewire.alunos.detalhes', [
            'matriculas' => $this->matriculas(),
            'mensalidades' => $this->podeVerValores() ? $this->mensalidades() : null,
            'emAberto' => $this->podeVerValores() ? $this->totalEmAberto() : null,
            'frequencia' => $frequencia = $this->frequencia(),
            'treinosNoMes' => $frequencia->where('ocorreu_em', '>=', now()->subDays(30))->count(),
            'catracaEmUso' => $this->catracaEmUso(),
        ])->title($this->aluno->nome);
    }

    /**
     * Professor vê a matrícula e a frequência, mas não dinheiro.
     *
     * A mesma regra da matriz de papéis: ele precisa saber em que plano o
     * aluno está e se está treinando, não quanto ele paga nem se deve.
     */
    private function podeVerValores(): bool
    {
        return auth()->user()->can('mensalidade.ver');
    }

    /**
     * Todas as matrículas, a mais recente primeiro.
     *
     * O histórico importa: "já foi aluno, saiu e voltou" é uma informação
     * diferente de "é aluno desde sempre", e some se a aba mostrar só a
     * vigente.
     *
     * @return Collection<int, Matricula>
     */
    private function matriculas(): Collection
    {
        return $this->aluno->matriculas()
            ->with(['plano', 'unidade'])
            ->orderByDesc('inicio_em')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, Mensalidade> */
    private function mensalidades(): Collection
    {
        return $this->aluno->mensalidades()
            ->orderByDesc('competencia')
            ->limit(24)
            ->get();
    }

    /** O que este aluno deve hoje, somando só o que está em aberto. */
    private function totalEmAberto(): string
    {
        return (string) $this->aluno->mensalidades()->emAberto()->sum('valor');
    }

    /**
     * As últimas passagens.
     *
     * Só entradas: a saída é a outra ponta da mesma visita, e listá-la
     * dobraria a lista sem acrescentar informação.
     *
     * @return Collection<int, Acesso>
     */
    private function frequencia(): Collection
    {
        return $this->aluno->acessos()
            ->entradas()
            ->where('resultado', ResultadoAcesso::Liberado)
            ->orderByDesc('ocorreu_em')
            ->limit(20)
            ->get();
    }

    /**
     * Sem passagem registrada na unidade, a aba não acusa ausência.
     *
     * Mesmo cuidado do Radar: dizer "nunca treinou" de um aluno assíduo só
     * porque a catraca ainda não foi integrada é pior do que não dizer nada.
     */
    private function catracaEmUso(): bool
    {
        return Acesso::query()
            ->where('unidade_id', $this->aluno->matriculaVigente?->unidade_id)
            ->exists();
    }
}
