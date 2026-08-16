<?php

declare(strict_types=1);

namespace App\Livewire\Configuracoes;

use App\Models\Academia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * As três regras que a academia ajusta — e que mudam o comportamento do
 * sistema todo dia.
 *
 * Nenhuma delas é preferência de tela: a primeira decide quando a catraca
 * para de liberar, a segunda decide quem aparece no Radar como sumido, e a
 * terceira decide quem o balcão consegue matricular. Por isso ficam com o
 * dono, e por isso cada campo diz na tela o que acontece se for mexido.
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
#[Title('Regras da academia')]
final class RegrasDaAcademia extends Component
{
    use AuthorizesRequests;

    public Academia $academia;

    public string $dias_tolerancia_bloqueio = '5';

    public string $dias_baixa_frequencia = '15';

    public string $idade_minima = '12';

    public function mount(): void
    {
        $this->academia = auth()->user()->academia;

        $this->authorize('configurar', $this->academia);

        $this->dias_tolerancia_bloqueio = (string) $this->academia->dias_tolerancia_bloqueio;
        $this->dias_baixa_frequencia = (string) $this->academia->dias_baixa_frequencia;
        $this->idade_minima = (string) $this->academia->idade_minima;
    }

    public function salvar(): void
    {
        $this->authorize('configurar', $this->academia);

        $this->validate([
            /*
             * Teto de 30 dias na tolerância: acima disso a catraca deixa de
             * funcionar como instrumento de cobrança, que é metade da razão
             * de ela existir. Zero é permitido — bloquear no dia seguinte ao
             * vencimento é rigoroso, mas é escolha legítima da academia.
             */
            'dias_tolerancia_bloqueio' => ['required', 'integer', 'min:0', 'max:30'],
            // Abaixo de 7 dias, quem viaja numa semana vira "sumido".
            'dias_baixa_frequencia' => ['required', 'integer', 'min:7', 'max:90'],
            'idade_minima' => ['required', 'integer', 'min:0', 'max:21'],
        ], [
            'dias_tolerancia_bloqueio.max' => 'Acima de 30 dias a catraca deixa de servir como cobrança.',
            'dias_baixa_frequencia.min' => 'Abaixo de 7 dias, quem viajou uma semana já apareceria como sumido.',
        ]);

        $this->academia->update([
            'dias_tolerancia_bloqueio' => (int) $this->dias_tolerancia_bloqueio,
            'dias_baixa_frequencia' => (int) $this->dias_baixa_frequencia,
            'idade_minima' => (int) $this->idade_minima,
        ]);

        $this->academia->refresh();

        session()->flash('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => 'Regras atualizadas. Elas valem a partir de agora, sem mexer no passado.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.configuracoes.regras-da-academia');
    }
}
