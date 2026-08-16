<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Support\Academia\Papeis;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A equipe da academia.
 *
 * Inativo aparece por padrão, ao contrário do que a lista de alunos faz: uma
 * equipe tem cinco pessoas, não trezentas, e a pergunta mais comum aqui é
 * "por que fulano não consegue entrar?".
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
#[Title('Usuários')]
final class Lista extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function render(): View
    {
        return view('livewire.usuarios.lista', [
            'usuarios' => $this->consultar(),
            'papeis' => Papeis::ROTULOS,
        ]);
    }

    /** @return Collection<int, User> */
    private function consultar(): Collection
    {
        $usuario = auth()->user();

        return User::query()
            /*
             * `users` fica FORA do Row Level Security — a autenticação
             * acontece antes de existir "academia atual". Então o filtro por
             * academia aqui não é conveniência: é a única barreira.
             */
            ->where('academia_id', $usuario->academia_id)
            ->when($this->termo !== '', fn (Builder $q) => $q->where(
                fn (Builder $busca) => $busca
                    ->where('name', 'ilike', '%'.trim($this->termo).'%')
                    ->orWhere('email', 'ilike', '%'.trim($this->termo).'%'),
            ))
            ->with(['unidadePadrao', 'roles'])
            ->orderByDesc('ativo')
            ->orderBy('name')
            ->get();
    }
}
