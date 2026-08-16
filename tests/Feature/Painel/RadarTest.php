<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Enums\ResultadoAcesso;
use App\Enums\SituacaoMatricula;
use App\Enums\SituacaoMensalidade;
use App\Livewire\Painel\Radar;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Models\Plano;
use App\Models\Unidade;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * O Radar.
 *
 * O que se prova aqui não é o layout: é que os números não mentem. Cada um
 * deles é derivado — de vencimento, de passagem na catraca —, nunca lido de
 * uma coluna que alguma rotina precisou virar de madrugada.
 */
final class RadarTest extends ContextoDeAcademia
{
    private function aluno(string $nome, ?CarbonImmutable $nascimento = null): Aluno
    {
        return Aluno::factory()->create([
            'nome' => $nome,
            // Um dia fixo que nao e hoje: senao todo aluno de teste vira aniversariante.
            'data_nascimento' => ($nascimento ?? CarbonImmutable::now()->subYears(30)->addDays(100))->toDateString(),
        ]);
    }

    private function comMatricula(Aluno $aluno, SituacaoMatricula $situacao = SituacaoMatricula::Ativa): Matricula
    {
        return Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create()->id,
            'situacao' => $situacao,
        ]);
    }

    private function mensalidade(Aluno $aluno, string $vencimento, float $valor = 100, ?Matricula $matricula = null): Mensalidade
    {
        return Mensalidade::factory()->create([
            'unidade_id' => $this->unidade->id,
            'matricula_id' => ($matricula ?? $this->comMatricula($aluno))->id,
            'aluno_id' => $aluno->id,
            // O indice unico (matricula_id, competencia) exige uma competencia
            // por mes — a mesma garantia de que a geracao automatica depende.
            'competencia' => CarbonImmutable::parse($vencimento)->startOfMonth()->toDateString(),
            'vencimento' => $vencimento,
            'valor' => $valor,
        ]);
    }

    private function passou(Aluno $aluno, CarbonImmutable $quando, ResultadoAcesso $resultado = ResultadoAcesso::Liberado): Acesso
    {
        return Acesso::create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'ocorreu_em' => $quando,
            'resultado' => $resultado,
        ]);
    }

    // -----------------------------------------------------------------
    // Dinheiro
    // -----------------------------------------------------------------

    public function test_soma_vencidas_e_conta_alunos_distintos(): void
    {
        $joao = $this->aluno('João Vencido');
        $ontem = CarbonImmutable::now()->subDay()->toDateString();

        // Duas mensalidades atrasadas do MESMO aluno: R$ 250, uma pessoa.
        // Na mesma matricula — o EXCLUDE do banco proibe vigencias sobrepostas.
        $matricula = $this->comMatricula($joao);
        $this->mensalidade($joao, $ontem, 100, matricula: $matricula);
        $this->mensalidade($joao, CarbonImmutable::now()->subMonth()->toDateString(), 150, matricula: $matricula);
        // Em dia, não entra.
        $this->mensalidade($this->aluno('Maria Em Dia'), CarbonImmutable::now()->addDays(10)->toDateString(), 90);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('vencidas', fn (array $v) => $v['total'] === '250.00' && $v['alunos'] === 1);
    }

    /**
     * "Vencida" continua sem ser coluna: o Radar deriva do vencimento, e
     * é por isso que ele não depende de nenhuma rotina noturna.
     */
    public function test_vencida_e_derivada_e_nao_lida_de_coluna(): void
    {
        $mensalidade = $this->mensalidade($this->aluno('Carlos Atrasado'), CarbonImmutable::now()->subDays(3)->toDateString());

        $this->assertSame(SituacaoMensalidade::Aberta, $mensalidade->situacao);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('vencidas', fn (array $v) => $v['alunos'] === 1)
            ->assertSee('Carlos Atrasado');
    }

    public function test_mensalidade_paga_sai_dos_numeros(): void
    {
        $mensalidade = $this->mensalidade($this->aluno('Ana Quitada'), CarbonImmutable::now()->subDays(5)->toDateString());
        $mensalidade->update(['situacao' => SituacaoMensalidade::Paga]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('vencidas', fn (array $v) => $v['total'] === '0' || $v['total'] === '0.00');
    }

    /**
     * "A receber" e "o que entrou" são perguntas diferentes — e só a segunda
     * é faturamento. A recepção precisa da primeira para atender; a segunda
     * não é dela.
     */
    public function test_faturamento_do_mes_so_aparece_para_quem_ve_relatorio(): void
    {
        $mensalidade = $this->mensalidade($this->aluno('Pedro Pagante'), CarbonImmutable::now()->toDateString());

        Pagamento::create([
            'mensalidade_id' => $mensalidade->id,
            'valor' => 100,
            'forma' => 'dinheiro',
            'recebido_em' => CarbonImmutable::now()->toDateString(),
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('recebido', '100.00');

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Radar::class)
            ->assertViewHas('recebido', null);
    }

    public function test_pagamento_estornado_nao_conta_como_faturamento(): void
    {
        $mensalidade = $this->mensalidade($this->aluno('Rita Estornada'), CarbonImmutable::now()->toDateString());

        Pagamento::create([
            'mensalidade_id' => $mensalidade->id,
            'valor' => 100,
            'forma' => 'dinheiro',
            'recebido_em' => CarbonImmutable::now()->toDateString(),
            'estornado_em' => CarbonImmutable::now(),
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('recebido', fn (string $v) => (float) $v === 0.0);
    }

    // -----------------------------------------------------------------
    // Frequência
    // -----------------------------------------------------------------

    /**
     * O ponto mais importante desta tela.
     *
     * Sem catraca integrada, TODO aluno seria "sumido" — um número grande,
     * assustador e sem significado nenhum. O Radar prefere dizer que não sabe.
     */
    public function test_sem_registro_de_catraca_nao_acusa_ninguem_de_sumido(): void
    {
        $this->comMatricula($this->aluno('Lucas Ativo'));
        $this->comMatricula($this->aluno('Bruna Ativa'));

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('catracaEmUso', false)
            ->assertViewHas('totalDeSumidos', 0)
            ->assertSee('A catraca ainda não registra acessos.')
            ->assertDontSee('Lucas Ativo');
    }

    public function test_conta_quem_nao_treina_ha_mais_dias_que_o_configurado(): void
    {
        $this->academia->update(['dias_baixa_frequencia' => 15]);

        $sumido = $this->aluno('Ricardo Sumido');
        $assiduo = $this->aluno('Sonia Assidua');
        $this->comMatricula($sumido);
        $this->comMatricula($assiduo);

        $this->passou($sumido, CarbonImmutable::now()->subDays(40));
        $this->passou($assiduo, CarbonImmutable::now()->subDays(2));

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('catracaEmUso', true)
            ->assertViewHas('totalDeSumidos', 1)
            ->assertSee('Ricardo Sumido')
            ->assertDontSee('Sonia Assidua');
    }

    /**
     * Quem foi BARRADO na catraca apareceu, mas não treinou — e é
     * exatamente quem a academia precisa procurar.
     */
    public function test_acesso_negado_nao_conta_como_treino(): void
    {
        $this->academia->update(['dias_baixa_frequencia' => 15]);

        $barrado = $this->aluno('Igor Barrado');
        $this->comMatricula($barrado);

        // Veio ontem, mas a catraca não liberou.
        $this->passou($barrado, CarbonImmutable::now()->subDay(), ResultadoAcesso::Bloqueado);
        // Alguém precisa ter treinado para a catraca contar como em uso.
        $this->passou($this->aluno('Outro Qualquer'), CarbonImmutable::now()->subDay());

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('totalDeSumidos', 1)
            ->assertSee('Igor Barrado');
    }

    /**
     * Sumiu E deve. É o perfil de quem cancela no mês seguinte, e por isso
     * muda a conversa: não é ligar para saber se está tudo bem, é ligar
     * antes de perder o aluno.
     */
    public function test_marca_quem_sumiu_e_tambem_deve(): void
    {
        $this->academia->update(['dias_baixa_frequencia' => 15]);

        $emRisco = $this->aluno('Vitor Risco');
        $matricula = $this->comMatricula($emRisco);
        $this->mensalidade($emRisco, CarbonImmutable::now()->subDays(20)->toDateString(), 100, matricula: $matricula);
        $this->passou($emRisco, CarbonImmutable::now()->subDays(30));

        $soSumido = $this->aluno('Clara Some');
        $this->comMatricula($soSumido);
        $this->passou($soSumido, CarbonImmutable::now()->subDays(30));

        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(Radar::class);

        $componente->assertViewHas('sumidos', function ($sumidos) use ($emRisco, $soSumido): bool {
            $porId = $sumidos->keyBy('id');

            return (bool) $porId[$emRisco->id]->deve === true
                && (bool) $porId[$soSumido->id]->deve === false;
        });

        $componente->assertSee('e está com mensalidade vencida');
    }

    public function test_aluno_sem_matricula_em_vigor_nao_entra_na_lista(): void
    {
        $this->academia->update(['dias_baixa_frequencia' => 15]);

        $encerrado = $this->aluno('Marcos Encerrado');
        $this->comMatricula($encerrado, SituacaoMatricula::Encerrada);
        $this->passou($encerrado, CarbonImmutable::now()->subDays(90));

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertViewHas('totalDeSumidos', 0)
            ->assertDontSee('Marcos Encerrado');
    }

    // -----------------------------------------------------------------
    // Aniversariantes
    // -----------------------------------------------------------------

    public function test_lista_aniversariantes_do_dia(): void
    {
        $hoje = CarbonImmutable::now();

        $aniversariante = $this->aluno('Julia Aniversariante', $hoje->subYears(25));
        $outro = $this->aluno('Tiago Outro Dia', $hoje->subYears(25)->addDays(3));
        $this->comMatricula($aniversariante);
        $this->comMatricula($outro);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Radar::class)
            ->assertSee('Julia Aniversariante')
            ->assertDontSee('Tiago Outro Dia');
    }

    // -----------------------------------------------------------------
    // Acesso
    // -----------------------------------------------------------------

    public function test_professor_nao_acessa_o_radar(): void
    {
        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('painel.inicio'))
            ->assertForbidden();
    }

    /** Gerente de uma unidade não vê o dinheiro da outra. */
    public function test_usuario_preso_a_uma_unidade_so_ve_os_numeros_dela(): void
    {
        $outraUnidade = Unidade::factory()->create([
            'academia_id' => $this->academia->id,
            'nome' => 'Filial',
        ]);

        $daqui = $this->aluno('Fabio Matriz');
        $deLa = $this->aluno('Helena Filial');

        $this->mensalidade($daqui, CarbonImmutable::now()->subDays(5)->toDateString(), 100);

        Mensalidade::factory()->create([
            'unidade_id' => $outraUnidade->id,
            'matricula_id' => Matricula::factory()->create([
                'unidade_id' => $outraUnidade->id,
                'aluno_id' => $deLa->id,
                'plano_id' => Plano::factory()->create()->id,
            ])->id,
            'aluno_id' => $deLa->id,
            'vencimento' => CarbonImmutable::now()->subDays(5)->toDateString(),
            'valor' => 999,
        ]);

        Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(Radar::class)
            ->assertViewHas('vencidas', fn (array $v) => $v['total'] === '100.00')
            ->assertSee('Fabio Matriz')
            ->assertDontSee('Helena Filial');
    }
}
