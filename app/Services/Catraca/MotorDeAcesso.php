<?php

declare(strict_types=1);

namespace App\Services\Catraca;

use App\Enums\ResultadoAcesso;
use App\Enums\SentidoAcesso;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\DispositivoAcesso;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Decide se a passagem foi entrada ou saída, e registra.
 *
 * O CONTEXTO FÍSICO, QUE EXPLICA TODAS AS REGRAS
 *
 * A catraca é de contato seco: o leitor reconhece o aluno, fecha um relé por
 * um segundo, a catraca libera o giro. Um equipamento por catraca. A catraca
 * não avisa para que lado girou, e o leitor manda `Status=255` — "sem
 * estado". Ou seja: NENHUMA informação de sentido existe no hardware.
 *
 * Então o sentido é deduzido por alternância, com três guardas:
 *
 *   1. REPIQUE. Duas detecções do mesmo aluno em segundos são a mesma
 *      passagem — o relé repicou, ou a pessoa mostrou o rosto de novo porque
 *      a catraca demorou a destravar. Sem esta guarda, a segunda detecção
 *      virava uma saída imediata, e o aluno "saía" no mesmo instante em que
 *      entrou.
 *
 *   2. TOLERÂNCIA. Entrada de muitas horas atrás quase certamente terminou
 *      sem registro — a pessoa saiu pela porta lateral, ou a academia fechou.
 *      Passado o limite, a próxima detecção é uma NOVA ENTRADA, e a anterior
 *      é encerrada como presumida.
 *
 *   3. HONESTIDADE. Saída presumida fica marcada como tal. Um relatório de
 *      permanência que trate dedução e leitura do mesmo jeito mente com
 *      confiança, e é pior do que não ter relatório.
 *
 * O que este motor NÃO faz: autorizar. No protocolo que o aparelho fala, ele
 * já reconheceu e já abriu o relé quando esta mensagem chega. Impedir a
 * passagem de quem está devendo é assunto da sincronização da lista de
 * usuários do aparelho, não daqui.
 */
final class MotorDeAcesso
{
    public function __construct(private readonly DispositivoAcesso $dispositivo) {}

    /**
     * Registra a passagem e devolve a linha criada.
     *
     * Devolve nulo quando não há o que registrar: lote reenviado (a chave de
     * origem já existe) ou repique dentro da janela.
     */
    public function registrar(RegistroDePassagem $registro, ?string $chaveDeOrigem = null): ?Acesso
    {
        $aluno = $this->alunoDoPin($registro->pin);

        if ($aluno === null) {
            // Passagem anônima: guardar mesmo assim. Um PIN que não casa com
            // ninguém é a pista de que o aparelho está com cadastro velho, e
            // sem a linha não há pista nenhuma.
            return $this->gravar($registro, null, SentidoAcesso::Entrada, $chaveDeOrigem);
        }

        $anterior = $this->ultimaPassagemDe($aluno);

        if ($this->ehRepique($anterior, $registro->ocorreuEm)) {
            return null;
        }

        $sentido = $this->deduzirSentido($anterior, $registro->ocorreuEm);

        return DB::transaction(function () use ($registro, $aluno, $sentido, $anterior, $chaveDeOrigem): ?Acesso {
            $acesso = $this->gravar($registro, $aluno, $sentido, $chaveDeOrigem);

            if ($acesso === null) {
                return null;
            }

            if ($anterior !== null && $anterior->estaDentro()) {
                /*
                 * `encerrada_em` é o instante em que a pessoa deixou de ser
                 * considerada presente — não uma leitura do relógio da porta.
                 * Quando a saída é presumida, esse instante é só o momento em
                 * que se concluiu, e por isso a permanência daquela visita
                 * fica indisponível em vez de virar um número inventado.
                 */
                $anterior->encerrar(
                    quando: $registro->ocorreuEm,
                    presumida: $sentido === SentidoAcesso::Entrada,
                );
            }

            return $acesso;
        });
    }

    /**
     * Fecha as entradas que ficaram abertas.
     *
     * Roda de madrugada. Sem isso, quem esqueceu de passar na saída aparece
     * como presente para sempre, e "quem está na academia agora" — o número
     * que a recepção usa para saber se pode fechar — vira ficção.
     */
    public static function encerrarEntradasAbandonadas(?CarbonImmutable $limite = null): int
    {
        $limite ??= CarbonImmutable::now()->subHours((int) config('pulso.catraca.horas_ate_presumir_saida'));

        $abertas = Acesso::query()
            ->presentes()
            ->where('ocorreu_em', '<', $limite)
            ->get();

        $agora = CarbonImmutable::now();

        foreach ($abertas as $entrada) {
            $entrada->encerrar($agora, presumida: true);
        }

        return $abertas->count();
    }

    // -----------------------------------------------------------------

    /**
     * A regra, em três linhas.
     *
     * Sem passagem anterior, ou a anterior foi saída: é entrada. Entrada
     * recente: é saída. Entrada antiga demais: a pessoa saiu sem registrar, e
     * esta é uma nova entrada.
     */
    private function deduzirSentido(?Acesso $anterior, CarbonImmutable $agora): SentidoAcesso
    {
        if ($anterior === null || $anterior->sentido === SentidoAcesso::Saida) {
            return SentidoAcesso::Entrada;
        }

        $horas = (int) config('pulso.catraca.horas_ate_presumir_saida');

        return $anterior->ocorreu_em->greaterThan($agora->subHours($horas))
            ? SentidoAcesso::Saida
            : SentidoAcesso::Entrada;
    }

    private function ehRepique(?Acesso $anterior, CarbonImmutable $agora): bool
    {
        if ($anterior === null) {
            return false;
        }

        $segundos = (int) config('pulso.catraca.janela_de_repique');

        return $anterior->ocorreu_em->greaterThan($agora->subSeconds($segundos));
    }

    private function ultimaPassagemDe(Aluno $aluno): ?Acesso
    {
        return Acesso::query()
            ->where('aluno_id', $aluno->id)
            ->where('unidade_id', $this->dispositivo->unidade_id)
            ->orderByDesc('ocorreu_em')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * O PIN do aparelho é o identificador do aluno.
     *
     * Escolha deliberada: é único, nunca é reaproveitado e não depende de
     * ninguém digitar uma matrícula sem errar. O aparelho comporta três mil
     * usuários — folga de sobra para o número crescer.
     */
    private function alunoDoPin(string $pin): ?Aluno
    {
        return ctype_digit($pin) ? Aluno::query()->find((int) $pin) : null;
    }

    private function gravar(
        RegistroDePassagem $registro,
        ?Aluno $aluno,
        SentidoAcesso $sentido,
        ?string $chaveDeOrigem,
    ): ?Acesso {
        try {
            /*
             * Em transação própria: se a chave já existir, o savepoint contém
             * a violação. Sem ele, o PostgreSQL abortaria o bloco inteiro e o
             * resto do lote se perderia — a mesma lição da geração de
             * mensalidades.
             */
            return DB::transaction(fn (): Acesso => Acesso::create([
                'unidade_id' => $this->dispositivo->unidade_id,
                'dispositivo_id' => $this->dispositivo->id,
                'aluno_id' => $aluno?->id,
                'pin' => $registro->pin,
                'ocorreu_em' => $registro->ocorreuEm,
                'sentido' => $sentido,
                'resultado' => ResultadoAcesso::Liberado,
                'tipo_credencial' => $registro->credencial(),
                'chave_origem' => $chaveDeOrigem,
            ]));
        } catch (QueryException $erro) {
            // 23505 = unicidade: o lote já tinha sido recebido.
            if ($erro->getCode() === '23505') {
                return null;
            }

            throw $erro;
        }
    }
}
