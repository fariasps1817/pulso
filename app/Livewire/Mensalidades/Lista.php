<?php

declare(strict_types=1);

namespace App\Livewire\Mensalidades;

use App\Enums\SituacaoMensalidade;
use App\Models\Mensalidade;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mensalidades — a tela que a recepção abre para receber.
 *
 * "Vencida" NÃO é situação no banco: é `aberta` com vencimento no passado. O
 * filtro aqui monta essa condição, e é por isso que o Radar nunca mente —
 * não depende de nenhuma rotina ter virado uma chave de madrugada.
 */
#[Layout('layouts.painel', ['secao' => 'mensalidades'])]
#[Title('Mensalidades')]
final class Lista extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'filtro', except: 'em_aberto')]
    public string $filtro = 'em_aberto';

    public function mount(): void
    {
        $this->authorize('viewAny', Mensalidade::class);
    }

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
        $consulta = $this->consultar();

        return view('livewire.mensalidades.lista', [
            'mensalidades' => $consulta,
            'totais' => $this->totais(),
        ]);
    }

    /**
     * Os três números do topo, na mesma base do Radar.
     *
     * @return array{vencido: string, vence_hoje: string, em_aberto: string}
     */
    private function totais(): array
    {
        $hoje = CarbonImmutable::now()->startOfDay();

        return [
            'vencido' => (string) $this->base()->vencidas($hoje)->sum('valor'),
            'vence_hoje' => (string) $this->base()->vencendoEm($hoje)->sum('valor'),
            'em_aberto' => (string) $this->base()->emAberto()->sum('valor'),
        ];
    }

    /** @return Builder<Mensalidade> */
    private function base(): Builder
    {
        $usuario = auth()->user();

        return Mensalidade::query()->when(
            ! $usuario->acessa_todas_unidades,
            fn (Builder $q) => $q->whereIn('unidade_id', $usuario->unidadesAcessiveis()->pluck('id')),
        );
    }

    /** @return LengthAwarePaginator<int, Mensalidade> */
    private function consultar(): LengthAwarePaginator
    {
        $hoje = CarbonImmutable::now()->startOfDay();

        return $this->base()
            ->with(['aluno', 'unidade'])
            ->when($this->termo !== '', fn (Builder $q) => $q->whereHas(
                'aluno',
                fn (Builder $aluno) => $aluno->where('nome', 'ilike', '%'.trim($this->termo).'%'),
            ))
            ->when($this->filtro === 'vencidas', fn (Builder $q) => $q->vencidas($hoje))
            ->when($this->filtro === 'vence_hoje', fn (Builder $q) => $q->vencendoEm($hoje))
            ->when($this->filtro === 'em_aberto', fn (Builder $q) => $q->emAberto())
            ->when($this->filtro === 'pagas', fn (Builder $q) => $q->where('situacao', SituacaoMensalidade::Paga))
            // Vencimento mais antigo primeiro: é a ordem da cobrança.
            ->orderBy('vencimento')
            ->paginate(25);
    }
}
