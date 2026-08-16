<?php

declare(strict_types=1);

namespace App\Livewire\Mensalidades;

use App\Enums\FormaPagamento;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Rules\DataBrasileira;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Recebimento no balcão.
 *
 * Uma mensalidade pode receber vários pagamentos — metade em dinheiro, metade
 * no Pix é rotina. A situação é recalculada a cada entrada e a cada estorno,
 * junto com o registro: separar os dois passos abriria a chance de registrar
 * o pagamento e esquecer a baixa, deixando quem já pagou aparecendo como
 * vencido no Radar.
 */
#[Layout('layouts.painel', ['secao' => 'mensalidades'])]
final class Detalhes extends Component
{
    use AuthorizesRequests;

    public Mensalidade $mensalidade;

    public string $valor = '';

    public string $forma = FormaPagamento::Dinheiro->value;

    public string $recebido_em = '';

    public string $motivo_estorno = '';

    public ?int $pagamentoParaEstornar = null;

    public function mount(Mensalidade $mensalidade): void
    {
        $this->authorize('view', $mensalidade);

        $this->mensalidade = $mensalidade->load(['aluno', 'unidade', 'matricula.plano', 'pagamentos.registradoPor']);

        // Sugere o que falta, que é o caso comum: o aluno paga tudo de uma vez.
        $this->valor = Formato::numeroDecimal($this->mensalidade->valorEmAberto());
        $this->recebido_em = CarbonImmutable::now()->format('d/m/Y');
    }

    public function registrar(): void
    {
        $this->authorize('receber', $this->mensalidade);

        $dados = $this->validate([
            'valor' => ['required'],
            'forma' => ['required', Rule::in(array_column(FormaPagamento::cases(), 'value'))],
            'recebido_em' => ['required', new DataBrasileira],
        ], [
            'valor.required' => 'Informe o valor recebido.',
            'recebido_em.required' => 'Informe a data do recebimento.',
        ]);

        $valor = Formato::decimalDoFormulario($dados['valor']);

        if ($valor === null || bccomp($valor, '0', 2) <= 0) {
            $this->addError('valor', 'O valor precisa ser maior que zero.');

            return;
        }

        /*
         * Receber mais do que se deve é erro de digitação, não generosidade do
         * aluno. Barrar aqui evita um acerto manual no banco depois.
         */
        if (bccomp($valor, $this->mensalidade->valorEmAberto(), 2) === 1) {
            $this->addError('valor', 'O valor é maior do que o que falta pagar.');

            return;
        }

        $this->mensalidade->registrarPagamento(
            $valor,
            FormaPagamento::from($dados['forma']),
            DataBrasileira::converter($dados['recebido_em']),
            auth()->id(),
        );

        $this->recarregar('Pagamento registrado.');
    }

    public function estornar(int $pagamentoId): void
    {
        $this->authorize('estornar', $this->mensalidade);

        $pagamento = $this->mensalidade->pagamentos()->whereKey($pagamentoId)->first();

        if ($pagamento === null || $pagamento->estaEstornado()) {
            return;
        }

        /*
         * Estorno NÃO apaga o pagamento: marca a data. Apagar dinheiro que
         * entrou e depois voltou destrói a conciliação com o extrato.
         */
        $pagamento->forceFill([
            'estornado_em' => now(),
            'motivo_estorno' => trim($this->motivo_estorno) !== '' ? trim($this->motivo_estorno) : null,
        ])->save();

        $this->mensalidade->reavaliarSituacao();

        $this->recarregar('Pagamento estornado. A mensalidade voltou a ficar em aberto.');
    }

    private function recarregar(string $mensagem): void
    {
        $this->mensalidade = $this->mensalidade->fresh([
            'aluno', 'unidade', 'matricula.plano', 'pagamentos.registradoPor',
        ]);

        $this->valor = Formato::numeroDecimal($this->mensalidade->valorEmAberto());
        $this->motivo_estorno = '';

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => $mensagem]);
    }

    public function render(): View
    {
        return view('livewire.mensalidades.detalhes', [
            'formas' => collect(FormaPagamento::cases())
                ->mapWithKeys(fn (FormaPagamento $f): array => [$f->value => $f->rotulo()])
                ->all(),
            'podeReceber' => auth()->user()->can('receber', $this->mensalidade),
            'podeEstornar' => auth()->user()->can('estornar', $this->mensalidade),
        ])->title('Mensalidade de '.$this->mensalidade->aluno->nome);
    }
}
