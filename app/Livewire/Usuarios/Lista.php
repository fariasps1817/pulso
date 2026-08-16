<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Services\Acesso\SenhaTemporaria;
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

    /** Mostrada uma vez, logo depois de redefinir. */
    public ?string $senhaTemporaria = null;

    public ?string $senhaDe = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * Gera uma senha nova para quem perdeu a sua.
     *
     * O gestor repassa e a pessoa é obrigada a trocá-la no primeiro acesso —
     * a mesma regra do cadastro, para o gestor nunca ficar sabendo a senha
     * definitiva de ninguém.
     */
    public function redefinirSenha(int $id): void
    {
        $alvo = User::query()
            ->where('academia_id', auth()->user()->academia_id)
            ->findOrFail($id);

        $this->authorize('redefinirSenha', $alvo);

        $this->senhaTemporaria = SenhaTemporaria::redefinirPara($alvo, auth()->user());
        $this->senhaDe = (string) $alvo->name;
    }

    public function fecharSenha(): void
    {
        $this->reset(['senhaTemporaria', 'senhaDe']);
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
