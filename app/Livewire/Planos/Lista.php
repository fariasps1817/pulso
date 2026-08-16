<?php

declare(strict_types=1);

namespace App\Livewire\Planos;

use App\Models\Plano;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.painel', ['secao' => 'planos'])]
#[Title('Planos')]
final class Lista extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'situacao', except: 'ativos')]
    public string $situacao = 'ativos';

    public function mount(): void
    {
        $this->authorize('viewAny', Plano::class);
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
        return view('livewire.planos.lista', [
            'planos' => $this->consultar(),
        ]);
    }

    /** @return LengthAwarePaginator<int, Plano> */
    private function consultar(): LengthAwarePaginator
    {
        return Plano::query()
            // A contagem responde à pergunta que o dono faz ao olhar a lista:
            // "quantos alunos estão neste plano?".
            ->withCount('matriculasVigentes')
            ->when($this->termo !== '', fn (Builder $q) => $q->where('nome', 'ilike', '%'.trim($this->termo).'%'))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->orderBy('nome')
            ->paginate(25);
    }
}
