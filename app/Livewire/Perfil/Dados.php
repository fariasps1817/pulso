<?php

declare(strict_types=1);

namespace App\Livewire\Perfil;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Meu perfil — o que a pessoa muda na própria conta.
 *
 * Separado da tela de usuários de propósito. Lá o gestor administra a EQUIPE:
 * papel, unidade, alcance. Aqui a pessoa cuida de si — e ninguém edita a
 * própria conta por aquela tela, porque um dono que se rebaixasse por engano
 * trancaria a academia.
 *
 * O que NÃO está aqui também é decisão: papel, unidade e sessão única são da
 * gerência. Se a pessoa pudesse mudar o próprio papel, a hierarquia inteira
 * seria decorativa.
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
#[Title('Meu perfil')]
final class Dados extends Component
{
    public string $name = '';

    public string $email = '';

    public string $senha_atual = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public function mount(): void
    {
        $usuario = auth()->user();

        $this->name = (string) $usuario->name;
        $this->email = (string) $usuario->email;
    }

    public function salvarDados(): void
    {
        $this->resetValidation();

        $usuario = auth()->user();

        $dados = [
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
        ];

        Validator::make($dados, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            /*
             * Único no sistema inteiro, como no cadastro pelo gestor: o login
             * recebe só o e-mail e não tem como perguntar de qual academia se
             * trata.
             */
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.unique' => 'Este e-mail já entra no Pulso.',
        ])->validate();

        $usuario->update($dados);

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Perfil atualizado.']);
    }

    /**
     * A senha atual é exigida mesmo com a sessão aberta.
     *
     * É a proteção contra o computador do balcão deixado destravado: sem ela,
     * quem passasse trocaria a senha e ficaria com a conta.
     */
    public function salvarSenha(): void
    {
        $this->resetValidation();

        $usuario = auth()->user();

        $this->validate([
            'senha_atual' => ['required', 'string'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ], [
            'senha_atual.required' => 'Digite sua senha atual.',
            'senha.required' => 'Escolha a nova senha.',
            'senha.confirmed' => 'As duas senhas não são iguais.',
            'senha.min' => 'A senha precisa ter ao menos 8 caracteres.',
            'senha.uncompromised' => 'Esta senha já apareceu em vazamentos públicos. Escolha outra.',
        ]);

        if (! Hash::check($this->senha_atual, $usuario->password)) {
            $this->addError('senha_atual', 'A senha atual não confere.');

            return;
        }

        $usuario->forceFill([
            'password' => $this->senha,
            'deve_trocar_senha' => false,
        ])->save();

        $this->reset(['senha_atual', 'senha', 'senha_confirmation']);

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Senha alterada.']);
    }

    public function render(): View
    {
        return view('livewire.perfil.dados');
    }
}
