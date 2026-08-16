<?php

declare(strict_types=1);

namespace Tests\Feature\Mensalidades;

use App\Enums\SituacaoMensalidade;
use App\Livewire\Mensalidades\Detalhes;
use App\Livewire\Mensalidades\Lista;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class RecebimentoTest extends ContextoDeAcademia
{
    /** @param array<string, mixed> $atributos */
    private function mensalidade(array $atributos = []): Mensalidade
    {
        $aluno = Aluno::factory()->create();

        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create()->id,
        ]);

        return Mensalidade::factory()->create([
            'unidade_id' => $this->unidade->id,
            'matricula_id' => $matricula->id,
            'aluno_id' => $aluno->id,
            'valor' => 129.90,
            ...$atributos,
        ]);
    }

    // -----------------------------------------------------------------
    // Recebimento
    // -----------------------------------------------------------------

    public function test_recebe_o_valor_inteiro_e_quita(): void
    {
        $mensalidade = $this->mensalidade();

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '129,90')
            ->set('forma', 'dinheiro')
            ->call('registrar')
            ->assertHasNoErrors();

        $mensalidade->refresh();

        $this->assertSame(SituacaoMensalidade::Paga, $mensalidade->situacao);
        $this->assertNotNull($mensalidade->paga_em);
        $this->assertSame('0.00', $mensalidade->valorEmAberto());
    }

    /** Metade em dinheiro e metade no Pix é rotina de balcão. */
    public function test_aceita_pagamento_parcial_e_so_quita_ao_completar(): void
    {
        $mensalidade = $this->mensalidade();
        $usuario = $this->usuarioCom('recepcao');

        Livewire::actingAs($usuario)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '60,00')
            ->set('forma', 'dinheiro')
            ->call('registrar');

        $mensalidade->refresh();
        $this->assertSame(SituacaoMensalidade::Aberta, $mensalidade->situacao);
        $this->assertSame('69.90', $mensalidade->valorEmAberto());

        Livewire::actingAs($usuario)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '69,90')
            ->set('forma', 'pix')
            ->call('registrar');

        $this->assertSame(SituacaoMensalidade::Paga, $mensalidade->fresh()->situacao);
    }

    /** Receber mais do que se deve é erro de digitação, não generosidade. */
    public function test_nao_recebe_mais_do_que_o_devido(): void
    {
        $mensalidade = $this->mensalidade();

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '500,00')
            ->call('registrar')
            ->assertHasErrors('valor');

        $this->assertSame(SituacaoMensalidade::Aberta, $mensalidade->fresh()->situacao);
    }

    public function test_valor_zero_e_recusado(): void
    {
        $mensalidade = $this->mensalidade();

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '0,00')
            ->call('registrar')
            ->assertHasErrors('valor');
    }

    /** O campo já vem com o que falta: o caso comum é pagar tudo de uma vez. */
    public function test_sugere_o_valor_em_aberto(): void
    {
        $mensalidade = $this->mensalidade(['valor' => 200, 'desconto' => 50]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->assertSet('valor', '150,00');
    }

    public function test_registra_quem_recebeu(): void
    {
        $mensalidade = $this->mensalidade();
        $usuario = $this->usuarioCom('recepcao');

        Livewire::actingAs($usuario)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '129,90')
            ->call('registrar');

        $this->assertSame($usuario->id, $mensalidade->pagamentos()->first()->registrado_por);
    }

    // -----------------------------------------------------------------
    // Estorno
    // -----------------------------------------------------------------

    /**
     * O estorno NÃO apaga o pagamento: marca a data. Apagar dinheiro que
     * entrou e depois voltou destrói a conciliação com o extrato.
     */
    public function test_estorno_preserva_o_pagamento_e_reabre_a_mensalidade(): void
    {
        $mensalidade = $this->mensalidade();
        $gerente = $this->usuarioCom('gerente');

        Livewire::actingAs($gerente)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '129,90')
            ->call('registrar');

        $pagamento = $mensalidade->fresh()->pagamentos()->first();

        Livewire::actingAs($gerente)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade->fresh()])
            ->call('estornar', $pagamento->id);

        $mensalidade->refresh();
        $pagamento->refresh();

        $this->assertNotNull($pagamento->estornado_em, 'O pagamento precisa continuar existindo.');
        $this->assertSame(SituacaoMensalidade::Aberta, $mensalidade->situacao);
        $this->assertNull($mensalidade->paga_em);
        $this->assertSame('129.90', $mensalidade->valorEmAberto());
    }

    /** Estornar faz dinheiro sumir do caixa: fica com gerente e dono. */
    public function test_recepcao_nao_estorna(): void
    {
        $mensalidade = $this->mensalidade();
        $recepcao = $this->usuarioCom('recepcao');

        Livewire::actingAs($recepcao)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade])
            ->set('valor', '129,90')
            ->call('registrar');

        $pagamento = $mensalidade->fresh()->pagamentos()->first();

        Livewire::actingAs($recepcao)
            ->test(Detalhes::class, ['mensalidade' => $mensalidade->fresh()])
            ->call('estornar', $pagamento->id)
            ->assertForbidden();

        $this->assertNull($pagamento->fresh()->estornado_em);
    }

    // -----------------------------------------------------------------
    // Lista
    // -----------------------------------------------------------------

    /**
     * "Vencida" não é situação no banco: é `aberta` com vencimento no
     * passado. É por isso que o número nunca depende de uma rotina ter
     * virado uma chave de madrugada.
     */
    public function test_vencida_e_derivada_do_vencimento(): void
    {
        $vencida = $this->mensalidade(['vencimento' => CarbonImmutable::now()->subDays(10)->toDateString()]);
        $aVencer = $this->mensalidade(['vencimento' => CarbonImmutable::now()->addDays(10)->toDateString()]);

        $this->assertTrue($vencida->estaVencida());
        $this->assertFalse($aVencer->estaVencida());
        // Nenhuma das duas tem situação diferente no banco.
        $this->assertSame(SituacaoMensalidade::Aberta, $vencida->situacao);
        $this->assertSame(SituacaoMensalidade::Aberta, $aVencer->situacao);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('filtro', 'vencidas')
            ->assertSee($vencida->aluno->nome)
            ->assertDontSee($aVencer->aluno->nome);
    }

    public function test_totais_somam_apenas_o_que_esta_em_aberto(): void
    {
        $this->mensalidade(['vencimento' => CarbonImmutable::now()->subDays(10)->toDateString()]);
        $this->mensalidade(['vencimento' => CarbonImmutable::now()->subDays(5)->toDateString()]);
        $this->mensalidade()->update(['situacao' => SituacaoMensalidade::Paga]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            // Duas vencidas de R$ 129,90.
            ->assertSee('R$ 259,80');
    }

    public function test_professor_nao_acessa_mensalidades(): void
    {
        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('mensalidades.lista'))
            ->assertForbidden();
    }
}
