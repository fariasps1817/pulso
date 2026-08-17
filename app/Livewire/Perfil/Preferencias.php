<?php

declare(strict_types=1);

namespace App\Livewire\Perfil;

use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Preferências de quem está usando.
 *
 * Ficam no PERFIL, não no navegador: a equipe alterna entre o computador do
 * balcão e o próprio celular, e quem escolheu tema escuro num lugar espera
 * encontrá-lo no outro.
 *
 * A barra lateral já se salva sozinha ao ser recolhida — aqui ficam as que
 * precisam de uma escolha consciente.
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
#[Title('Preferências')]
final class Preferencias extends Component
{
    public string $tema = 'sistema';

    public string $itens_por_pagina = '25';

    public ?int $unidade_padrao_id = null;

    public function mount(): void
    {
        $usuario = auth()->user();
        $preferencias = $usuario->preferencias ?? [];

        $this->tema = $preferencias['tema'] ?? config('pulso.tema.padrao');
        $this->itens_por_pagina = (string) ($preferencias['itens_por_pagina'] ?? 25);
        $this->unidade_padrao_id = $usuario->unidade_padrao_id;
    }

    public function salvar(): void
    {
        $this->resetValidation();

        $usuario = auth()->user();

        $this->validate([
            'tema' => ['required', Rule::in(config('pulso.tema.opcoes'))],
            'itens_por_pagina' => ['required', 'in:10,25,50,100'],
            /*
             * A unidade padrão só pode ser uma das que a pessoa alcança — e
             * quem não pode alternar não escolhe coisa nenhuma. Sem essa
             * checagem, bastaria trocar o número no formulário para abrir a
             * filial que a gerência travou.
             */
            'unidade_padrao_id' => [
                'nullable',
                Rule::in($this->unidadesDisponiveis()->pluck('id')),
            ],
        ], [
            'unidade_padrao_id.in' => 'Você não tem acesso a essa unidade.',
        ]);

        $usuario->forceFill([
            'preferencias' => array_merge($usuario->preferencias ?? [], [
                'tema' => $this->tema,
                'itens_por_pagina' => (int) $this->itens_por_pagina,
            ]),
        ]);

        if ($usuario->pode_alternar_unidade && $this->unidade_padrao_id !== null) {
            $usuario->unidade_padrao_id = $this->unidade_padrao_id;
        }

        $usuario->save();

        session()->flash('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => 'Preferências salvas. O tema vale em qualquer aparelho onde você entrar.',
        ]);

        // O tema é aplicado no HTML antes da primeira pintura, então a troca
        // só aparece de verdade recarregando a tela.
        $this->redirectRoute('perfil.preferencias', navigate: false);
    }

    /** @return Collection<int, Unidade> */
    private function unidadesDisponiveis()
    {
        return auth()->user()->unidadesAcessiveis();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('livewire.perfil.preferencias', [
            'unidades' => $usuario->pode_alternar_unidade
                ? $this->unidadesDisponiveis()
                : collect(),
        ]);
    }
}
