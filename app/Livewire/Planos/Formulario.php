<?php

declare(strict_types=1);

namespace App\Livewire\Planos;

use App\Models\Plano;
use App\Support\Formato;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro e edição de plano.
 *
 * O valor aqui vale para matrículas NOVAS. As existentes guardam o valor
 * copiado na contratação — reajustar o plano em janeiro não pode mudar
 * retroativamente o que o aluno devia em novembro.
 */
#[Layout('layouts.painel', ['secao' => 'planos'])]
final class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Plano $plano = null;

    public string $nome = '';

    public string $descricao = '';

    public string $valor_mensal = '';

    public string $taxa_matricula = '';

    public string $multa_cancelamento = '';

    public string $duracao_meses = '1';

    public string $dias_experiencia = '0';

    public string $sessoes_experiencia = '0';

    public bool $acesso_todas_unidades = false;

    public bool $ativo = true;

    public function mount(?Plano $plano = null): void
    {
        if ($plano?->exists) {
            $this->authorize('update', $plano);

            $this->plano = $plano;
            $this->preencherCom($plano);

            return;
        }

        $this->authorize('create', Plano::class);
    }

    public function editando(): bool
    {
        return $this->plano !== null;
    }

    /**
     * Valores já convertidos para o formato do banco.
     *
     * @return array<model-property<Plano>, mixed>
     */
    private function dadosNormalizados(): array
    {
        return [
            'nome' => trim($this->nome) !== '' ? trim($this->nome) : null,
            'descricao' => trim($this->descricao) !== '' ? trim($this->descricao) : null,
            // "1.234,56" vira "1234.56": mandar o texto brasileiro direto para
            // uma coluna numeric gravaria 1.23 no lugar de 1234.56.
            'valor_mensal' => Formato::decimalDoFormulario($this->valor_mensal),
            'taxa_matricula' => Formato::decimalDoFormulario($this->taxa_matricula) ?? '0',
            'multa_cancelamento' => Formato::decimalDoFormulario($this->multa_cancelamento) ?? '0',
            'duracao_meses' => (int) $this->duracao_meses,
            'dias_experiencia' => (int) $this->dias_experiencia,
            'sessoes_experiencia' => (int) $this->sessoes_experiencia,
            'acesso_todas_unidades' => $this->acesso_todas_unidades,
            'ativo' => $this->ativo,
        ];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'min:3', 'max:255',
                Rule::unique('planos', 'nome')
                    ->where('academia_id', auth()->user()->academia_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->plano?->id),
            ],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'valor_mensal' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'taxa_matricula' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'multa_cancelamento' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'duracao_meses' => ['required', 'integer', 'min:1', 'max:60'],
            // Teto de 30 dias combinado no documento de domínio.
            'dias_experiencia' => ['required', 'integer', 'min:0', 'max:30'],
            'sessoes_experiencia' => ['required', 'integer', 'min:0', 'max:60'],
            'acesso_todas_unidades' => ['boolean'],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do plano.',
            'nome.unique' => 'Já existe um plano com esse nome nesta academia.',
            'valor_mensal.required' => 'Informe o valor do plano.',
            'valor_mensal.min' => 'O valor precisa ser maior que zero.',
            'duracao_meses.min' => 'A duração mínima é de um mês.',
            'dias_experiencia.max' => 'A experiência tem teto de 30 dias.',
        ];
    }

    public function salvar(): void
    {
        /*
         * LIMPAR O SACO DE ERROS ANTES DE VALIDAR DE NOVO.
         *
         * A validação aqui é feita com `Validator::make(...)`, e não com
         * `$this->validate()`. A diferença é silenciosa e cara: o Livewire só
         * TROCA o saco de erros quando uma ValidationException é lançada.
         * Passando a validação, as críticas da tentativa anterior continuam
         * lá — a pessoa corrige o campo e a mensagem não some.
         */
        $this->resetValidation();

        $dados = $this->dadosNormalizados();

        Validator::make($dados, $this->rules(), $this->messages())->validate();

        if ($this->editando()) {
            $this->plano->update($dados);
            $mensagem = 'Plano atualizado. O novo valor vale para matrículas novas.';
            $plano = $this->plano;
        } else {
            $plano = Plano::create($dados);
            $mensagem = 'Plano cadastrado.';
        }

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => $mensagem]);

        $this->redirectRoute('planos.detalhes', $plano, navigate: true);
    }

    private function preencherCom(Plano $plano): void
    {
        $this->nome = (string) $plano->nome;
        $this->descricao = (string) $plano->descricao;
        $this->valor_mensal = Formato::numeroDecimal($plano->valor_mensal);
        $this->taxa_matricula = Formato::numeroDecimal($plano->taxa_matricula);
        $this->multa_cancelamento = Formato::numeroDecimal($plano->multa_cancelamento);
        $this->duracao_meses = (string) $plano->duracao_meses;
        $this->dias_experiencia = (string) $plano->dias_experiencia;
        $this->sessoes_experiencia = (string) $plano->sessoes_experiencia;
        $this->acesso_todas_unidades = (bool) $plano->acesso_todas_unidades;
        $this->ativo = (bool) $plano->ativo;
    }

    public function render(): View
    {
        return view('livewire.planos.formulario', [
            // O campo de acesso entre filiais só faz sentido em rede.
            'temFiliais' => auth()->user()->academia->unidades()->where('ativa', true)->count() > 1,
        ]);
    }
}
