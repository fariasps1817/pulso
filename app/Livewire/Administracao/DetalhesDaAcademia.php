<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A ficha da academia, do lado do Pulso.
 *
 * É aqui que se bloqueia quem não pagou — e o que se muda é a SITUAÇÃO, nunca
 * apagando nada. Uma academia cancelada guarda os dados dela pelo prazo legal;
 * o que acaba é o acesso.
 *
 * Nenhum dado de dentro da academia aparece: sem aluno, sem mensalidade, sem
 * acesso. Só o total de alunos, que é um número que ela mesma mantém.
 */
#[Layout('layouts.administracao', ['secao' => 'academias'])]
final class DetalhesDaAcademia extends Component
{
    public Academia $academia;

    public string $situacao = '';

    public string $motivo_bloqueio = '';

    public ?string $assinatura_vence_em = null;

    public function mount(Academia $academia): void
    {
        $this->academia = $academia;
        $this->situacao = $academia->situacao->value;
        $this->motivo_bloqueio = (string) $academia->motivo_bloqueio;
        $this->assinatura_vence_em = $academia->assinatura_vence_em?->toDateString();
    }

    /**
     * Muda a situação da academia.
     *
     * Bloquear e cancelar exigem motivo: quem atender o telefone da academia
     * amanhã precisa saber por quê, e "alguém bloqueou" não é resposta. O
     * motivo é interno — a academia vê que está bloqueada, não o texto.
     */
    public function alterarSituacao(): void
    {
        $nova = SituacaoAcademia::from($this->situacao);
        $exigeMotivo = ! $nova->permiteAcessoAoSistema();

        $this->validate([
            'situacao' => ['required'],
            'motivo_bloqueio' => [$exigeMotivo ? 'required' : 'nullable', 'string', 'max:1000'],
            'assinatura_vence_em' => ['nullable', 'date'],
        ], [
            'motivo_bloqueio.required' => 'Diga por que a academia está sendo suspensa. Quem atender o telefone dela amanhã precisa saber.',
        ]);

        $this->academia->update([
            'situacao' => $nova,
            'motivo_bloqueio' => $exigeMotivo ? trim($this->motivo_bloqueio) : null,
            // Registra QUANDO — sem isso não há como saber há quanto tempo a
            // academia está fora, nem cobrar retroativo.
            'bloqueada_em' => $exigeMotivo
                ? ($this->academia->bloqueada_em ?? CarbonImmutable::now())
                : null,
            'assinatura_vence_em' => $this->assinatura_vence_em ?: null,
        ]);

        $this->academia->refresh();

        session()->flash('pulso.aviso', [
            'tipo' => $exigeMotivo ? 'atencao' : 'sucesso',
            'texto' => "Situação alterada para {$nova->rotulo()}.",
        ]);
    }

    public function render(): View
    {
        return view('livewire.administracao.detalhes-da-academia', [
            'situacoes' => collect(SituacaoAcademia::cases())
                ->mapWithKeys(fn (SituacaoAcademia $s): array => [$s->value => $s->rotulo()])
                ->all(),
            'unidades' => $this->academia->unidades()->orderBy('id')->get(),
            /*
             * `users` é plano de controle e fica fora do isolamento — é a
             * única tabela do interior da academia que o super administrador
             * alcança, porque a autenticação precisa acontecer antes de
             * existir "academia atual".
             */
            'equipe' => User::query()
                ->where('academia_id', $this->academia->id)
                ->with('roles')
                ->orderByDesc('ativo')
                ->orderBy('name')
                ->get(),
        ])->title($this->academia->nome);
    }
}
