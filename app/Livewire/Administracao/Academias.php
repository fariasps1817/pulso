<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * As academias que usam o Pulso.
 *
 * O QUE ESTA TELA NÃO MOSTRA, E POR QUÊ
 *
 * Nenhum nome de aluno, nenhuma mensalidade, nenhum acesso. As políticas de
 * Row Level Security não abrem exceção para o super administrador, e é isso
 * que faz uma conta da equipe do Pulso comprometida não virar uma porta para
 * o dado de todos os clientes.
 *
 * O que aparece é o plano de controle: situação, unidades, usuários — e o
 * TOTAL de alunos, que é um número mantido pela própria academia, não uma
 * consulta aos alunos dela.
 */
#[Layout('layouts.administracao', ['secao' => 'academias'])]
#[Title('Academias')]
final class Academias extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'situacao', except: 'todas')]
    public string $filtro = 'todas';

    public function updatedTermo(): void
    {
        $this->resetPage();
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.administracao.academias', [
            'academias' => $this->consultar()->paginate(25),
            'situacoes' => SituacaoAcademia::cases(),
            /*
             * Os totais saem de uma consulta AGREGADA, não da página.
             * Somar o que está na tela daria "3 academias" na segunda página
             * de uma base com duzentas — e é justamente o número que decide
             * se a operação está crescendo.
             */
            'totais' => $this->totais(),
        ]);
    }

    /** @return array{academias: int, alunos: int, com_filial: int} */
    private function totais(): array
    {
        $base = Academia::query()->when(
            $this->filtro !== 'todas',
            fn (Builder $q) => $q->where('situacao', $this->filtro),
        );

        return [
            'academias' => $base->clone()->count(),
            'alunos' => (int) $base->clone()->sum('total_alunos_ativos'),
            'com_filial' => $base->clone()
                ->whereHas('unidades', fn (Builder $q) => $q->where('ativa', true), '>', 1)
                ->count(),
        ];
    }

    /** @return Builder<Academia> */
    private function consultar(): Builder
    {
        return Academia::query()
            // `unidades` e `users` são plano de controle: ficam fora do
            // isolamento, e por isso podem ser contados daqui.
            ->withCount([
                'unidades' => fn (Builder $q) => $q->where('ativa', true),
                'usuarios' => fn (Builder $q) => $q->where('ativo', true),
            ])
            ->when($this->termo !== '', fn (Builder $q) => $q->where(
                fn (Builder $busca) => $busca
                    ->where('nome', 'ilike', '%'.trim($this->termo).'%')
                    ->orWhere('cnpj', 'ilike', '%'.preg_replace('/\D/', '', $this->termo).'%'),
            ))
            ->when($this->filtro !== 'todas', fn (Builder $q) => $q->where('situacao', $this->filtro))
            ->orderBy('nome');
    }
}
