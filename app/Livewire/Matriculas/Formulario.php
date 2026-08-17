<?php

declare(strict_types=1);

namespace App\Livewire\Matriculas;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoMatricula;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Rules\DataBrasileira;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Nova matrícula.
 *
 * As três regras que o documento de domínio fixou e que esta tela faz valer:
 *
 *   1. Experiência não exige contrato; matrícula regular exige.
 *   2. Dia de vencimento entre 1 e 28 — dia 31 não existe em fevereiro.
 *   3. O aluno não pode ter duas matrículas sobrepostas na mesma unidade.
 *      O banco recusa com uma constraint EXCLUDE; aqui a mensagem é traduzida
 *      para quem está no balcão.
 */
#[Layout('layouts.painel', ['secao' => 'matriculas'])]
final class Formulario extends Component
{
    use AuthorizesRequests;

    public ?int $aluno_id = null;

    public ?int $plano_id = null;

    public ?int $unidade_id = null;

    public string $tipo = TipoMatricula::Regular->value;

    public string $inicio_em = '';

    public string $contrato_assinado_em = '';

    public string $dia_vencimento = '5';

    public string $valor_mensal = '';

    public string $observacoes = '';

    public function mount(?Aluno $aluno = null): void
    {
        $this->authorize('create', Matricula::class);

        $hoje = CarbonImmutable::now();

        $this->inicio_em = $hoje->format('d/m/Y');
        $this->contrato_assinado_em = $hoje->format('d/m/Y');
        // Sugere o dia do início, respeitando o teto de 28.
        $this->dia_vencimento = (string) min($hoje->day, 28);
        $this->unidade_id = auth()->user()->unidadeAtual()?->id;

        // Vindo da ficha do aluno, já chega com ele escolhido.
        if ($aluno?->exists) {
            $this->aluno_id = $aluno->id;
        }
    }

    /**
     * Trocar de plano puxa o valor e ajusta o tipo.
     *
     * O valor fica editável: desconto negociado no balcão é rotina, e o que
     * vale para a cobrança é o valor da matrícula, não o do plano.
     */
    public function updatedPlanoId(): void
    {
        $plano = $this->planoEscolhido();

        if ($plano === null) {
            return;
        }

        $this->valor_mensal = Formato::numeroDecimal($plano->valor_mensal);

        if (! $plano->temExperiencia() && $this->tipo === TipoMatricula::Experiencia->value) {
            $this->tipo = TipoMatricula::Regular->value;
        }
    }

    private function planoEscolhido(): ?Plano
    {
        return $this->plano_id === null ? null : Plano::find($this->plano_id);
    }

    public function ehExperiencia(): bool
    {
        return $this->tipo === TipoMatricula::Experiencia->value;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();

        return [
            'aluno_id' => ['required', Rule::exists('alunos', 'id')->whereNull('deleted_at')],
            'plano_id' => ['required', Rule::exists('planos', 'id')->where('ativo', true)->whereNull('deleted_at')],
            'unidade_id' => [
                'required',
                // Não basta existir: precisa ser uma unidade que este usuário
                // opera. Sem isso, bastaria trocar o número no formulário.
                Rule::in($usuario->unidadesAcessiveis()->pluck('id')->all()),
            ],
            'tipo' => ['required', Rule::in(array_column(TipoMatricula::cases(), 'value'))],
            'inicio_em' => ['required', new DataBrasileira],
            'contrato_assinado_em' => [
                Rule::requiredIf(fn (): bool => ! $this->ehExperiencia()),
                'nullable',
                new DataBrasileira,
            ],
            'dia_vencimento' => ['required', 'integer', 'min:1', 'max:28'],
            'valor_mensal' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'aluno_id.required' => 'Escolha o aluno.',
            'plano_id.required' => 'Escolha o plano.',
            'unidade_id.required' => 'Escolha a unidade.',
            'unidade_id.in' => 'Você não opera nessa unidade.',
            'contrato_assinado_em.required' => 'Matrícula regular só existe com contrato assinado.',
            'dia_vencimento.max' => 'O dia de vencimento vai até 28: dia 31 não existe em fevereiro.',
            'valor_mensal.min' => 'O valor precisa ser maior que zero.',
        ];
    }

    public function salvar(): void
    {
        $plano = $this->planoEscolhido();

        $dados = [
            'aluno_id' => $this->aluno_id,
            'plano_id' => $this->plano_id,
            'unidade_id' => $this->unidade_id,
            'tipo' => $this->tipo,
            'inicio_em' => $this->inicio_em,
            'contrato_assinado_em' => $this->ehExperiencia() ? null : $this->contrato_assinado_em,
            'dia_vencimento' => (int) $this->dia_vencimento,
            'valor_mensal' => Formato::decimalDoFormulario($this->valor_mensal),
            'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
        ];

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

        Validator::make($dados, $this->rules(), $this->messages())->validate();

        $inicio = DataBrasileira::converter($this->inicio_em);

        $dados['inicio_em'] = $inicio?->toDateString();
        $dados['contrato_assinado_em'] = $this->ehExperiencia()
            ? null
            : DataBrasileira::converter($this->contrato_assinado_em)?->toDateString();

        $dados['situacao'] = $this->ehExperiencia()
            ? SituacaoMatricula::Experiencia->value
            : SituacaoMatricula::Ativa->value;

        $dados['fim_previsto_em'] = $this->calcularFimPrevisto($inicio, $plano);

        try {
            /*
             * Envolvido em transação de propósito. No PostgreSQL, um comando
             * que falha aborta o bloco inteiro: sem o savepoint que o
             * `transaction()` cria, qualquer consulta seguinte — inclusive a
             * que re-renderiza a tela — morreria com "transação atual foi
             * interrompida". A tentativa que falha fica contida.
             */
            $matricula = DB::transaction(fn (): Matricula => Matricula::create($dados));
        } catch (QueryException $erro) {
            /*
             * A constraint EXCLUDE é a barreira de verdade — vale para
             * importação e console, não só para esta tela. Aqui só traduzimos
             * o erro do banco para quem está atendendo no balcão.
             */
            if (str_contains($erro->getMessage(), 'matriculas_sem_sobreposicao')) {
                $this->addError('aluno_id', 'Este aluno já tem matrícula em vigor nesta unidade no período informado.');

                return;
            }

            throw $erro;
        }

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Matrícula criada.']);

        $this->redirectRoute('matriculas.detalhes', $matricula, navigate: true);
    }

    /**
     * Fim previsto.
     *
     * Na experiência, é o último dia do teste. No plano mensal não há fim: a
     * matrícula corre até alguém encerrar. Só o plano com prazo tem data.
     */
    private function calcularFimPrevisto(?CarbonImmutable $inicio, ?Plano $plano): ?string
    {
        if ($inicio === null || $plano === null) {
            return null;
        }

        if ($this->ehExperiencia()) {
            return $plano->dias_experiencia > 0
                ? $inicio->addDays($plano->dias_experiencia)->toDateString()
                : null;
        }

        return $plano->duracao_meses > 1
            ? $inicio->addMonths($plano->duracao_meses)->toDateString()
            : null;
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $plano = $this->planoEscolhido();

        return view('livewire.matriculas.formulario', [
            'alunos' => Aluno::query()->orderBy('nome')->pluck('nome', 'id'),
            'planos' => Plano::query()->where('ativo', true)->orderBy('nome')->get(),
            'unidades' => $usuario->unidadesAcessiveis()->pluck('nome', 'id'),
            'planoTemExperiencia' => $plano?->temExperiencia() ?? false,
            'plano' => $plano,
        ]);
    }
}
