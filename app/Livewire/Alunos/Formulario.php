<?php

declare(strict_types=1);

namespace App\Livewire\Alunos;

use App\Models\Aluno;
use App\Rules\CpfValido;
use App\Rules\DataBrasileira;
use App\Services\Enderecos\Ibge;
use App\Services\Enderecos\ViaCep;
use App\Support\Documentos;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro e edição de aluno. A mesma tela para os dois — mesmo layout, mesma
 * ordem de campos, mesmas regras.
 *
 * CPF, data de nascimento e WhatsApp são obrigatórios por decisão de produto:
 * são o que sustentam a lista de aniversariantes, o controle de menor de idade
 * e o contrato.
 */
#[Layout('layouts.painel', ['secao' => 'alunos'])]
final class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Aluno $aluno = null;

    // Dados pessoais
    public string $nome = '';

    public string $cpf = '';

    public string $data_nascimento = '';

    public string $sexo = '';

    public string $whatsapp = '';

    public string $telefone = '';

    public string $email = '';

    // Endereço
    public string $cep = '';

    public string $logradouro = '';

    public string $numero = '';

    public string $complemento = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = '';

    // Responsável — obrigatório para menor de 18
    public string $responsavel_nome = '';

    public string $responsavel_cpf = '';

    public string $responsavel_telefone = '';

    public string $responsavel_parentesco = '';

    public string $observacoes = '';

    /** Aviso do ViaCEP quando o endereço não pôde ser preenchido sozinho. */
    public string $avisoCep = '';

    public function mount(?Aluno $aluno = null): void
    {
        if ($aluno?->exists) {
            $this->authorize('update', $aluno);

            $this->aluno = $aluno;
            $this->preencherCom($aluno);

            return;
        }

        $this->authorize('create', Aluno::class);
    }

    public function editando(): bool
    {
        return $this->aluno !== null;
    }

    // -----------------------------------------------------------------
    // Regras
    // -----------------------------------------------------------------

    /**
     * Os valores como vão para o banco: sem máscara.
     *
     * A validação acontece sobre ESTES dados, não sobre o que está na tela.
     * Enquanto validava o texto com máscara, a regra de unicidade procurava
     * "529.982.247-25" numa coluna que guarda "52998224725" — nunca achava o
     * duplicado, e o erro só aparecia como falha de banco na cara do usuário.
     *
     * As chaves são anotadas como colunas de Aluno: assim a análise estática
     * confere cada nome contra o schema e denuncia um "responsavel_nome"
     * digitado errado antes de virar coluna fantasma.
     *
     * @return array<model-property<Aluno>, string|null>
     */
    private function dadosNormalizados(): array
    {
        $digitos = static fn (string $valor): ?string => Documentos::apenasDigitos($valor) ?: null;
        $texto = static fn (string $valor): ?string => trim($valor) !== '' ? trim($valor) : null;

        return [
            'nome' => $texto($this->nome),
            'cpf' => $digitos($this->cpf),
            'data_nascimento' => $texto($this->data_nascimento),
            'sexo' => $texto($this->sexo),
            'whatsapp' => $digitos($this->whatsapp),
            'telefone' => $digitos($this->telefone),
            'email' => $texto($this->email),

            'cep' => $digitos($this->cep),
            'logradouro' => $texto($this->logradouro),
            'numero' => $texto($this->numero),
            'complemento' => $texto($this->complemento),
            'bairro' => $texto($this->bairro),
            'cidade' => $texto($this->cidade),
            'uf' => $texto($this->uf),

            'responsavel_nome' => $texto($this->responsavel_nome),
            'responsavel_cpf' => $digitos($this->responsavel_cpf),
            'responsavel_telefone' => $digitos($this->responsavel_telefone),
            'responsavel_parentesco' => $texto($this->responsavel_parentesco),

            'observacoes' => $texto($this->observacoes),
        ];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $academia = auth()->user()->academia;

        return [
            'nome' => ['required', 'string', 'min:3', 'max:255'],

            'cpf' => [
                'required',
                'digits:11',
                new CpfValido,
                /*
                 * Único por academia, ignorando os excluídos e o próprio
                 * registro na edição. O índice do banco garante o mesmo — isto
                 * existe para a pessoa ver a mensagem no campo em vez de um
                 * erro de banco na tela.
                 */
                Rule::unique('alunos', 'cpf')
                    ->where('academia_id', $academia?->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->aluno?->id),
            ],

            'data_nascimento' => ['required', new DataBrasileira],

            'sexo' => ['nullable', Rule::in(['M', 'F'])],

            // Dígitos, não caracteres: 10 para fixo, 11 para celular.
            'whatsapp' => ['required', 'digits_between:10,11'],
            'telefone' => ['nullable', 'digits_between:10,11'],

            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('alunos', 'email')
                    ->where('academia_id', $academia?->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->aluno?->id),
            ],

            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],

            // Obrigatórios só quando o aluno é menor — ver validacaoExtra().
            'responsavel_nome' => ['nullable', 'string', 'max:255'],
            'responsavel_cpf' => ['nullable', 'digits:11', new CpfValido],
            'responsavel_telefone' => ['nullable', 'digits_between:10,11'],
            'responsavel_parentesco' => ['nullable', 'string', 'max:40'],

            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do aluno.',
            'nome.min' => 'O nome parece curto demais. Confira.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.unique' => 'Já existe um aluno com esse CPF nesta academia.',
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'cpf.digits' => 'O CPF precisa ter 11 dígitos.',
            'whatsapp.required' => 'O WhatsApp é obrigatório: é por ele que a cobrança e os avisos saem.',
            'whatsapp.digits_between' => 'Informe o WhatsApp com DDD.',
            'telefone.digits_between' => 'Informe o telefone com DDD.',
            'cep.digits' => 'O CEP precisa ter 8 dígitos.',
            'email.unique' => 'Já existe um aluno com esse e-mail nesta academia.',
        ];
    }

    // -----------------------------------------------------------------
    // Endereço pelo CEP
    // -----------------------------------------------------------------

    /**
     * Preenche o endereço quando o CEP fica completo.
     *
     * **Não trava o cadastro**: CEP não encontrado ou serviço fora do ar
     * apenas avisa, e a recepção digita à mão. Aluno em rua nova não pode
     * ficar sem cadastro porque uma API pública caiu.
     */
    public function updatedCep(): void
    {
        $this->avisoCep = '';

        if (strlen(Documentos::apenasDigitos($this->cep)) !== 8) {
            return;
        }

        $endereco = app(ViaCep::class)->buscar($this->cep);

        if ($endereco === null) {
            $this->avisoCep = 'CEP não encontrado. Preencha o endereço à mão.';

            return;
        }

        $this->logradouro = $endereco['logradouro'] ?: $this->logradouro;
        $this->bairro = $endereco['bairro'] ?: $this->bairro;
        $this->cidade = $endereco['cidade'] ?: $this->cidade;
        $this->uf = $endereco['uf'] ?: $this->uf;
    }

    /** Trocar de estado invalida a cidade escolhida. */
    public function updatedUf(): void
    {
        $this->cidade = '';
    }

    // -----------------------------------------------------------------
    // Gravação
    // -----------------------------------------------------------------

    public function salvar(): void
    {
        /*
         * Valida os dados JÁ NORMALIZADOS, e não o texto com máscara da tela.
         * A ValidationException que sai daqui é capturada pelo Livewire e
         * preenche o mesmo saco de erros de sempre, então os campos continuam
         * mostrando a mensagem no lugar certo.
         */
        $dados = $this->dadosNormalizados();

        Validator::make($dados, $this->rules(), $this->messages())->validate();

        /*
         * Faixa etária e responsável são conferidos depois do `validate()`:
         * dependem da data já convertida e da configuração da academia, o que
         * não cabe numa regra de campo isolada.
         *
         * `addError` marca o campo; a gravação abaixo só acontece se nada foi
         * marcado.
         */
        $nascimento = DataBrasileira::converter($this->data_nascimento);
        $this->validarIdade($nascimento);
        $this->validarResponsavel($nascimento);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Os campos em branco já viraram nulo em dadosNormalizados(): string
        // vazia ocuparia o índice único de e-mail e o segundo cadastro em
        // branco colidiria.
        $dados['data_nascimento'] = $nascimento?->toDateString();

        if ($this->editando()) {
            $this->aluno->update($dados);
            $mensagem = 'Cadastro atualizado.';
            $aluno = $this->aluno;
        } else {
            $aluno = Aluno::create($dados);
            $mensagem = 'Aluno cadastrado.';
        }

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => $mensagem]);

        $this->redirectRoute('alunos.detalhes', $aluno, navigate: true);
    }

    /**
     * Faixa etária: entre a mínima da academia e 99 anos.
     *
     * O teto pega o erro de digitação mais comum do balcão — trocar o ano e
     * cadastrar alguém nascido em 1899.
     */
    private function validarIdade(?CarbonImmutable $nascimento): void
    {
        if ($nascimento === null) {
            return;
        }

        $hoje = CarbonImmutable::now()->startOfDay();

        if ($nascimento->greaterThan($hoje)) {
            $this->addError('data_nascimento', 'A data de nascimento não pode ser no futuro.');

            return;
        }

        $idade = (int) $nascimento->diffInYears($hoje);
        // A política já barrou o super administrador, então há academia aqui.
        $minima = (int) auth()->user()->academia->idade_minima;

        if ($idade < $minima) {
            $this->addError('data_nascimento', "Esta academia aceita alunos a partir de {$minima} anos.");
        }

        if ($idade > 99) {
            $this->addError('data_nascimento', 'Confira o ano de nascimento.');
        }
    }

    /** Abaixo de 18, os dados do responsável passam a ser obrigatórios. */
    private function validarResponsavel(?CarbonImmutable $nascimento): void
    {
        if ($nascimento === null || ! $this->ehMenor($nascimento)) {
            return;
        }

        $obrigatorios = [
            'responsavel_nome' => 'Informe o nome do responsável.',
            'responsavel_cpf' => 'Informe o CPF do responsável.',
            'responsavel_telefone' => 'Informe o telefone do responsável.',
            'responsavel_parentesco' => 'Informe o parentesco do responsável.',
        ];

        foreach ($obrigatorios as $campo => $mensagem) {
            if (trim($this->{$campo}) === '') {
                $this->addError($campo, $mensagem);
            }
        }
    }

    public function ehMenor(?CarbonImmutable $nascimento = null): bool
    {
        $nascimento ??= DataBrasileira::converter($this->data_nascimento);

        if ($nascimento === null) {
            return false;
        }

        return $nascimento->diffInYears(CarbonImmutable::now()) < 18;
    }

    // -----------------------------------------------------------------

    private function preencherCom(Aluno $aluno): void
    {
        $this->nome = (string) $aluno->nome;
        $this->cpf = Documentos::formatarCpf($aluno->cpf);
        $this->data_nascimento = $aluno->data_nascimento->format('d/m/Y');
        $this->sexo = (string) $aluno->sexo;
        $this->whatsapp = Formato::telefone((string) $aluno->whatsapp);
        $this->telefone = $aluno->telefone ? Formato::telefone($aluno->telefone) : '';
        $this->email = (string) $aluno->email;

        $this->cep = $aluno->cep ? substr((string) $aluno->cep, 0, 5).'-'.substr((string) $aluno->cep, 5) : '';
        $this->logradouro = (string) $aluno->logradouro;
        $this->numero = (string) $aluno->numero;
        $this->complemento = (string) $aluno->complemento;
        $this->bairro = (string) $aluno->bairro;
        $this->cidade = (string) $aluno->cidade;
        $this->uf = (string) $aluno->uf;

        $this->responsavel_nome = (string) $aluno->responsavel_nome;
        $this->responsavel_cpf = $aluno->responsavel_cpf ? Documentos::formatarCpf($aluno->responsavel_cpf) : '';
        $this->responsavel_telefone = $aluno->responsavel_telefone
            ? Formato::telefone($aluno->responsavel_telefone)
            : '';
        $this->responsavel_parentesco = (string) $aluno->responsavel_parentesco;

        $this->observacoes = (string) $aluno->observacoes;
    }

    public function render(): View
    {
        $ibge = app(Ibge::class);

        return view('livewire.alunos.formulario', [
            'estados' => $ibge->estados(),
            'municipios' => $this->uf !== '' ? $ibge->municipios($this->uf) : [],
            'menorDeIdade' => $this->ehMenor(),
        ]);
    }
}
