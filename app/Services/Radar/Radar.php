<?php

declare(strict_types=1);

namespace App\Services\Radar;

use App\Enums\ResultadoAcesso;
use App\Enums\SentidoAcesso;
use App\Enums\SituacaoMatricula;
use App\Models\Academia;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Os números do Radar.
 *
 * Objeto de consulta separado da tela, porque o Radar vai reaparecer no "Meu
 * Pulso", em relatório e em notificação — e a definição de "vencido" e de
 * "sumiu" não pode divergir entre eles.
 *
 * NENHUM número aqui é lido de coluna de estado. "Vencida" é derivada do
 * vencimento; "sumiu" é derivado da última passagem na catraca. É o que faz o
 * Radar nunca mentir: ele não depende de uma rotina ter virado uma chave de
 * madrugada.
 */
final class Radar
{
    /** @param list<int>|null $unidades Nulo = todas as unidades da academia. */
    public function __construct(
        private readonly Academia $academia,
        private readonly ?array $unidades = null,
        private readonly ?CarbonImmutable $hoje = null,
    ) {}

    private function hoje(): CarbonImmutable
    {
        return $this->hoje ?? CarbonImmutable::now()->startOfDay();
    }

    // -----------------------------------------------------------------
    // Dinheiro
    // -----------------------------------------------------------------

    /** @return array{total: string, alunos: int} */
    public function vencidas(): array
    {
        return $this->totalizar($this->mensalidades()->vencidas($this->hoje()));
    }

    /** @return array{total: string, alunos: int} */
    public function vencemHoje(): array
    {
        return $this->totalizar($this->mensalidades()->vencendoEm($this->hoje()));
    }

    /**
     * As vencidas mais antigas primeiro — é a ordem em que a recepção liga.
     *
     * @return Collection<int, Mensalidade>
     */
    public function listaDeVencidas(int $limite = 6): Collection
    {
        return $this->mensalidades()
            ->vencidas($this->hoje())
            ->with('aluno')
            ->orderBy('vencimento')
            ->limit($limite)
            ->get();
    }

    /**
     * O que efetivamente ENTROU no mês.
     *
     * É outra pergunta, não o avesso da anterior: "a receber" é operação de
     * balcão, "o que entrou" é faturamento. Por isso este número fica atrás de
     * `relatorio_financeiro.ver`, e os de cima não.
     *
     * Estornado não conta — o dinheiro voltou.
     */
    public function recebidoNoMes(): string
    {
        return (string) Pagamento::query()
            ->validos()
            ->whereBetween('recebido_em', [
                $this->hoje()->startOfMonth()->toDateString(),
                $this->hoje()->endOfMonth()->toDateString(),
            ])
            ->when($this->unidades !== null, fn (Builder $q) => $q->whereHas(
                'mensalidade',
                fn (Builder $m) => $m->whereIn('unidade_id', $this->unidades),
            ))
            ->sum('valor');
    }

    // -----------------------------------------------------------------
    // Frequência
    // -----------------------------------------------------------------

    /**
     * A catraca já registrou alguma passagem?
     *
     * Sem essa pergunta, "baixa frequência" acusaria TODOS os alunos de
     * sumidos só porque o equipamento ainda não foi integrado. Um número que
     * assusta e não significa nada é pior do que número nenhum.
     */
    public function catracaEmUso(): bool
    {
        return Acesso::query()
            ->when($this->unidades !== null, fn (Builder $q) => $q->whereIn('unidade_id', $this->unidades))
            ->exists();
    }

    public function diasDeBaixaFrequencia(): int
    {
        return $this->academia->dias_baixa_frequencia;
    }

    /**
     * Alunos com matrícula em vigor que não treinam há N dias.
     *
     * @return Collection<int, Aluno>
     */
    public function sumidos(int $limite = 6): Collection
    {
        if (! $this->catracaEmUso()) {
            return collect();
        }

        return $this->consultaDeSumidos()
            ->withMax(
                ['acessos as ultimo_acesso_em' => fn (Builder $q) => $q
                    ->where('resultado', ResultadoAcesso::Liberado)
                    ->where('sentido', SentidoAcesso::Entrada)],
                'ocorreu_em',
            )
            /*
             * Sumiu E deve: é o perfil de quem cancela no mês seguinte.
             * Vale marcar, porque muda a conversa — não é ligar para saber
             * se está tudo bem, é ligar antes de perder o aluno.
             */
            ->withExists(['mensalidades as deve' => $this->apenasVencidas(...)])
            // Quem nunca apareceu vem primeiro: é o caso mais urgente.
            ->orderByRaw('ultimo_acesso_em ASC NULLS FIRST')
            ->limit($limite)
            ->get();
    }

    public function totalDeSumidos(): int
    {
        return $this->catracaEmUso() ? $this->consultaDeSumidos()->count() : 0;
    }

    /** @return Builder<Aluno> */
    private function consultaDeSumidos(): Builder
    {
        $limite = $this->hoje()->subDays($this->diasDeBaixaFrequencia());

        return Aluno::query()
            ->whereHas('matriculas', function (Builder $consulta): void {
                $consulta->whereIn('situacao', [
                    SituacaoMatricula::Ativa->value,
                    SituacaoMatricula::Experiencia->value,
                ]);

                $this->restringirUnidade($consulta);
            })
            /*
             * Duas restrições, e cada uma corrige um jeito diferente de o
             * número mentir:
             *
             * Só passagem LIBERADA conta como treino. Quem foi barrado na
             * catraca apareceu, mas não treinou — e é justamente quem a
             * academia precisa procurar.
             *
             * Só ENTRADA conta. A catraca é de contato seco e o Pulso deduz o
             * sentido alternando; contar a saída também faria a frequência de
             * quem registra as duas pontas valer o dobro.
             *
             * Sem entrada liberada recente = sumido. Inclui quem nunca veio.
             */
            ->whereDoesntHave('acessos', fn (Builder $q) => $q
                ->where('resultado', ResultadoAcesso::Liberado)
                ->where('sentido', SentidoAcesso::Entrada)
                ->where('ocorreu_em', '>=', $limite));
    }

    // -----------------------------------------------------------------
    // Aniversariantes
    // -----------------------------------------------------------------

    /**
     * A lista que a recepção abre de manhã. É o motivo de a data de
     * nascimento ser obrigatória no cadastro.
     *
     * @return Collection<int, Aluno>
     */
    public function aniversariantesDeHoje(): Collection
    {
        return Aluno::query()
            ->aniversariantesDe($this->hoje())
            ->whereHas('matriculas', function (Builder $consulta): void {
                $consulta->whereIn('situacao', [
                    SituacaoMatricula::Ativa->value,
                    SituacaoMatricula::Experiencia->value,
                ]);

                $this->restringirUnidade($consulta);
            })
            ->orderBy('nome')
            ->get();
    }

    // -----------------------------------------------------------------

    /**
     * @param  Builder<Mensalidade>  $consulta
     * @return array{total: string, alunos: int}
     */
    private function totalizar(Builder $consulta): array
    {
        return [
            'total' => (string) $consulta->clone()->sum('valor'),
            'alunos' => $consulta->clone()->distinct()->count('aluno_id'),
        ];
    }

    /** @return Builder<Mensalidade> */
    private function mensalidades(): Builder
    {
        // Os scopes `vencidas`/`vencendoEm` já restringem a situação.
        return Mensalidade::query()
            ->when($this->unidades !== null, fn (Builder $q) => $q->whereIn('unidade_id', $this->unidades));
    }

    /**
     * A MESMA definição de vencida usada nos totais — método nomeado só para
     * o subconsulta chegar tipada.
     *
     * @param  Builder<Mensalidade>  $consulta
     */
    private function apenasVencidas(Builder $consulta): void
    {
        $consulta->vencidas($this->hoje());
    }

    /** @param Builder<covariant \Illuminate\Database\Eloquent\Model> $consulta */
    private function restringirUnidade(Builder $consulta): void
    {
        if ($this->unidades !== null) {
            $consulta->whereIn('unidade_id', $this->unidades);
        }
    }
}
