<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use App\Models\Unidade;
use App\Models\User;
use App\Rules\DataBrasileira;
use App\Services\Acesso\SenhaTemporaria;
use App\Services\Enderecos\Ibge;
use App\Support\Academia\ContextoAcademia;
use App\Support\Academia\PadroesDeAcesso;
use App\Support\Documentos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Uma academia nasce com três coisas, ou não nasce.
 *
 * ACADEMIA + PRIMEIRA UNIDADE + PRIMEIRO DONO, na mesma transação.
 *
 * Criar só a academia produziria um cliente inacessível: sem unidade não há
 * onde matricular ninguém, e sem usuário não há quem entre. O super
 * administrador não pode suprir a falta depois, porque ele não enxerga o
 * interior de academia nenhuma — teria que mexer no banco.
 *
 * A senha do dono aparece UMA vez, para a equipe do Pulso repassar na entrega.
 * O primeiro acesso dele exige a troca.
 */
#[Layout('layouts.administracao', ['secao' => 'academias'])]
#[Title('Nova academia')]
final class NovaAcademia extends Component
{
    public string $nome = '';

    public string $razao_social = '';

    public string $cnpj = '';

    public string $email = '';

    public string $whatsapp = '';

    public string $cidade = '';

    public string $uf = '';

    public string $unidade_nome = 'Matriz';

    public string $dono_nome = '';

    public string $dono_email = '';

    public ?string $assinatura_vence_em = null;

    /** Mostrada uma única vez, depois de criar. */
    public ?string $senhaTemporaria = null;

    public ?Academia $criada = null;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'nullable', 'string', 'size:14', 'unique:academias,cnpj',
                // Dígito verificador conferido: CNPJ digitado errado só
                // aparece meses depois, na primeira nota fiscal.
                fn (string $campo, mixed $valor, callable $falhar) => Documentos::cnpjValido((string) $valor)
                    ? null
                    : $falhar('O CNPJ informado não é válido.'),
            ],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'min:10', 'max:11'],
            'cidade' => ['required', 'string', 'max:120'],
            'uf' => ['required', 'string', 'size:2'],
            'unidade_nome' => ['required', 'string', 'max:120'],
            'dono_nome' => ['required', 'string', 'min:3', 'max:255'],
            'dono_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Validada sobre o TEXTO da tela, não sobre a conversão — ver
            // a nota em `salvar()`.
            'assinatura_vence_em' => ['nullable', new DataBrasileira],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da academia.',
            'email.required' => 'Informe o e-mail da academia.',
            'cidade.required' => 'Informe a cidade.',
            'unidade_nome.required' => 'A academia precisa de ao menos uma unidade.',
            'dono_nome.required' => 'Informe quem vai receber o acesso de dono.',
            'dono_email.required' => 'Informe o e-mail de quem vai entrar no sistema.',
            'dono_email.unique' => 'Este e-mail já entra no Pulso. Use outro — enquanto o login não pergunta a academia, cada e-mail responde por uma conta só.',
            'cnpj.unique' => 'Já existe uma academia cadastrada com este CNPJ.',
        ];
    }

    public function salvar(ContextoAcademia $contexto): void
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

        /*
         * A data é validada com o que a PESSOA digitou, e não com o que
         * `dadosNormalizados()` devolveu.
         *
         * O defeito era exatamente esse: a normalização já convertia
         * "31/12/2027" em "2027-12-31", e a regra brasileira então reprovava
         * a própria conversão — a tela dizia "informe uma data no formato
         * dd/mm/aaaa" por mais correta que a data estivesse.
         */
        Validator::make(
            [...$dados, 'assinatura_vence_em' => $this->assinatura_vence_em],
            $this->rules(),
            $this->messages(),
        )->validate();

        $senha = SenhaTemporaria::gerar();

        $academia = DB::transaction(function () use ($dados, $senha, $contexto): Academia {
            $academia = Academia::create([
                'nome' => $dados['nome'],
                'razao_social' => $dados['razao_social'],
                'cnpj' => $dados['cnpj'],
                'email' => $dados['email'],
                'whatsapp' => $dados['whatsapp'],
                'cidade' => $dados['cidade'],
                'uf' => $dados['uf'],
                'situacao' => SituacaoAcademia::Ativa,
                'assinatura_vence_em' => $dados['assinatura_vence_em'],
            ]);

            /*
             * A unidade e o papel só podem ser criados DENTRO do contexto da
             * academia nova: `unidades` carrega `academia_id` e o pacote de
             * permissões precisa saber a qual academia o papel pertence —
             * quem é dono aqui não é dono na vizinha.
             */
            return $contexto->paraAcademia($academia->id, function () use ($academia, $dados, $senha): Academia {
                setPermissionsTeamId($academia->id);

                $unidade = Unidade::create([
                    'academia_id' => $academia->id,
                    'nome' => $dados['unidade_nome'],
                    'cidade' => $dados['cidade'],
                    'uf' => $dados['uf'],
                    'ativa' => true,
                ]);

                $dono = User::create([
                    'academia_id' => $academia->id,
                    'name' => $dados['dono_nome'],
                    'email' => $dados['dono_email'],
                    'password' => $senha,
                    'unidade_padrao_id' => $unidade->id,
                    ...PadroesDeAcesso::paraPapel('dono'),
                    'ativo' => true,
                    'deve_trocar_senha' => true,
                ]);

                $dono->assignRole('dono');

                return $academia;
            });
        });

        $this->criada = $academia;
        $this->senhaTemporaria = $senha;
    }

    /** @return array<string, mixed> */
    private function dadosNormalizados(): array
    {
        $digitos = static fn (string $valor): ?string => ($limpo = preg_replace('/\D/', '', $valor)) !== ''
            ? $limpo
            : null;

        return [
            'nome' => trim($this->nome),
            'razao_social' => trim($this->razao_social) !== '' ? trim($this->razao_social) : null,
            'cnpj' => $digitos($this->cnpj),
            'email' => mb_strtolower(trim($this->email)),
            'whatsapp' => $digitos($this->whatsapp),
            'cidade' => trim($this->cidade),
            'uf' => mb_strtoupper(trim($this->uf)),
            'unidade_nome' => trim($this->unidade_nome),
            'dono_nome' => trim($this->dono_nome),
            'dono_email' => mb_strtolower(trim($this->dono_email)),
            /*
             * O campo tem mascara brasileira: `Carbon::parse` leria
             * "16/02/2027" como mes 16 e falharia, ou pior, como m/d/Y.
             */
            'assinatura_vence_em' => $this->assinatura_vence_em !== null && $this->assinatura_vence_em !== ''
                ? DataBrasileira::converter($this->assinatura_vence_em)?->toDateString()
                : null,
        ];
    }

    public function render(): View
    {
        $ibge = app(Ibge::class);

        return view('livewire.administracao.nova-academia', [
            'estados' => $ibge->estados(),
            /*
             * A lista de municípios só é buscada com o estado escolhido — são
             * 5.570 no país, e trazer todos para filtrar na tela seria carga
             * inútil em toda abertura do formulário.
             */
            'municipios' => $this->uf !== '' ? $ibge->municipios($this->uf) : [],
        ]);
    }
}
