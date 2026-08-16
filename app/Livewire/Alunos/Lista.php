<?php

declare(strict_types=1);

namespace App\Livewire\Alunos;

use App\Models\Aluno;
use App\Support\Documentos;
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
 * Lista de alunos — o padrão que todas as outras listas vão seguir.
 *
 * Busca ao digitar, sem recarregar a página: a recepção pesquisa com o aluno
 * esperando no balcão, e cada recarga é um segundo a mais de espera.
 */
#[Layout('layouts.painel', ['secao' => 'alunos'])]
#[Title('Alunos')]
final class Lista extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /**
     * Busca e filtro vão para a URL: assim o link é compartilhável, o botão
     * voltar do navegador funciona, e recarregar a página não perde o que a
     * pessoa já tinha filtrado.
     */
    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'situacao', except: 'todos')]
    public string $situacao = 'todos';

    public int $porPagina = 25;

    public function mount(): void
    {
        $this->authorize('viewAny', Aluno::class);

        $this->porPagina = (int) auth()->user()->preferencia('itens_por_pagina', 25);
    }

    /** Filtro novo recomeça da primeira página — senão a busca cai numa página vazia. */
    public function updatedTermo(): void
    {
        $this->resetPage();
    }

    public function updatedSituacao(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->reset('termo', 'situacao');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.alunos.lista', [
            'alunos' => $this->consultar(),
            'temFiltro' => $this->termo !== '' || $this->situacao !== 'todos',
        ]);
    }

    /** @return LengthAwarePaginator<int, Aluno> */
    private function consultar(): LengthAwarePaginator
    {
        return Aluno::query()
            // Sem isto, cada linha dispararia uma consulta para descobrir a
            // situação — 300 alunos custariam 301 idas ao banco.
            ->with('matriculaVigente')
            ->when($this->termo !== '', fn (Builder $q) => $this->aplicarBusca($q))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->whereHas('matriculaVigente'))
            ->when($this->situacao === 'sem_matricula', fn (Builder $q) => $q->whereDoesntHave('matriculaVigente'))
            ->orderBy('nome')
            ->paginate($this->porPagina);
    }

    /**
     * Busca por nome ou CPF.
     *
     * Digitou número, procura CPF; digitou texto, procura nome. A recepção não
     * deveria ter de escolher em qual campo pesquisar — ela tem o aluno na
     * frente e o documento na mão.
     *
     * @param  Builder<Aluno>  $consulta
     */
    private function aplicarBusca(Builder $consulta): void
    {
        $termo = trim($this->termo);
        $digitos = Documentos::apenasDigitos($termo);

        if ($digitos !== '' && strlen($digitos) >= 3) {
            $consulta->where('cpf', 'like', $digitos.'%');

            return;
        }

        /*
         * `ILIKE` para o começo do nome e `%` (similaridade do pg_trgm) para o
         * resto: "Ana" acha "Ana Beatriz" já na terceira letra, e "Joao Siva"
         * ainda acha "João Silva".
         */
        $consulta->where(function (Builder $q) use ($termo): void {
            $q->where('nome', 'ilike', $termo.'%')
                ->orWhereRaw('nome % ?', [$termo]);
        });
    }
}
