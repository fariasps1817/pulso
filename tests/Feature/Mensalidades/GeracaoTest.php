<?php

declare(strict_types=1);

namespace Tests\Feature\Mensalidades;

use App\Enums\SituacaoMatricula;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use App\Support\Academia\ContextoAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Tests\ContextoDeAcademia;

/**
 * A rotina que gera as mensalidades do mês.
 *
 * O que mais importa aqui é a idempotência: ela roda todo dia, e rodar de
 * novo não pode duplicar cobrança. E a garantia não está no código — está no
 * índice único (matricula_id, competencia).
 */
final class GeracaoTest extends ContextoDeAcademia
{
    private function gerar(?string $competencia = null): void
    {
        // O comando percorre academias, então precisa começar sem contexto —
        // é assim que ele roda no agendador.
        $contexto = app(ContextoAcademia::class);
        $academiaAtual = $contexto->id();

        Artisan::call('pulso:gerar-mensalidades', array_filter([
            '--competencia' => $competencia,
            '--academia' => $this->academia->id,
        ]));

        $contexto->definir($academiaAtual);
    }

    /** @param array<string, mixed> $atributos */
    private function matriculaAtiva(array $atributos = []): Matricula
    {
        return Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => Aluno::factory()->create()->id,
            'plano_id' => Plano::factory()->create()->id,
            'valor_mensal' => 129.90,
            'dia_vencimento' => 5,
            'inicio_em' => CarbonImmutable::now()->subMonths(3)->toDateString(),
            ...$atributos,
        ]);
    }

    public function test_gera_a_mensalidade_do_mes_para_matricula_ativa(): void
    {
        $matricula = $this->matriculaAtiva();

        $this->gerar();

        $mensalidade = Mensalidade::where('matricula_id', $matricula->id)->first();

        $this->assertNotNull($mensalidade);
        $this->assertSame('129.90', $mensalidade->valor);
        $this->assertSame(
            CarbonImmutable::now()->startOfMonth()->toDateString(),
            $mensalidade->competencia->toDateString(),
        );
        // Competência + (dia - 1): vence no dia 5 do mês.
        $this->assertSame(5, $mensalidade->vencimento->day);
    }

    /**
     * A garantia que sustenta rodar todo dia. Não é o código que impede a
     * duplicata: é o índice único do banco.
     */
    public function test_rodar_de_novo_nao_duplica(): void
    {
        $this->matriculaAtiva();

        $this->gerar();
        $this->gerar();
        $this->gerar();

        $this->assertSame(1, Mensalidade::count());
    }

    public function test_matricula_trancada_nao_gera(): void
    {
        $this->matriculaAtiva();
        Matricula::query()->update(['situacao' => SituacaoMatricula::Suspensa]);

        $this->gerar();

        $this->assertSame(0, Mensalidade::count());
    }

    public function test_matricula_encerrada_nao_gera(): void
    {
        $matricula = $this->matriculaAtiva();
        $matricula->encerrar('Parou de treinar.');

        $this->gerar();

        $this->assertSame(0, Mensalidade::count());
    }

    public function test_experiencia_nao_gera(): void
    {
        Matricula::factory()->emExperiencia()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => Aluno::factory()->create()->id,
            'plano_id' => Plano::factory()->create()->id,
        ]);

        $this->gerar();

        $this->assertSame(0, Mensalidade::count());
    }

    /**
     * Sem cobrança proporcional: se o vencimento do mês já passou quando a
     * matrícula começou, a primeira cobrança fica para o mês seguinte.
     *
     * Matriculou dia 22 com vencimento no dia 5 — o dia 5 deste mês já era.
     */
    public function test_nao_cobra_vencimento_anterior_ao_inicio(): void
    {
        $this->matriculaAtiva([
            'inicio_em' => CarbonImmutable::now()->startOfMonth()->addDays(21)->toDateString(),
            'dia_vencimento' => 5,
        ]);

        $this->gerar();

        $this->assertSame(0, Mensalidade::count());
    }

    public function test_gera_competencia_informada(): void
    {
        $this->matriculaAtiva([
            'inicio_em' => CarbonImmutable::parse('2026-01-01')->toDateString(),
        ]);

        $this->gerar('2026-03');

        $mensalidade = Mensalidade::first();

        $this->assertSame('2026-03-01', $mensalidade->competencia->toDateString());
        $this->assertSame('2026-03-05', $mensalidade->vencimento->toDateString());
    }

    /** O valor sai da MATRÍCULA, não do plano: é o que foi contratado. */
    public function test_usa_o_valor_da_matricula_e_nao_o_do_plano(): void
    {
        $plano = Plano::factory()->create(['valor_mensal' => 199.90]);

        $this->matriculaAtiva([
            'plano_id' => $plano->id,
            'valor_mensal' => 99.90,
        ]);

        $this->gerar();

        $this->assertSame('99.90', Mensalidade::first()->valor);
    }
}
