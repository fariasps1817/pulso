<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\PadroesDeAcesso;
use App\Support\Academia\Papeis;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro e edição de usuário da academia.
 *
 * A SENHA NÃO É DIGITADA POR NINGUÉM
 *
 * O sistema gera uma temporária, mostra UMA vez para o gestor repassar, e
 * exige a troca no primeiro acesso. Duas razões: não depende de e-mail
 * configurado, e o gestor nunca fica sabendo a senha definitiva de ninguém —
 * o que importa porque a conta dele assina recebimento de dinheiro.
 *
 * Senha escolhida por terceiro vira "academia123" para a equipe inteira. Esta
 * é uma daquelas decisões em que o caminho mais confortável é o pior.
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
final class Formulario extends Component
{
    use AuthorizesRequests;

    public ?User $usuario = null;

    public string $name = '';

    public string $email = '';

    public string $papel = 'recepcao';

    public ?int $unidade_padrao_id = null;

    /** @var list<int> */
    public array $unidades = [];

    public bool $acessa_todas_unidades = false;

    public bool $pode_alternar_unidade = false;

    public bool $sessao_unica = true;

    public bool $ativo = true;

    /** Mostrada uma única vez, logo após o cadastro. */
    public ?string $senhaTemporaria = null;

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->authorize('update', $usuario);

            $this->usuario = $usuario;
            $this->preencherCom($usuario);

            return;
        }

        $this->authorize('create', User::class);

        $this->aplicarPadraoDoPapel();
    }

    public function editando(): bool
    {
        return $this->usuario !== null;
    }

    /**
     * Trocar o papel repõe os padrões de acesso.
     *
     * Sem isso, promover uma recepcionista a gerente a deixaria travada numa
     * unidade só — e o gestor nunca ia entender por quê.
     */
    public function updatedPapel(): void
    {
        $this->aplicarPadraoDoPapel();
    }

    private function aplicarPadraoDoPapel(): void
    {
        $padrao = PadroesDeAcesso::paraPapel($this->papel);

        $this->acessa_todas_unidades = $padrao['acessa_todas_unidades'];
        $this->pode_alternar_unidade = $padrao['pode_alternar_unidade'];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            /*
             * Unico no sistema INTEIRO, embora o banco permita o mesmo e-mail
             * em academias diferentes (indice `(academia_id, email)`). A
             * aplicacao e mais restrita de proposito: o login recebe so o
             * e-mail e nao tem como perguntar de qual academia se trata.
             * Afrouxar isto exige antes a escolha de academia no login.
             */
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->usuario?->id),
            ],
            'papel' => ['required', Rule::in(Papeis::atribuiveisPor(auth()->user()))],
            'unidade_padrao_id' => ['required', Rule::in($this->unidadesDaAcademia()->pluck('id'))],
            'unidades' => ['array'],
            'unidades.*' => [Rule::in($this->unidadesDaAcademia()->pluck('id'))],
            'acessa_todas_unidades' => ['boolean'],
            'pode_alternar_unidade' => ['boolean'],
            'sessao_unica' => ['boolean'],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da pessoa.',
            'email.required' => 'Informe o e-mail — é com ele que se entra no sistema.',
            'email.unique' => 'Este e-mail já entra no Pulso. Use outro — enquanto o login não pergunta a academia, cada e-mail responde por uma conta só.',
            'papel.in' => 'Você não pode atribuir este papel.',
            'unidade_padrao_id.required' => 'Escolha a unidade onde a pessoa trabalha.',
        ];
    }

    public function salvar(): void
    {
        $dados = $this->dadosNormalizados();

        // `papel` e `unidades` são validados, mas não são colunas do usuário:
        // o papel vive no pacote de permissões e as unidades, numa tabela de
        // vínculo.
        Validator::make(
            $dados + ['papel' => $this->papel, 'unidades' => $this->unidades],
            $this->rules(),
            $this->messages(),
        )->validate();

        $vinculos = $this->vinculosDeUnidade();

        if ($this->editando()) {
            $this->atualizar($dados, $vinculos);

            return;
        }

        $this->criar($dados, $vinculos);
    }

    /**
     * @param  array<model-property<User>, mixed>  $dados
     * @param  list<int>  $vinculos
     */
    private function criar(array $dados, array $vinculos): void
    {
        $senha = $this->gerarSenha();

        $usuario = DB::transaction(function () use ($dados, $vinculos, $senha): User {
            $usuario = User::create([
                ...$dados,
                'academia_id' => auth()->user()->academia_id,
                'password' => $senha,
                // A janela em que duas pessoas conhecem a senha fecha aqui.
                'deve_trocar_senha' => true,
            ]);

            $usuario->assignRole($this->papel);
            $usuario->unidades()->sync($vinculos);

            return $usuario;
        });

        $this->usuario = $usuario;

        // Fica na tela, e não numa mensagem de sucesso que some ao navegar:
        // o gestor precisa copiar isto antes de sair.
        $this->senhaTemporaria = $senha;
    }

    /**
     * @param  array<model-property<User>, mixed>  $dados
     * @param  list<int>  $vinculos
     */
    private function atualizar(array $dados, array $vinculos): void
    {
        DB::transaction(function () use ($dados, $vinculos): void {
            $this->usuario->update($dados);
            $this->usuario->syncRoles([$this->papel]);
            $this->usuario->unidades()->sync($vinculos);
        });

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Usuário atualizado.']);

        $this->redirectRoute('usuarios.lista', navigate: true);
    }

    /**
     * Quem enxerga a rede inteira não precisa de vínculo por unidade — e
     * gravar os dois faria a lista de vínculos mentir depois que uma filial
     * nova fosse criada.
     *
     * @return list<int>
     */
    private function vinculosDeUnidade(): array
    {
        if ($this->acessa_todas_unidades) {
            return [];
        }

        // A unidade padrão é sempre um vínculo: sem ela, a pessoa entraria
        // numa unidade a que não tem acesso.
        return array_values(array_unique([...$this->unidades, $this->unidade_padrao_id]));
    }

    /** @return array<model-property<User>, mixed> */
    private function dadosNormalizados(): array
    {
        return [
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
            'unidade_padrao_id' => $this->unidade_padrao_id,
            'acessa_todas_unidades' => $this->acessa_todas_unidades,
            'pode_alternar_unidade' => $this->pode_alternar_unidade,
            'sessao_unica' => $this->sessao_unica,
            'ativo' => $this->ativo,
        ];
    }

    /**
     * Senha temporária legível ao telefone.
     *
     * Sem caracteres que se confundem em voz ou na tela — o gestor vai ditar
     * isto para alguém, e "1" contra "l" vira chamado de suporte.
     */
    private function gerarSenha(): string
    {
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return collect(range(1, 12))
            ->map(fn (): string => $alfabeto[random_int(0, strlen($alfabeto) - 1)])
            ->implode('');
    }

    /** @return Collection<int, Unidade> */
    private function unidadesDaAcademia()
    {
        return Unidade::query()
            ->where('academia_id', auth()->user()->academia_id)
            ->where('ativa', true)
            ->orderBy('id')
            ->get();
    }

    private function preencherCom(User $usuario): void
    {
        $this->name = (string) $usuario->name;
        $this->email = (string) $usuario->email;
        $this->papel = (string) $usuario->getRoleNames()->first();
        $this->unidade_padrao_id = $usuario->unidade_padrao_id;
        $this->unidades = $usuario->unidades()->pluck('unidades.id')->all();
        $this->acessa_todas_unidades = (bool) $usuario->acessa_todas_unidades;
        $this->pode_alternar_unidade = (bool) $usuario->pode_alternar_unidade;
        $this->sessao_unica = (bool) $usuario->sessao_unica;
        $this->ativo = (bool) $usuario->ativo;
    }

    public function render(): View
    {
        $unidades = $this->unidadesDaAcademia();

        return view('livewire.usuarios.formulario', [
            /*
             * NAO se chama `unidades`: o componente ja tem uma propriedade
             * publica com esse nome (os vinculos marcados), e ela venceria os
             * dados do render — a view receberia um array de ids onde espera
             * uma colecao de unidades.
             */
            'listaDeUnidades' => $unidades,
            'temFiliais' => $unidades->count() > 1,
            'papeisDisponiveis' => collect(Papeis::atribuiveisPor(auth()->user()))
                ->mapWithKeys(fn (string $papel): array => [$papel => Papeis::rotulo($papel)])
                ->all(),
        ])->title($this->editando() ? 'Editar usuário' : 'Novo usuário');
    }
}
