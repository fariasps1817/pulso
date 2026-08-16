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
 * O "Sair" do menu do usuário.
 *
 * O botão existia, estava visível e não fazia nada: a propriedade do
 * componente é `tipo`, mas foi escrita como `type`. O valor caiu nos atributos
 * extras, o botão saiu com dois `type`, e o navegador usou o primeiro
 * (`button`). Nenhum erro, nenhum aviso — só um botão inerte.
 *
 * Um teste que olhasse apenas "existe um link de logout na página" teria
 * passado. Por isso estes verificam o BOTÃO em si e o efeito de sair.
 */
final class SairTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $academia = Academia::factory()->create();
        app(ContextoAcademia::class)->definir($academia->id);
        setPermissionsTeamId($academia->id);

        Unidade::factory()->create(['academia_id' => $academia->id]);

        $usuario = User::factory()->daAcademia($academia->id)->create(
            PadroesDeAcesso::paraPapel('dono'),
        );
        $usuario->assignRole('dono');

        return $usuario;
    }

    /**
     * O botão precisa ser submit E não pode ter um segundo type na marcação —
     * é a combinação exata que quebrava.
     */
    public function test_o_botao_sair_envia_o_formulario(): void
    {
        $html = $this->actingAs($this->usuario())->get(route('painel.inicio'))->getContent();

        // Só a tag de abertura do botão: o campo oculto do CSRF também tem um
        // `type`, e contá-lo mascararia o defeito.
        $formulario = str($html)->after(route('logout'))->before('</form>')->toString();
        $botao = str($formulario)->after('<button')->before('>')->toString();

        $this->assertStringContainsString('type="submit"', $botao);
        $this->assertSame(
            1,
            substr_count($botao, 'type='),
            'O botão saiu com mais de um atributo type: o navegador usa o primeiro e o formulário não é enviado.',
        );
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_depois_de_sair_o_painel_pede_login(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('logout'));

        $this->get(route('painel.inicio'))->assertRedirect(route('login'));
    }
}
