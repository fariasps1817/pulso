<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use App\Models\User;
use App\Rules\DataBrasileira;
use App\Services\Acesso\SenhaTemporaria;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    /** Mostrada uma vez, logo depois de redefinir. */
    public ?string $senhaTemporaria = null;

    public ?string $senhaDe = null;

    public function mount(Academia $academia): void
    {
        $this->academia = $academia;
        $this->situacao = $academia->situacao->value;
        $this->motivo_bloqueio = (string) $academia->motivo_bloqueio;
        /*
         * `d/m/Y`, e nao o formato do banco: o campo tem mascara brasileira, e
         * entregar `2027-02-16` a ela produzia `20/27/0216` na tela.
         */
        $this->assinatura_vence_em = $academia->assinatura_vence_em?->format('d/m/Y');
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
            'assinatura_vence_em' => ['nullable', new DataBrasileira],
        ], [
            'motivo_bloqueio.required' => 'Diga por que a academia está sendo suspensa. Quem atender o telefone dela amanhã precisa saber.',
        ]);

        $vencimento = $this->assinatura_vence_em !== null && $this->assinatura_vence_em !== ''
            ? DataBrasileira::converter($this->assinatura_vence_em)?->toDateString()
            : null;

        $this->academia->update([
            'situacao' => $nova,
            'motivo_bloqueio' => $exigeMotivo ? trim($this->motivo_bloqueio) : null,
            // Registra QUANDO — sem isso não há como saber há quanto tempo a
            // academia está fora, nem cobrar retroativo.
            'bloqueada_em' => $exigeMotivo
                ? ($this->academia->bloqueada_em ?? CarbonImmutable::now())
                : null,
            'assinatura_vence_em' => $vencimento,
        ]);

        $this->academia->refresh();

        session()->flash('pulso.aviso', [
            'tipo' => $exigeMotivo ? 'atencao' : 'sucesso',
            'texto' => "Situação alterada para {$nova->rotulo()}.",
        ]);
    }

    /**
     * Devolve o acesso a quem ficou de fora.
     *
     * O CASO REAL: o dono é um só, esqueceu a senha, e o e-mail de
     * recuperação ainda não está configurado. Sem isto, a academia fica
     * parada e a única saída seria mexer no banco.
     *
     * ISTO É UMA EXCEÇÃO CONSCIENTE À GARANTIA DE ISOLAMENTO. Em toda esta
     * área o Pulso não alcança dado de academia nenhuma — mas quem gera a
     * senha de um usuário pode entrar com ela. A troca é deliberada:
     *
     *   - a ação fica REGISTRADA com quem pediu (ver SenhaTemporaria);
     *   - a senha é temporária e a pessoa é obrigada a trocá-la, então o
     *     acesso da equipe do Pulso dura até o dono entrar;
     *   - as sessões abertas caem junto.
     *
     * Quando o envio de e-mail estiver configurado, a recuperação pelo próprio
     * usuário passa a ser o caminho normal, e esta tela vira último recurso.
     */
    public function redefinirSenhaDe(int $id): void
    {
        $alvo = User::query()
            ->where('academia_id', $this->academia->id)
            ->findOrFail($id);

        $this->senhaTemporaria = SenhaTemporaria::redefinirPara($alvo, auth()->user());
        $this->senhaDe = (string) $alvo->name;
    }

    public function fecharSenha(): void
    {
        $this->reset(['senhaTemporaria', 'senhaDe']);
    }

    /**
     * A equipe, com o papel de cada um.
     *
     * O pacote de permissoes resolve papel DENTRO de uma academia — quem e
     * gerente aqui nao e gerente na vizinha. O super administrador nao tem
     * academia, entao sem definir a desta tela os papeis voltavam vazios e a
     * coluna mostrava um travessao para todo mundo.
     *
     * @return Collection<int, User>
     */
    private function equipe()
    {
        setPermissionsTeamId($this->academia->id);

        $equipe = User::query()
            ->where('academia_id', $this->academia->id)
            ->with('roles')
            ->orderByDesc('ativo')
            ->orderBy('name')
            ->get();

        // Devolve o estado: o super administrador nao pertence a academia
        // nenhuma, e deixar o time preso aqui contaminaria o resto do pedido.
        setPermissionsTeamId(null);

        return $equipe;
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
            'equipe' => $this->equipe(),
        ])->title($this->academia->nome);
    }
}
