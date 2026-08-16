<?php

declare(strict_types=1);

namespace App\Livewire\Acesso;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A troca da senha temporária.
 *
 * Sem barra lateral e sem menu: quem chega aqui tem uma coisa para fazer, e
 * qualquer outro caminho na tela é convite para adiar.
 */
#[Layout('layouts.acesso')]
#[Title('Escolha sua senha')]
final class TrocarSenha extends Component
{
    public string $atual = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public function salvar(): void
    {
        $usuario = auth()->user();

        $this->validate([
            'atual' => ['required', 'string'],
            'senha' => [
                'required', 'confirmed',
                /*
                 * `uncompromised` consulta a lista pública de senhas já
                 * vazadas — sem enviar a senha, só um prefixo do hash. É a
                 * checagem que mais evita conta invadida, e custa nada.
                 */
                Password::min(8)->letters()->numbers()->uncompromised(),
            ],
        ], [
            'atual.required' => 'Digite a senha temporária que você recebeu.',
            'senha.required' => 'Escolha uma senha.',
            'senha.confirmed' => 'As duas senhas não são iguais.',
            'senha.min' => 'A senha precisa ter ao menos 8 caracteres.',
            'senha.uncompromised' => 'Esta senha já apareceu em vazamentos públicos. Escolha outra.',
        ]);

        if (! Hash::check($this->atual, $usuario->password)) {
            $this->addError('atual', 'A senha atual não confere.');

            return;
        }

        if (Hash::check($this->senha, $usuario->password)) {
            // Repetir a temporária deixaria a senha que o gestor conhece
            // valendo para sempre — que é exatamente o que esta tela evita.
            $this->addError('senha', 'A senha nova precisa ser diferente da temporária.');

            return;
        }

        $usuario->forceFill([
            'password' => $this->senha,
            'deve_trocar_senha' => false,
        ])->save();

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Senha definida. Bem-vindo ao Pulso.']);

        $this->redirectRoute('painel.inicio', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.acesso.trocar-senha');
    }
}
