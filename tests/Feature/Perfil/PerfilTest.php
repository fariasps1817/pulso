<?php

declare(strict_types=1);

namespace Tests\Feature\Perfil;

use App\Livewire\Perfil\Dados;
use App\Livewire\Perfil\Preferencias;
use App\Models\Unidade;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * "Meu perfil" e "Preferências".
 *
 * Os dois itens existiam no menu apontando para `href="#"` — apareciam, e não
 * levavam a lugar nenhum.
 *
 * A divisão é de propósito: aqui a pessoa cuida de si; na tela de usuários o
 * gestor administra a equipe. Papel, unidade e sessão única continuam sendo da
 * gerência — se a pessoa pudesse mudar o próprio papel, a hierarquia inteira
 * seria decorativa.
 */
final class PerfilTest extends ContextoDeAcademia
{
    public function test_os_itens_do_menu_levam_as_telas(): void
    {
        $usuario = $this->usuarioCom('recepcao');

        $html = $this->actingAs($usuario)->get(route('painel.inicio'))->assertOk()->getContent();

        $this->assertStringContainsString(route('perfil.dados'), $html);
        $this->assertStringContainsString(route('perfil.preferencias'), $html);

        $this->actingAs($usuario)->get(route('perfil.dados'))->assertOk();
        $this->actingAs($usuario)->get(route('perfil.preferencias'))->assertOk();
    }

    // -----------------------------------------------------------------
    // Dados e senha
    // -----------------------------------------------------------------

    public function test_a_pessoa_muda_o_proprio_nome_e_e_mail(): void
    {
        $usuario = $this->usuarioCom('recepcao');

        Livewire::actingAs($usuario)
            ->test(Dados::class)
            ->set('name', 'Patricia Gomes Lima')
            ->set('email', 'Patricia@Alpha-Fit.com.BR')
            ->call('salvarDados')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertSame('Patricia Gomes Lima', $usuario->name);
        // Guardado em minúsculas: é com ele que se entra.
        $this->assertSame('patricia@alpha-fit.com.br', $usuario->email);
    }

    public function test_troca_a_propria_senha(): void
    {
        $usuario = $this->usuarioCom('recepcao', ['password' => 'SenhaAntiga123']);

        Livewire::actingAs($usuario)
            ->test(Dados::class)
            ->set('senha_atual', 'SenhaAntiga123')
            ->set('senha', 'MinhaSenhaNova4712')
            ->set('senha_confirmation', 'MinhaSenhaNova4712')
            ->call('salvarSenha')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('MinhaSenhaNova4712', $usuario->fresh()->password));
    }

    /**
     * A senha atual é exigida mesmo com a sessão aberta: é o que protege a
     * conta num computador de balcão deixado destravado.
     */
    public function test_sem_a_senha_atual_nao_troca(): void
    {
        $usuario = $this->usuarioCom('recepcao', ['password' => 'SenhaAntiga123']);

        Livewire::actingAs($usuario)
            ->test(Dados::class)
            ->set('senha_atual', 'chute-errado')
            ->set('senha', 'MinhaSenhaNova4712')
            ->set('senha_confirmation', 'MinhaSenhaNova4712')
            ->call('salvarSenha')
            ->assertHasErrors('senha_atual');

        $this->assertTrue(Hash::check('SenhaAntiga123', $usuario->fresh()->password));
    }

    // -----------------------------------------------------------------
    // Preferências
    // -----------------------------------------------------------------

    public function test_as_preferencias_ficam_no_perfil_e_nao_no_navegador(): void
    {
        $usuario = $this->usuarioCom('recepcao');

        Livewire::actingAs($usuario)
            ->test(Preferencias::class)
            ->set('tema', 'escuro')
            ->set('itens_por_pagina', '50')
            ->call('salvar')
            ->assertHasNoErrors();

        $preferencias = $usuario->fresh()->preferencias;

        $this->assertSame('escuro', $preferencias['tema']);
        $this->assertSame(50, $preferencias['itens_por_pagina']);
    }

    /**
     * Quem pode alternar escolhe a unidade que abre por padrão — e só entre
     * as que alcança. Sem essa checagem, bastaria trocar o número no
     * formulário para abrir a filial que a gerência travou.
     */
    public function test_a_unidade_padrao_fica_presa_ao_que_a_pessoa_alcanca(): void
    {
        $filial = Unidade::factory()->create(['academia_id' => $this->academia->id, 'nome' => 'Filial']);

        $gerente = $this->usuarioCom('gerente');
        $gerente->unidades()->syncWithoutDetaching([$filial->id]);

        Livewire::actingAs($gerente->fresh())
            ->test(Preferencias::class)
            ->set('unidade_padrao_id', $filial->id)
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame($filial->id, $gerente->fresh()->unidade_padrao_id);
    }

    public function test_nao_aceita_unidade_fora_do_alcance(): void
    {
        $trancada = Unidade::factory()->create(['academia_id' => $this->academia->id, 'nome' => 'Trancada']);

        $recepcao = $this->usuarioCom('recepcao');

        Livewire::actingAs($recepcao)
            ->test(Preferencias::class)
            ->set('unidade_padrao_id', $trancada->id)
            ->call('salvar')
            ->assertHasErrors('unidade_padrao_id');

        $this->assertNotSame($trancada->id, $recepcao->fresh()->unidade_padrao_id);
    }
}
