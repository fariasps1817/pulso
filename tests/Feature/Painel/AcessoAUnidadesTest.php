<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Models\Academia;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\ContextoAcademia;
use App\Support\Academia\PadroesDeAcesso;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Acesso a unidades: declarado, nunca inferido.
 *
 * O modelo anterior deduzia o alcance do usuário da ausência de vínculo — sem
 * unidade vinculada, enxergava todas. Falhava ABRINDO, ao contrário do resto
 * do sistema. Estes casos garantem que não volte a falhar assim.
 */
final class AcessoAUnidadesTest extends TestCase
{
    use DatabaseTransactions;

    private Academia $academia;

    private Unidade $matriz;

    private Unidade $filial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academia = Academia::factory()->create(['nome' => 'Fit B']);
        app(ContextoAcademia::class)->definir($this->academia->id);
        setPermissionsTeamId($this->academia->id);

        $this->matriz = Unidade::factory()->create([
            'academia_id' => $this->academia->id,
            'nome' => 'Landida',
        ]);

        $this->filial = Unidade::factory()->create([
            'academia_id' => $this->academia->id,
            'nome' => 'Geraldina',
        ]);
    }

    /** @param array<string, mixed> $atributos */
    private function usuario(array $atributos = [], string $papel = 'recepcao'): User
    {
        $usuario = User::factory()->daAcademia($this->academia->id)->create($atributos);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * O caso que motivou a mudança: cadastro sem vincular unidade não pode
     * liberar a rede inteira.
     */
    public function test_usuario_sem_vinculo_nao_enxerga_nenhuma_unidade(): void
    {
        $usuario = $this->usuario();

        $this->assertCount(0, $usuario->unidadesAcessiveis());
        $this->assertNull($usuario->unidadeAtual());
    }

    public function test_tela_avisa_quando_o_usuario_nao_tem_unidade(): void
    {
        $resposta = $this->actingAs($this->usuario())->get(route('painel.inicio'));

        $resposta->assertOk();
        $resposta->assertSee('Sem unidade vinculada');
    }

    public function test_usuario_com_acesso_total_enxerga_todas(): void
    {
        $usuario = $this->usuario(PadroesDeAcesso::paraPapel('dono'), 'dono');

        $this->assertCount(2, $usuario->unidadesAcessiveis());
    }

    public function test_usuario_vinculado_enxerga_so_o_que_foi_vinculado(): void
    {
        $usuario = $this->usuario(['unidade_padrao_id' => $this->matriz->id]);
        $usuario->unidades()->attach($this->matriz->id);

        $acessiveis = $usuario->unidadesAcessiveis();

        $this->assertCount(1, $acessiveis);
        $this->assertTrue($acessiveis->first()->is($this->matriz));
    }

    /**
     * Sem permissão de alternar, o seletor é TEXTO — não lista desabilitada.
     * Controle desligado na tela anuncia algo que a pessoa não pode fazer, e
     * isso só gera pergunta para o gerente.
     */
    public function test_sem_permissao_de_alternar_nao_aparece_seletor(): void
    {
        $usuario = $this->usuario([
            'acessa_todas_unidades' => true,
            'pode_alternar_unidade' => false,
            'unidade_padrao_id' => $this->matriz->id,
        ]);

        $this->assertFalse($usuario->podeTrocarDeUnidade());

        $resposta = $this->actingAs($usuario)->get(route('painel.inicio'));

        $resposta->assertSee('Landida');
        $resposta->assertDontSee('Trocar de unidade');
    }

    public function test_com_permissao_o_seletor_aparece(): void
    {
        $usuario = $this->usuario(PadroesDeAcesso::paraPapel('dono'), 'dono');

        $this->assertTrue($usuario->podeTrocarDeUnidade());

        $resposta = $this->actingAs($usuario)->get(route('painel.inicio'));

        $resposta->assertSee('Trocar de unidade');
        $resposta->assertSee('Geraldina');
    }

    /**
     * O seletor visual não é controle nenhum se o servidor aceitar qualquer
     * número que chegue no formulário.
     */
    public function test_nao_e_possivel_trocar_para_unidade_sem_acesso(): void
    {
        $usuario = $this->usuario([
            'pode_alternar_unidade' => true,
            'unidade_padrao_id' => $this->matriz->id,
        ]);
        $usuario->unidades()->attach($this->matriz->id);

        $this->actingAs($usuario)
            ->from(route('painel.inicio'))
            ->post(route('painel.trocar-unidade'), ['unidade_id' => $this->filial->id])
            ->assertSessionHasErrors('unidade_id');

        $this->assertTrue($usuario->fresh()->unidadeAtual()->is($this->matriz));
    }

    public function test_usuario_travado_nao_consegue_trocar_nem_pelo_formulario(): void
    {
        $usuario = $this->usuario([
            'acessa_todas_unidades' => true,
            'pode_alternar_unidade' => false,
            'unidade_padrao_id' => $this->matriz->id,
        ]);

        $this->actingAs($usuario)
            ->from(route('painel.inicio'))
            ->post(route('painel.trocar-unidade'), ['unidade_id' => $this->filial->id])
            ->assertSessionHasErrors('unidade_id');
    }

    public function test_troca_valida_e_gravada_na_preferencia(): void
    {
        $usuario = $this->usuario(PadroesDeAcesso::paraPapel('dono'), 'dono');

        $this->actingAs($usuario)
            ->from(route('painel.inicio'))
            ->post(route('painel.trocar-unidade'), ['unidade_id' => $this->filial->id])
            ->assertSessionHasNoErrors();

        $this->assertTrue($usuario->fresh()->unidadeAtual()->is($this->filial));
    }

    /**
     * Preferência forjada não escapa do travamento: quem não pode alternar
     * abre sempre na unidade padrão.
     */
    public function test_preferencia_nao_vence_o_travamento(): void
    {
        $usuario = $this->usuario([
            'acessa_todas_unidades' => true,
            'pode_alternar_unidade' => false,
            'unidade_padrao_id' => $this->matriz->id,
            'preferencias' => ['unidade_id' => $this->filial->id],
        ]);

        $this->assertTrue($usuario->unidadeAtual()->is($this->matriz));
    }

    public function test_padroes_por_papel(): void
    {
        $this->assertSame(
            ['acessa_todas_unidades' => true, 'pode_alternar_unidade' => true],
            PadroesDeAcesso::paraPapel('dono'),
        );

        $this->assertSame(
            ['acessa_todas_unidades' => false, 'pode_alternar_unidade' => true],
            PadroesDeAcesso::paraPapel('gerente'),
        );

        // Recepção e professor nascem travados.
        foreach (['recepcao', 'professor', null] as $papel) {
            $this->assertSame(
                ['acessa_todas_unidades' => false, 'pode_alternar_unidade' => false],
                PadroesDeAcesso::paraPapel($papel),
            );
        }
    }
}
