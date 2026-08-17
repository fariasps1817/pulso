<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Models\Academia;
use App\Models\AvisoAcademia;
use App\Rules\DataBrasileira;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Os recados do Pulso para as academias.
 *
 * É o único canal do fornecedor com o cliente dentro do sistema. Serve ao
 * caso comercial — "sua assinatura vence em cinco dias" — e ao operacional —
 * "manutenção domingo às 2h", que vale para todas de uma vez.
 *
 * A JANELA DE EXIBIÇÃO É OBRIGATÓRIA, e não um extra. Aviso sem data de fim
 * vira parte da paisagem: fica meses na tela, ninguém mais lê, e quando vier
 * um recado que importa ele já terá sido treinado a ser ignorado.
 */
#[Layout('layouts.administracao', ['secao' => 'avisos'])]
#[Title('Avisos')]
final class Avisos extends Component
{
    /** Nulo = para todas as academias. */
    public ?int $academia_id = null;

    public string $tipo = 'informativo';

    public string $titulo = '';

    public string $mensagem = '';

    public string $exibir_de = '';

    public string $exibir_ate = '';

    public bool $dispensavel = true;

    public bool $publicando = false;

    public function mount(): void
    {
        $hoje = CarbonImmutable::now();

        $this->exibir_de = $hoje->format('d/m/Y');
        $this->exibir_ate = $hoje->addDays(7)->format('d/m/Y');
    }

    public function publicar(): void
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

        $dados = [
            'academia_id' => $this->academia_id,
            'tipo' => $this->tipo,
            'titulo' => trim($this->titulo),
            'mensagem' => trim($this->mensagem),
            'exibir_de' => $this->exibir_de,
            'exibir_ate' => $this->exibir_ate,
            'dispensavel' => $this->dispensavel,
        ];

        Validator::make($dados, [
            'academia_id' => ['nullable', 'exists:academias,id'],
            'tipo' => ['required', Rule::in(['informativo', 'atencao', 'erro'])],
            'titulo' => ['required', 'string', 'min:3', 'max:255'],
            'mensagem' => ['required', 'string', 'min:5', 'max:1000'],
            'exibir_de' => ['required', new DataBrasileira],
            'exibir_ate' => ['required', new DataBrasileira],
            'dispensavel' => ['boolean'],
        ], [
            'titulo.required' => 'Escreva um título curto — é o que a academia lê primeiro.',
            'mensagem.required' => 'Escreva o recado.',
            'exibir_de.required' => 'Informe a partir de quando o aviso aparece.',
            'exibir_ate.required' => 'Informe até quando. Aviso sem fim vira paisagem.',
        ])->validate();

        $de = DataBrasileira::converter($this->exibir_de);
        $ate = DataBrasileira::converter($this->exibir_ate);

        if ($de === null || $ate === null || $ate->lessThan($de)) {
            $this->addError('exibir_ate', 'A data final não pode ser anterior à inicial.');

            return;
        }

        AvisoAcademia::create([
            ...$dados,
            'exibir_de' => $de->toDateString(),
            'exibir_ate' => $ate->toDateString(),
            // Quem escreveu. Numa reclamação de "recebi um aviso errado", é a
            // primeira pergunta.
            'criado_por' => auth()->id(),
        ]);

        $this->reset(['titulo', 'mensagem', 'academia_id', 'publicando']);
        $this->tipo = 'informativo';
        $this->dispensavel = true;

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Aviso publicado.']);
    }

    /** Tirar do ar é apagar: aviso vencido já não aparece, e histórico de recado não serve para nada. */
    public function remover(int $id): void
    {
        AvisoAcademia::query()->whereKey($id)->delete();

        session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => 'Aviso removido.']);
    }

    public function render(): View
    {
        $hoje = CarbonImmutable::now()->toDateString();

        return view('livewire.administracao.avisos', [
            'avisos' => AvisoAcademia::query()
                ->with('academia')
                ->orderByDesc('exibir_ate')
                ->orderByDesc('id')
                ->get(),
            'academias' => Academia::query()->orderBy('nome')->pluck('nome', 'id')->all(),
            'hoje' => $hoje,
        ]);
    }
}
