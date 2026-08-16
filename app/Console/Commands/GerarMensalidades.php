<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SituacaoAcademia;
use App\Enums\SituacaoMatricula;
use App\Models\Academia;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Support\Academia\ContextoAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gera as mensalidades do mês para as matrículas ativas.
 *
 * Roda todo dia. É IDEMPOTENTE por construção: o índice único
 * (matricula_id, competencia) impede a segunda linha, então rodar duas vezes
 * no mesmo dia não duplica nada — e não é o código que garante isso, é o
 * banco.
 *
 * Gera SÓ a competência corrente. Adiantar meses criaria recebível fantasma:
 * o aluno que cancelar em março apareceria devendo abril e maio.
 *
 * RODA PELA CONEXÃO DA APLICAÇÃO, e não pela de manutenção. Ele percorre as
 * academias definindo o contexto de cada uma, então as políticas de Row Level
 * Security o autorizam academia por academia — sem precisar de um papel que
 * atravessa o isolamento. Menos privilégio para a mesma tarefa.
 *
 *     php artisan pulso:gerar-mensalidades
 */
final class GerarMensalidades extends Command
{
    protected $signature = 'pulso:gerar-mensalidades
        {--competencia= : Mês de referência no formato AAAA-MM. Padrão: o mês corrente}
        {--academia= : Gera só para uma academia}';

    protected $description = 'Gera as mensalidades do mês para as matrículas ativas.';

    public function handle(ContextoAcademia $contexto): int
    {
        $competencia = $this->competencia();

        if ($competencia === null) {
            $this->error('Competência inválida. Use o formato AAAA-MM.');

            return self::FAILURE;
        }

        $academias = Academia::query()
            // Academia cancelada não gera cobrança nova: o contrato acabou.
            ->whereIn('situacao', [
                SituacaoAcademia::Ativa->value,
                SituacaoAcademia::EmAviso->value,
                SituacaoAcademia::Bloqueada->value,
            ])
            ->when($this->option('academia'), fn ($q) => $q->whereKey($this->option('academia')))
            ->get();

        $criadas = 0;
        $puladas = 0;

        foreach ($academias as $academia) {
            [$novas, $existentes] = $contexto->paraAcademia(
                $academia->id,
                fn (): array => $this->gerarPara($competencia),
            );

            $criadas += $novas;
            $puladas += $existentes;

            $this->line("  {$academia->nome}: {$novas} criadas, {$existentes} já existiam.");
        }

        $contexto->limpar();

        $this->info("Competência {$competencia->format('m/Y')}: {$criadas} mensalidades criadas, {$puladas} já existiam.");

        return self::SUCCESS;
    }

    /** @return array{int, int} criadas e puladas */
    private function gerarPara(CarbonImmutable $competencia): array
    {
        $criadas = 0;
        $puladas = 0;

        $fimDaCompetencia = $competencia->endOfMonth();

        /*
         * Quem já tem mensalidade nesta competência, numa consulta só.
         *
         * O índice único continua sendo a garantia — inclusive contra duas
         * execuções simultâneas —, mas conferir antes evita provocar a
         * violação no caminho NORMAL. E isso importa: no PostgreSQL, um
         * comando que falha aborta o bloco inteiro, então "tentar e capturar"
         * como rotina derrubaria a transação a cada matrícula já gerada.
         */
        $jaGeradas = Mensalidade::query()
            ->whereDate('competencia', $competencia->startOfMonth())
            ->pluck('matricula_id')
            ->flip();

        Matricula::query()
            ->where('situacao', SituacaoMatricula::Ativa)
            // Matrícula que começou depois do mês não gera nada nele.
            ->whereDate('inicio_em', '<=', $fimDaCompetencia)
            ->whereNull('encerrada_em')
            ->chunkById(200, function ($matriculas) use ($competencia, $jaGeradas, &$criadas, &$puladas): void {
                foreach ($matriculas as $matricula) {
                    if ($jaGeradas->has($matricula->id)) {
                        $puladas++;

                        continue;
                    }

                    $this->gerarUma($matricula, $competencia) ? $criadas++ : $puladas++;
                }
            });

        return [$criadas, $puladas];
    }

    private function gerarUma(Matricula $matricula, CarbonImmutable $competencia): bool
    {
        $vencimento = $matricula->vencimentoDe($competencia);

        /*
         * Sem cobrança proporcional: se o vencimento do mês cai antes de a
         * matrícula começar, a primeira cobrança fica para o mês seguinte.
         * Matriculou dia 22 com vencimento no dia 5? O dia 5 deste mês já
         * passou — cobra-se a partir do próximo.
         */
        if ($vencimento->lessThan($matricula->inicio_em)) {
            return false;
        }

        try {
            /*
             * Em transação: o savepoint mantém a violação contida, para o caso
             * de duas execuções simultâneas passarem pela verificação anterior
             * e colidirem no índice. Sem ele, o bloco inteiro seria abortado e
             * as matrículas seguintes ficariam sem mensalidade.
             */
            DB::transaction(fn () => Mensalidade::create([
                'unidade_id' => $matricula->unidade_id,
                'matricula_id' => $matricula->id,
                'aluno_id' => $matricula->aluno_id,
                'competencia' => $competencia->startOfMonth()->toDateString(),
                'vencimento' => $vencimento->toDateString(),
                'valor' => $matricula->valor_mensal,
            ]));

            return true;
        } catch (QueryException $erro) {
            // 23505 = violação de unicidade: alguém gerou primeiro.
            if ($erro->getCode() === '23505') {
                return false;
            }

            throw $erro;
        }
    }

    private function competencia(): ?CarbonImmutable
    {
        $informada = $this->option('competencia');

        if ($informada === null) {
            return CarbonImmutable::now()->startOfMonth();
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $informada.'-01')->startOfMonth();
        } catch (Throwable) {
            return null;
        }
    }
}
