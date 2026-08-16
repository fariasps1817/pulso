<?php

declare(strict_types=1);

namespace Tests\Feature\Alunos;

use App\Enums\SentidoAcesso;
use App\Enums\SituacaoMatricula;
use App\Livewire\Alunos\Detalhes;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\DispositivoAcesso;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * As três abas da ficha do aluno.
 *
 * O que se prova aqui, além do conteúdo: que o professor não vê dinheiro em
 * lugar nenhum, e que a aba de frequência não acusa ausência quando a catraca
 * simplesmente ainda não existe.
 */
final class AbasDaFichaTest extends ContextoDeAcademia
{
    private function aluno(): Aluno
    {
        return Aluno::factory()->create(['nome' => 'Marcos Ficha']);
    }

    private function matricular(Aluno $aluno, SituacaoMatricula $situacao = SituacaoMatricula::Ativa, ?string $inicio = null): Matricula
    {
        return Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create(['nome' => 'Mensal Livre'])->id,
            'situacao' => $situacao,
            'inicio_em' => $inicio ?? CarbonImmutable::now()->subMonths(3)->toDateString(),
        ]);
    }

    private function passar(Aluno $aluno, string $quando, ?string $ate = null): Acesso
    {
        return Acesso::create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'ocorreu_em' => CarbonImmutable::parse($quando, config('app.timezone')),
            'encerrada_em' => $ate === null ? null : CarbonImmutable::parse($ate, config('app.timezone')),
            'sentido' => SentidoAcesso::Entrada,
            'resultado' => 'liberado',
            'tipo_credencial' => 'facial',
        ]);
    }

    // -----------------------------------------------------------------
    // Matrícula
    // -----------------------------------------------------------------

    public function test_mostra_o_plano_a_unidade_e_a_vigencia(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertSee('Mensal Livre')
            ->assertSee('Matriz');
    }

    /**
     * O histórico importa: "saiu e voltou" é outra informação, e sumiria se a
     * aba mostrasse só a matrícula vigente.
     */
    public function test_lista_tambem_as_matriculas_encerradas(): void
    {
        $aluno = $this->aluno();

        $this->matricular($aluno, SituacaoMatricula::Encerrada, CarbonImmutable::now()->subYears(2)->toDateString())
            ->update(['plano_id' => Plano::factory()->create(['nome' => 'Trimestral Antigo'])->id]);

        $this->matricular($aluno);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertViewHas('matriculas', fn ($m) => $m->count() === 2)
            ->assertSee('Trimestral Antigo')
            ->assertSee('Mensal Livre');
    }

    public function test_aluno_sem_matricula_recebe_estado_vazio(): void
    {
        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $this->aluno()])
            ->assertSee('Este aluno ainda não tem matrícula');
    }

    // -----------------------------------------------------------------
    // Mensalidades
    // -----------------------------------------------------------------

    public function test_soma_o_que_o_aluno_deve(): void
    {
        $aluno = $this->aluno();
        $matricula = $this->matricular($aluno);

        foreach ([['2026-06-01', '2026-06-05'], ['2026-07-01', '2026-07-05']] as [$competencia, $vencimento]) {
            Mensalidade::factory()->create([
                'unidade_id' => $this->unidade->id,
                'matricula_id' => $matricula->id,
                'aluno_id' => $aluno->id,
                'competencia' => $competencia,
                'vencimento' => $vencimento,
                'valor' => 120,
            ]);
        }

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertViewHas('emAberto', '240.00')
            ->assertSee('R$ 240,00');
    }

    /**
     * Professor não vê dinheiro — nem valor de plano, nem mensalidade. E a
     * aba diz isso, em vez de sumir: o que some sem explicação parece defeito.
     */
    public function test_professor_nao_ve_valores_em_aba_nenhuma(): void
    {
        $aluno = $this->aluno();
        $matricula = $this->matricular($aluno);

        Mensalidade::factory()->create([
            'unidade_id' => $this->unidade->id,
            'matricula_id' => $matricula->id,
            'aluno_id' => $aluno->id,
            'valor' => 129.90,
        ]);

        Livewire::actingAs($this->usuarioCom('professor'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertViewHas('mensalidades', null)
            ->assertViewHas('emAberto', null)
            ->assertSee('Valores não fazem parte do seu acesso')
            ->assertDontSee('129,90')
            // O plano ele vê; o valor dele, não.
            ->assertSee('Mensal Livre');
    }

    // -----------------------------------------------------------------
    // Frequência
    // -----------------------------------------------------------------

    /**
     * O cuidado herdado do Radar: sem catraca integrada, não existe ausência
     * — existe falta de informação, e a tela diz qual das duas é.
     */
    public function test_sem_catraca_a_aba_nao_acusa_ausencia(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertViewHas('catracaEmUso', false)
            ->assertSee('A catraca ainda não registra acessos')
            ->assertDontSee('Nenhuma passagem registrada');
    }

    public function test_lista_as_passagens_e_conta_os_treinos_do_mes(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);
        DispositivoAcesso::factory()->create(['unidade_id' => $this->unidade->id]);

        $this->passar($aluno, CarbonImmutable::now()->subDays(2)->format('Y-m-d H:i:s'));
        $this->passar($aluno, CarbonImmutable::now()->subDays(9)->format('Y-m-d H:i:s'));
        // Fora da janela de 30 dias: aparece na lista, não na contagem.
        $this->passar($aluno, CarbonImmutable::now()->subDays(60)->format('Y-m-d H:i:s'));

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertViewHas('catracaEmUso', true)
            ->assertViewHas('treinosNoMes', 2)
            ->assertViewHas('frequencia', fn ($f) => $f->count() === 3);
    }

    public function test_calcula_a_permanencia_de_quem_registrou_as_duas_pontas(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);

        $this->passar($aluno, '2026-08-10 07:00:00', '2026-08-10 08:30:00');

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertSee('1h30');
    }

    /**
     * Saída presumida não vira número: ninguém mediu a hora, e um valor com
     * cara de medição faria a ficha mentir.
     */
    public function test_saida_presumida_aparece_como_nao_registrada(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);

        $this->passar($aluno, '2026-08-10 07:00:00', '2026-08-10 19:00:00')
            ->update(['encerrada_presumida' => true]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertSee('não registrada')
            ->assertDontSee('12h00');
    }

    public function test_quem_esta_dentro_aparece_como_na_academia(): void
    {
        $aluno = $this->aluno();
        $this->matricular($aluno);

        $this->passar($aluno, CarbonImmutable::now()->subHour()->format('Y-m-d H:i:s'));

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->assertSee('na academia');
    }
}
