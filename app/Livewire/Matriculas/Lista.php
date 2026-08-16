<?php

declare(strict_types=1);

namespace App\Livewire\Matriculas;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.painel', ['secao' => 'matriculas'])]
#[Title('Matrículas')]
final class Lista extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'situacao', except: 'vigentes')]
    public string $situacao = 'vigentes';

    public function mount(): void
    {
        $this->authorize('viewAny', Matricula::class);
    }

    public function updatedTermo(): void
    {
        $this->resetPage();
    }

    public function updatedSituacao(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.matriculas.lista', [
            'matriculas' => $this->consultar(),
            'mostraValores' => auth()->user()->can('verValores', Matricula::class),
        ]);
    }

    /** @return LengthAwarePaginator<int, Matricula> */
    private function consultar(): LengthAwarePaginator
    {
        $usuario = auth()->user();

        return Matricula::query()
            ->with(['aluno', 'plano', 'unidade'])
            /*
             * Quem não acessa todas as unidades só vê as matrículas das suas.
             * O RLS isola academias; entre filiais da mesma academia, o corte
             * é este.
             */
            ->when(
                ! $usuario->acessa_todas_unidades,
                fn (Builder $q) => $q->whereIn('unidade_id', $usuario->unidadesAcessiveis()->pluck('id')),
            )
            ->when($this->termo !== '', fn (Builder $q) => $q->whereHas(
                'aluno',
                fn (Builder $aluno) => $aluno->where('nome', 'ilike', '%'.trim($this->termo).'%'),
            ))
            ->when($this->situacao === 'vigentes', fn (Builder $q) => $q->vigentes())
            ->when(
                in_array($this->situacao, array_column(SituacaoMatricula::cases(), 'value'), true),
                fn (Builder $q) => $q->where('situacao', $this->situacao),
            )
            ->orderByDesc('inicio_em')
            ->paginate(25);
    }
}
