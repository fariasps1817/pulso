<?php

declare(strict_types=1);

namespace App\Livewire\Acesso;

use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\DispositivoAcesso;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Um aparelho biométrico de mentira, falando o protocolo de verdade.
 *
 * POR QUE ELE FALA O PROTOCOLO INTEIRO
 *
 * Seria mais fácil um botão que gravasse uma linha na tabela de acessos. Mas
 * isso testaria a tela e não testaria nada do que é difícil: o TAB entre os
 * campos, a resposta que precisa ser "OK", a chave de idempotência, a fila de
 * comandos, o contexto que sai do número de série. Quando o equipamento
 * chegasse, a integração seria estreia — e não confirmação.
 *
 * Então este simulador monta a mesma linha de ATTLOG que o SenseFace monta e
 * a entrega nos mesmos endpoints `/iclock/*`, passando pelo mesmo middleware.
 * O que aparece na tela é o tráfego cru dos dois lados.
 *
 * Fica fora do ar em produção.
 */
#[Layout('layouts.painel', ['secao' => 'acesso'])]
#[Title('Simulador de catraca')]
final class Simulador extends Component
{
    public ?int $dispositivoId = null;

    public ?int $alunoId = null;

    /** Método de identificação, nos códigos do protocolo. */
    public int $metodo = 15;

    /** Uma matrícula que não existe, para simular cadastro velho no leitor. */
    public string $pinAvulso = '';

    /** @var list<array{titulo: string, enviado: string, recebido: string, nota: string}> */
    public array $conversa = [];

    public function mount(): void
    {
        abort_if(app()->isProduction(), 404);
        abort_unless(auth()->user()->can('dispositivo.ver'), 403);

        $this->dispositivoId = DispositivoAcesso::query()->value('id');
        $this->alunoId = Aluno::query()->value('id');
    }

    public function render(): View
    {
        return view('livewire.acesso.simulador', [
            'aparelhos' => DispositivoAcesso::query()->orderBy('nome')->get(),
            'alunos' => Aluno::query()->orderBy('nome')->limit(100)->get(),
            'presentes' => Acesso::query()
                ->presentes()
                ->whereNotNull('aluno_id')
                ->with('aluno')
                ->orderByDesc('ocorreu_em')
                ->get(),
        ]);
    }

    // -----------------------------------------------------------------
    // Ações
    // -----------------------------------------------------------------

    /** O aparelho reconheceu alguém e empurrou a passagem. */
    public function detectar(): void
    {
        $aparelho = $this->aparelho();

        if ($aparelho === null) {
            return;
        }

        $pin = $this->pinAvulso !== '' ? $this->pinAvulso : (string) $this->alunoId;

        if ($pin === '') {
            return;
        }

        $antes = Acesso::query()->count();
        $corpo = $this->linhaDeAttlog($pin);

        $resposta = $this->falarComOPulso(
            'POST',
            "/iclock/cdata?SN={$aparelho->numero_serie}&table=ATTLOG&Stamp=9999",
            $corpo,
        );

        $criado = Acesso::query()->count() > $antes;

        $novo = $criado
            ? Acesso::query()->orderByDesc('id')->first()
            : null;

        $this->registrar(
            'Passagem na catraca',
            $corpo,
            $resposta,
            $novo !== null
                ? "Registrada como {$novo->sentido->rotulo()}."
                : 'Nada foi gravado — repique dentro da janela, ou lote já recebido.',
        );
    }

    /**
     * O mesmo lote, de novo.
     *
     * É o que acontece de verdade quando a rede oscila e o aparelho não
     * recebe o "OK": ele reenvia tudo. Se a idempotência falhar, o número de
     * passagens sobe aqui.
     */
    public function reenviarUltimoLote(): void
    {
        $ultima = collect($this->conversa)->firstWhere('titulo', 'Passagem na catraca');

        if ($ultima === null) {
            return;
        }

        $aparelho = $this->aparelho();

        if ($aparelho === null) {
            return;
        }

        $antes = Acesso::query()->count();

        $resposta = $this->falarComOPulso(
            'POST',
            "/iclock/cdata?SN={$aparelho->numero_serie}&table=ATTLOG&Stamp=9999",
            $ultima['enviado'],
        );

        $depois = Acesso::query()->count();

        $this->registrar(
            'Reenvio do mesmo lote',
            $ultima['enviado'],
            $resposta,
            $depois === $antes
                ? 'Nenhuma passagem duplicada — a chave de origem barrou.'
                : 'ATENÇÃO: a passagem foi duplicada. A idempotência não está valendo.',
        );
    }

    /** O aparelho ligando e se apresentando. */
    public function handshake(): void
    {
        $aparelho = $this->aparelho();

        if ($aparelho === null) {
            return;
        }

        $url = "/iclock/cdata?SN={$aparelho->numero_serie}&options=all"
            .'&language=80&pushver=2.4.1&DeviceType=att&PushOptionsFlag=1';

        $this->registrar(
            'Handshake (o aparelho ligou)',
            "GET {$url}",
            $this->falarComOPulso('GET', $url),
            'É a resposta que define o intervalo do polling, o fuso e quais tipos de dado o aparelho vai mandar.',
        );
    }

    /** O polling: "tem alguma coisa para mim?" */
    public function consultarFila(): void
    {
        $aparelho = $this->aparelho();

        if ($aparelho === null) {
            return;
        }

        $url = "/iclock/getrequest?SN={$aparelho->numero_serie}";
        $resposta = $this->falarComOPulso('GET', $url);

        $this->registrar(
            'Consulta à fila de comandos',
            "GET {$url}",
            $resposta,
            trim($resposta) === 'OK'
                ? 'Fila vazia. O aparelho volta a perguntar depois do intervalo.'
                : 'Um comando saiu da fila e foi entregue.',
        );
    }

    /** O aparelho confirmando o último comando que recebeu. */
    public function confirmarComando(int $id, int $retorno = 0): void
    {
        $aparelho = $this->aparelho();

        if ($aparelho === null) {
            return;
        }

        $corpo = "ID={$id}&Return={$retorno}&CMD=DATA";

        $this->registrar(
            'Confirmação do comando',
            $corpo,
            $this->falarComOPulso('POST', "/iclock/devicecmd?SN={$aparelho->numero_serie}", $corpo),
            $retorno === 0 ? 'Aplicado no aparelho.' : 'Recusado — o código fica registrado na fila.',
        );
    }

    public function limpar(): void
    {
        $this->conversa = [];
    }

    // -----------------------------------------------------------------

    /**
     * A linha exata que o SenseFace monta.
     *
     * Campos separados por TAB. `255` no terceiro campo é o "sem estado":
     * o aparelho não sabe se a pessoa entrou ou saiu.
     */
    private function linhaDeAttlog(string $pin): string
    {
        $instante = CarbonImmutable::now()->format('Y-m-d H:i:s');

        return implode("\t", [$pin, $instante, '255', (string) $this->metodo, '0', '0', '0']);
    }

    /**
     * Entrega a requisição ao próprio Pulso, pela porta da frente.
     *
     * Passa pelo roteador, pelo middleware que identifica o aparelho e pelo
     * controller — é o mesmo caminho de uma chamada vinda do equipamento. Por
     * isso o resultado vale como confirmação, e não como encenação.
     *
     * A requisição original é devolvida ao container depois: o Livewire ainda
     * precisa dela para montar a resposta desta tela.
     */
    private function falarComOPulso(string $metodo, string $url, string $corpo = ''): string
    {
        $original = request();

        /*
         * O middleware TEM que rodar: é ele que traduz o número de série em
         * academia, e simular sem ele seria simular a parte fácil. O harness
         * de teste do Livewire desliga o middleware das rotas enquanto uma
         * ação de componente roda, então religamos pelo tempo da chamada.
         *
         * Em produção esta chave nem existe, e o trecho não faz nada.
         */
        $desligado = app()->bound('middleware.disable') && app()->make('middleware.disable') === true;

        if ($desligado) {
            unset(app()['middleware.disable']);
        }

        try {
            $requisicao = Request::create($url, $metodo, [], [], [], [
                'CONTENT_TYPE' => 'text/plain',
            ], $corpo);

            return app(Kernel::class)->handle($requisicao)->getContent();
        } finally {
            if ($desligado) {
                app()->instance('middleware.disable', true);
            }

            app()->instance('request', $original);
        }
    }

    private function aparelho(): ?DispositivoAcesso
    {
        return $this->dispositivoId === null
            ? null
            : DispositivoAcesso::query()->find($this->dispositivoId);
    }

    private function registrar(string $titulo, string $enviado, string $recebido, string $nota): void
    {
        // Mais recente no topo: é o que se está olhando.
        array_unshift($this->conversa, [
            'titulo' => $titulo,
            'enviado' => $enviado,
            'recebido' => trim($recebido),
            'nota' => $nota,
        ]);

        $this->conversa = array_slice($this->conversa, 0, 12);
    }
}
