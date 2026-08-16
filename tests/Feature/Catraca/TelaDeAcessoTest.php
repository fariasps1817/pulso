<?php

declare(strict_types=1);

namespace Tests\Feature\Catraca;

use App\Enums\SentidoAcesso;
use App\Livewire\Acesso\Painel;
use App\Livewire\Acesso\Simulador;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\DispositivoAcesso;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class TelaDeAcessoTest extends ContextoDeAcademia
{
    private DispositivoAcesso $aparelho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aparelho = DispositivoAcesso::factory()->create([
            'unidade_id' => $this->unidade->id,
            'nome' => 'Catraca da entrada',
            'numero_serie' => 'NYU7251903222',
        ]);
    }

    // -----------------------------------------------------------------
    // A tela
    // -----------------------------------------------------------------

    public function test_mostra_quem_esta_dentro_e_quem_ja_saiu(): void
    {
        $dentro = Aluno::factory()->create(['nome' => 'Paulo Treinando']);
        $saiu = Aluno::factory()->create(['nome' => 'Rita Terminou']);

        Acesso::create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $dentro->id,
            'ocorreu_em' => now()->subHour(),
            'sentido' => SentidoAcesso::Entrada,
            'resultado' => 'liberado',
        ]);

        Acesso::create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $saiu->id,
            'ocorreu_em' => now()->subHours(2),
            'encerrada_em' => now()->subHour(),
            'sentido' => SentidoAcesso::Entrada,
            'resultado' => 'liberado',
        ]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Painel::class)
            ->assertViewHas('presentes', fn ($presentes) => $presentes->count() === 1)
            ->assertSee('Paulo Treinando');
    }

    /**
     * O aparelho mudo é o pior problema deste módulo: não dá erro em lugar
     * nenhum. A catraca continua girando, e o Pulso só para de saber.
     */
    public function test_avisa_quando_o_aparelho_nunca_conectou(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Painel::class)
            ->assertSee('Catraca da entrada')
            ->assertSee('Nunca conectou');
    }

    /** Professor acompanha frequência, mas não mexe em equipamento. */
    public function test_professor_ve_o_movimento_e_nao_os_aparelhos(): void
    {
        Livewire::actingAs($this->usuarioCom('professor'))
            ->test(Painel::class)
            ->assertOk()
            ->assertViewHas('aparelhos', null)
            ->assertDontSee('Catraca da entrada');
    }

    // -----------------------------------------------------------------
    // O simulador
    // -----------------------------------------------------------------

    /**
     * O simulador vale porque atravessa o caminho inteiro: monta a linha do
     * protocolo, entrega no endpoint, passa pelo middleware que identifica o
     * aparelho e chega ao motor. Um botão que gravasse direto na tabela não
     * provaria nada disso.
     */
    public function test_o_simulador_registra_pelo_caminho_real_do_protocolo(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Bruno Simulado']);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Simulador::class)
            ->set('dispositivoId', $this->aparelho->id)
            ->set('alunoId', $aluno->id)
            ->call('detectar')
            ->assertSee('Registrada como Entrada');

        $acesso = Acesso::query()->firstOrFail();

        $this->assertSame($aluno->id, $acesso->aluno_id);
        $this->assertSame($this->aparelho->id, $acesso->dispositivo_id);
        // A chave de origem só existe se a passagem veio pelo protocolo.
        $this->assertNotNull($acesso->chave_origem);
    }

    /** A alternância, vista de fora: a segunda detecção fecha a entrada. */
    public function test_a_segunda_deteccao_no_simulador_e_saida(): void
    {
        $aluno = Aluno::factory()->create();

        $componente = Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Simulador::class)
            ->set('dispositivoId', $this->aparelho->id)
            ->set('alunoId', $aluno->id)
            ->call('detectar');

        // Fora da janela de repique, senão a detecção seria descartada.
        config(['pulso.catraca.janela_de_repique' => 0]);

        /*
         * E num segundo diferente. A chave de idempotência é derivada do
         * instante, então duas detecções no MESMO segundo são, para o
         * protocolo, o mesmo lote reenviado — o risco residual assumido lá.
         */
        $this->travel(2)->seconds();

        $componente->call('detectar')->assertSee('Registrada como Saída');

        $this->assertSame(0, Acesso::query()->presentes()->count());
    }

    /**
     * O botão que existe justamente para provar a idempotência: é o que
     * acontece de verdade quando a rede oscila e o aparelho reenvia o lote.
     */
    public function test_o_reenvio_do_lote_nao_duplica_a_passagem(): void
    {
        $aluno = Aluno::factory()->create();

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Simulador::class)
            ->set('dispositivoId', $this->aparelho->id)
            ->set('alunoId', $aluno->id)
            ->call('detectar')
            ->call('reenviarUltimoLote')
            ->assertSee('Nenhuma passagem duplicada');

        $this->assertSame(1, Acesso::query()->count());
    }

    public function test_o_handshake_devolve_as_opcoes_do_aparelho(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Simulador::class)
            ->set('dispositivoId', $this->aparelho->id)
            ->call('handshake')
            ->assertSee('GET OPTION FROM: NYU7251903222')
            ->assertSee('Realtime=1');
    }

    /** Matrícula que não existe no Pulso vira passagem anônima — é a pista. */
    public function test_matricula_avulsa_vira_passagem_nao_reconhecida(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Simulador::class)
            ->set('dispositivoId', $this->aparelho->id)
            ->set('pinAvulso', '987654')
            ->call('detectar');

        $acesso = Acesso::query()->firstOrFail();

        $this->assertNull($acesso->aluno_id);
        $this->assertSame('987654', $acesso->pin);
    }

    public function test_recepcao_nao_abre_o_simulador(): void
    {
        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('acesso.simulador'))
            ->assertForbidden();
    }
}
