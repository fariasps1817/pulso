<?php

declare(strict_types=1);

namespace Tests\Feature\Painel;

use App\Models\Academia;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CabecalhoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuarioDe(Academia $academia, string $nome = 'Jose Maria da Silva'): User
    {
        app(ContextoAcademia::class)->definir($academia->id);
        setPermissionsTeamId($academia->id);

        $usuario = User::factory()->daAcademia($academia->id)->create(['name' => $nome]);
        $usuario->assignRole('dono');

        return $usuario;
    }

    public function test_mostra_nome_curto_e_papel_do_usuario(): void
    {
        $academia = Academia::factory()->create(['nome' => 'Fit A']);
        Unidade::factory()->create(['academia_id' => $academia->id, 'nome' => 'Matriz']);

        $resposta = $this->actingAs($this->usuarioDe($academia))->get(route('painel.inicio'));

        $resposta->assertOk();
        // Nome curto no cabeçalho, nome completo dentro do menu.
        $resposta->assertSee('Jose Maria');
        $resposta->assertSee('Jose Maria da Silva');
        $resposta->assertSee('Dono');
        // Iniciais no avatar.
        $resposta->assertSee('JM');
    }

    /**
     * Academia de uma unidade só não vê a palavra "unidade": é jargão do
     * sistema vazando para quem tem uma loja e não pediu por isso.
     */
    public function test_academia_com_uma_unidade_mostra_so_o_nome(): void
    {
        $academia = Academia::factory()->create(['nome' => 'Fit A']);
        Unidade::factory()->create(['academia_id' => $academia->id, 'nome' => 'Matriz']);

        $resposta = $this->actingAs($this->usuarioDe($academia))->get(route('painel.inicio'));

        $resposta->assertSee('Fit A');
        $resposta->assertDontSee('Fit A</span>
                        <span class="text-texto-mudo">·', escape: false);
    }

    public function test_academia_com_filiais_mostra_academia_e_unidade(): void
    {
        $academia = Academia::factory()->create(['nome' => 'Fit B']);
        Unidade::factory()->create(['academia_id' => $academia->id, 'nome' => 'Landida']);
        Unidade::factory()->create(['academia_id' => $academia->id, 'nome' => 'Geraldina']);

        $resposta = $this->actingAs($this->usuarioDe($academia))->get(route('painel.inicio'));

        $resposta->assertSee('Fit B');
        // A primeira cadastrada abre por padrão — a principal, não a alfabética.
        $resposta->assertSee('Landida');
        // As duas aparecem no seletor.
        $resposta->assertSee('Geraldina');
    }

    /**
     * Três estados de tema exigem três ícones. Com "sistema" e "claro"
     * dividindo o sol, clicar com o sistema já no claro parecia não funcionar.
     */
    public function test_alternador_de_tema_tem_tres_icones_distintos(): void
    {
        $academia = Academia::factory()->create();
        Unidade::factory()->create(['academia_id' => $academia->id]);

        $resposta = $this->actingAs($this->usuarioDe($academia))->get(route('painel.inicio'));

        $resposta->assertSee('icone-sistema');
        $resposta->assertSee('icone-claro');
        $resposta->assertSee('icone-escuro');
    }

    public function test_sair_vive_dentro_do_menu_do_usuario(): void
    {
        $academia = Academia::factory()->create();
        Unidade::factory()->create(['academia_id' => $academia->id]);

        $resposta = $this->actingAs($this->usuarioDe($academia))->get(route('painel.inicio'));

        $resposta->assertSee('Sair');
        $resposta->assertSee(route('logout'), escape: false);
    }
}
