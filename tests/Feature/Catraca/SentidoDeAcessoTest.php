<?php

declare(strict_types=1);

namespace Tests\Feature\Catraca;

use App\Enums\SentidoAcesso;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\DispositivoAcesso;
use App\Services\Catraca\MotorDeAcesso;
use App\Services\Catraca\Protocolo;
use App\Services\Catraca\RegistroDePassagem;
use Carbon\CarbonImmutable;
use Tests\ContextoDeAcademia;

/**
 * Entrada e saída numa catraca que não sabe para que lado girou.
 *
 * Toda a montagem física está resumida aqui: o leitor reconhece o aluno,
 * fecha um relé por um segundo, a catraca libera o giro. Um equipamento por
 * catraca. Nem a catraca nem o leitor dizem o sentido — o protocolo confirma,
 * mandando `Status=255`, "sem estado".
 *
 * Logo, tudo o que esta suíte prova é sobre uma DEDUÇÃO. E a dedução tem que
 * ser conservadora: é melhor não saber quanto tempo o aluno ficou do que
 * afirmar um número que ninguém mediu.
 */
final class SentidoDeAcessoTest extends ContextoDeAcademia
{
    private DispositivoAcesso $aparelho;

    private MotorDeAcesso $motor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aparelho = DispositivoAcesso::factory()->create([
            'unidade_id' => $this->unidade->id,
            'numero_serie' => 'NYU7251903222',
        ]);

        $this->motor = new MotorDeAcesso($this->aparelho);
    }

    /** Uma detecção, com chave de origem única para não colidir com as outras. */
    private function detectar(Aluno $aluno, string $instante): ?Acesso
    {
        $registro = new RegistroDePassagem(
            pin: (string) $aluno->id,
            ocorreuEm: CarbonImmutable::parse($instante, config('app.timezone')),
            status: 255,
            metodo: 15,
        );

        return $this->motor->registrar($registro, Protocolo::chaveDeOrigem('NYU7251903222', $registro));
    }

    // -----------------------------------------------------------------
    // A alternância
    // -----------------------------------------------------------------

    public function test_a_primeira_passagem_do_dia_e_entrada(): void
    {
        $aluno = Aluno::factory()->create();

        $acesso = $this->detectar($aluno, '2026-08-16 07:00:00');

        $this->assertSame(SentidoAcesso::Entrada, $acesso->sentido);
        $this->assertTrue($acesso->estaDentro());
    }

    public function test_a_passagem_seguinte_dentro_da_tolerancia_e_saida(): void
    {
        $aluno = Aluno::factory()->create();

        $entrada = $this->detectar($aluno, '2026-08-16 07:00:00');
        $saida = $this->detectar($aluno, '2026-08-16 08:30:00');

        $this->assertSame(SentidoAcesso::Saida, $saida->sentido);

        $entrada->refresh();
        $this->assertFalse($entrada->estaDentro());
        $this->assertFalse($entrada->encerrada_presumida);
        $this->assertSame(90, $entrada->permanenciaEmMinutos());
    }

    public function test_depois_de_sair_a_proxima_passagem_e_entrada_de_novo(): void
    {
        $aluno = Aluno::factory()->create();

        $this->detectar($aluno, '2026-08-16 07:00:00');
        $this->detectar($aluno, '2026-08-16 08:30:00');
        $volta = $this->detectar($aluno, '2026-08-16 17:00:00');

        $this->assertSame(SentidoAcesso::Entrada, $volta->sentido);
    }

    // -----------------------------------------------------------------
    // A tolerância
    // -----------------------------------------------------------------

    /**
     * Entrada de muitas horas atrás quase certamente terminou sem registro —
     * a pessoa saiu por outra porta, ou a academia fechou. Tratar a detecção
     * de agora como "saída" marcaria uma saída oito horas depois de o aluno
     * já ter ido embora, e ainda deixaria a entrada de hoje sem existir.
     */
    public function test_entrada_antiga_demais_vira_nova_entrada_e_nao_saida(): void
    {
        config(['pulso.catraca.horas_ate_presumir_saida' => 4]);

        $aluno = Aluno::factory()->create();

        $ontem = $this->detectar($aluno, '2026-08-16 07:00:00');
        $hoje = $this->detectar($aluno, '2026-08-16 18:00:00');

        $this->assertSame(SentidoAcesso::Entrada, $hoje->sentido);

        $ontem->refresh();
        $this->assertFalse($ontem->estaDentro());
        $this->assertTrue($ontem->encerrada_presumida, 'A saída anterior foi deduzida, não registrada.');
    }

    /**
     * O ponto de honestidade da tela: quando a saída é presumida, ninguém
     * sabe a hora real. Devolver a diferença até o instante em que o sistema
     * concluiu seria inventar um número com cara de medição — e um relatório
     * de permanência montado sobre isso mentiria com confiança.
     */
    public function test_saida_presumida_nao_produz_tempo_de_permanencia(): void
    {
        $aluno = Aluno::factory()->create();

        $entrada = $this->detectar($aluno, '2026-08-16 07:00:00');
        $this->detectar($aluno, '2026-08-16 18:00:00');

        $this->assertNull($entrada->refresh()->permanenciaEmMinutos());
    }

    public function test_o_limite_da_tolerancia_e_respeitado(): void
    {
        config(['pulso.catraca.horas_ate_presumir_saida' => 4]);

        $aluno = Aluno::factory()->create();

        $this->detectar($aluno, '2026-08-16 07:00:00');
        // Três horas e meia: ainda dentro, então é saída.
        $saida = $this->detectar($aluno, '2026-08-16 10:30:00');

        $this->assertSame(SentidoAcesso::Saida, $saida->sentido);

        $outro = Aluno::factory()->create();
        $this->detectar($outro, '2026-08-16 07:00:00');
        // Quatro horas e meia: passou, então é entrada nova.
        $novaEntrada = $this->detectar($outro, '2026-08-16 11:30:00');

        $this->assertSame(SentidoAcesso::Entrada, $novaEntrada->sentido);
    }

    // -----------------------------------------------------------------
    // O repique
    // -----------------------------------------------------------------

    /**
     * O caso que mais aparece na prática: a catraca demora a destravar, a
     * pessoa mostra o rosto de novo, e o leitor detecta duas vezes. Sem esta
     * guarda, a segunda detecção viraria uma saída — e o aluno "sairia" no
     * mesmo minuto em que entrou.
     */
    public function test_duas_deteccoes_seguidas_sao_a_mesma_passagem(): void
    {
        config(['pulso.catraca.janela_de_repique' => 45]);

        $aluno = Aluno::factory()->create();

        $entrada = $this->detectar($aluno, '2026-08-16 07:00:00');
        $repique = $this->detectar($aluno, '2026-08-16 07:00:20');

        $this->assertNull($repique, 'O repique não pode virar uma segunda passagem.');
        $this->assertSame(1, Acesso::query()->where('aluno_id', $aluno->id)->count());
        $this->assertTrue($entrada->refresh()->estaDentro());
    }

    public function test_passada_a_janela_a_deteccao_conta_de_novo(): void
    {
        config(['pulso.catraca.janela_de_repique' => 45]);

        $aluno = Aluno::factory()->create();

        $this->detectar($aluno, '2026-08-16 07:00:00');
        $saida = $this->detectar($aluno, '2026-08-16 07:02:00');

        $this->assertNotNull($saida);
        $this->assertSame(SentidoAcesso::Saida, $saida->sentido);
    }

    // -----------------------------------------------------------------
    // Fechamento do dia
    // -----------------------------------------------------------------

    /**
     * Sem esta rotina, quem esqueceu de passar na saída fica presente para
     * sempre — e "quem está na academia agora", o número que a recepção usa
     * para saber se pode fechar, vira ficção.
     */
    public function test_a_rotina_noturna_fecha_entradas_abandonadas(): void
    {
        $esquecido = Aluno::factory()->create();
        $recente = Aluno::factory()->create();

        $antiga = $this->detectar($esquecido, CarbonImmutable::now()->subHours(10)->format('Y-m-d H:i:s'));
        $nova = $this->detectar($recente, CarbonImmutable::now()->subMinutes(30)->format('Y-m-d H:i:s'));

        $fechadas = MotorDeAcesso::encerrarEntradasAbandonadas();

        $this->assertSame(1, $fechadas);
        $this->assertFalse($antiga->refresh()->estaDentro());
        $this->assertTrue($antiga->encerrada_presumida);
        $this->assertTrue($nova->refresh()->estaDentro(), 'Quem entrou há pouco continua dentro.');
    }

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    public function test_quem_esta_na_academia_agora(): void
    {
        $dentro = Aluno::factory()->create(['nome' => 'Paulo Presente']);
        $foi = Aluno::factory()->create(['nome' => 'Rita Saiu']);

        $this->detectar($dentro, '2026-08-16 07:00:00');
        $this->detectar($foi, '2026-08-16 07:05:00');
        $this->detectar($foi, '2026-08-16 08:05:00');

        $presentes = Acesso::query()->presentes()->pluck('aluno_id');

        $this->assertTrue($presentes->contains($dentro->id));
        $this->assertFalse($presentes->contains($foi->id));
    }

    /**
     * Para o Radar, treino é ENTRADA. Contar a saída também dobraria a
     * frequência de todo mundo que registra as duas pontas.
     */
    public function test_frequencia_conta_entrada_e_nao_saida(): void
    {
        $aluno = Aluno::factory()->create();

        $this->detectar($aluno, '2026-08-16 07:00:00');
        $this->detectar($aluno, '2026-08-16 08:00:00');

        $this->assertSame(2, Acesso::query()->where('aluno_id', $aluno->id)->count());
        $this->assertSame(1, Acesso::query()->where('aluno_id', $aluno->id)->entradas()->count());
    }
}
