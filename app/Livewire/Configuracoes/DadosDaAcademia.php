<?php

declare(strict_types=1);

namespace App\Livewire\Configuracoes;

use App\Models\Academia;
use App\Services\Enderecos\ViaCep;
use App\Support\Documentos;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Os dados que saem impressos.
 *
 * Esta tela não é cadastro burocrático: é o cabeçalho do recibo que o aluno
 * leva para casa e do contrato que ele assina. Razão social e CNPJ errados
 * aqui viram documento sem valor, e ninguém percebe até alguém precisar dele.
 *
 * A logo é da ACADEMIA, para os documentos dela. Não substitui a marca Pulso
 * na interface — o sistema continua sendo o Pulso.
 */
#[Layout('layouts.painel', ['secao' => 'configuracoes'])]
#[Title('Dados da academia')]
final class DadosDaAcademia extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Academia $academia;

    public string $nome = '';

    public string $razao_social = '';

    public string $cnpj = '';

    public string $email = '';

    public string $telefone = '';

    public string $whatsapp = '';

    public string $cep = '';

    public string $logradouro = '';

    public string $numero = '';

    public string $complemento = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = '';

    public mixed $logo = null;

    public ?string $avisoDeCep = null;

    public function mount(): void
    {
        $this->academia = auth()->user()->academia;

        $this->authorize('configurar', $this->academia);

        $this->preencher();
    }

    /**
     * O CEP preenche o endereço, mas nunca trava o cadastro.
     *
     * Se o ViaCEP estiver fora do ar, a tela apenas avisa e o gestor digita à
     * mão — a mesma regra do cadastro de aluno.
     */
    public function updatedCep(): void
    {
        $this->avisoDeCep = null;

        if (strlen(Documentos::apenasDigitos($this->cep)) !== 8) {
            return;
        }

        $endereco = app(ViaCep::class)->buscar($this->cep);

        if ($endereco === null) {
            $this->avisoDeCep = 'Não foi possível consultar o CEP agora. Preencha o endereço à mão.';

            return;
        }

        $this->logradouro = $endereco['logradouro'] ?: $this->logradouro;
        $this->bairro = $endereco['bairro'] ?: $this->bairro;
        $this->cidade = $endereco['cidade'] ?: $this->cidade;
        $this->uf = $endereco['uf'] ?: $this->uf;
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

        $this->authorize('configurar', $this->academia);

        $dados = $this->dadosNormalizados();

        Validator::make(
            [...$dados, 'logo' => $this->logo],
            $this->rules(),
            $this->messages(),
        )->validate();

        if ($this->logo !== null) {
            $dados['logo_path'] = $this->guardarLogo();
        }

        $this->academia->update($dados);
        $this->academia->refresh();
        $this->logo = null;

        session()->flash('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => 'Dados atualizados. Os próximos documentos já saem com eles.',
        ]);
    }

    public function removerLogo(): void
    {
        $this->authorize('configurar', $this->academia);

        if ($this->academia->logo_path) {
            Storage::disk('public')->delete($this->academia->logo_path);
        }

        $this->academia->update(['logo_path' => null]);
        $this->academia->refresh();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'nullable', 'string', 'size:14',
                'unique:academias,cnpj,'.$this->academia->id,
                // Dígito verificador conferido: CNPJ errado só aparece na
                // primeira nota fiscal, meses depois.
                fn (string $campo, mixed $valor, callable $falhar) => Documentos::cnpjValido((string) $valor)
                    ? null
                    : $falhar('O CNPJ informado não é válido.'),
            ],
            'email' => ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'min:10', 'max:11'],
            'whatsapp' => ['nullable', 'string', 'min:10', 'max:11'],
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da academia.',
            'email.required' => 'Informe o e-mail da academia.',
            'cep.digits' => 'O CEP precisa ter 8 dígitos.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado em outra academia.',
            'logo.image' => 'A logo precisa ser uma imagem.',
            'logo.max' => 'A logo precisa ter até 2 MB.',
        ];
    }

    /**
     * Guarda no disco público: a logo entra em PDF e em tela, e não é dado
     * sensível — ao contrário do template biométrico, que nunca vira arquivo.
     */
    private function guardarLogo(): string
    {
        $anterior = $this->academia->logo_path;

        $caminho = $this->logo->store("academias/{$this->academia->id}", 'public');

        // Trocar a logo apaga a antiga: guardar as duas encheria o disco com
        // arquivos que ninguém consegue mais alcançar pela interface.
        if ($anterior && $anterior !== $caminho) {
            Storage::disk('public')->delete($anterior);
        }

        return $caminho;
    }

    /** @return array<model-property<Academia>, mixed> */
    private function dadosNormalizados(): array
    {
        $texto = static fn (string $valor): ?string => trim($valor) !== '' ? trim($valor) : null;
        $digitos = static fn (string $valor): ?string => ($limpo = preg_replace('/\D/', '', $valor)) !== ''
            ? $limpo
            : null;

        return [
            'nome' => trim($this->nome),
            'razao_social' => $texto($this->razao_social),
            'cnpj' => $digitos($this->cnpj),
            'email' => mb_strtolower(trim($this->email)),
            'telefone' => $digitos($this->telefone),
            'whatsapp' => $digitos($this->whatsapp),
            'cep' => $digitos($this->cep),
            'logradouro' => $texto($this->logradouro),
            'numero' => $texto($this->numero),
            'complemento' => $texto($this->complemento),
            'bairro' => $texto($this->bairro),
            'cidade' => $texto($this->cidade),
            'uf' => $this->uf !== '' ? mb_strtoupper(trim($this->uf)) : null,
        ];
    }

    private function preencher(): void
    {
        $academia = $this->academia;

        $this->nome = (string) $academia->nome;
        $this->razao_social = (string) $academia->razao_social;
        $this->cnpj = (string) $academia->cnpj;
        $this->email = (string) $academia->email;
        $this->telefone = (string) $academia->telefone;
        $this->whatsapp = (string) $academia->whatsapp;
        $this->cep = (string) $academia->cep;
        $this->logradouro = (string) $academia->logradouro;
        $this->numero = (string) $academia->numero;
        $this->complemento = (string) $academia->complemento;
        $this->bairro = (string) $academia->bairro;
        $this->cidade = (string) $academia->cidade;
        $this->uf = (string) $academia->uf;
    }

    public function render(): View
    {
        return view('livewire.configuracoes.dados-da-academia', [
            'logoAtual' => $this->academia->logo_path
                ? Storage::disk('public')->url($this->academia->logo_path)
                : null,
        ]);
    }
}
