<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use Tests\TestCase;

final class TelaDeLoginTest extends TestCase
{
    public function test_tela_de_login_responde(): void
    {
        $resposta = $this->get(route('login'));

        $resposta->assertOk();
        $resposta->assertSee('Entrar no Pulso');
        $resposta->assertSee('Esqueci a senha');
    }

    public function test_atalho_em_portugues_leva_ao_login(): void
    {
        $this->get('/entrar')->assertRedirect('/login');
    }

    public function test_tela_de_recuperacao_de_senha_responde(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    /**
     * Nao existe auto-cadastro: quem cria acesso e a equipe do Pulso ou o
     * gestor da academia. A rota do Fortify precisa continuar fora do ar.
     */
    public function test_nao_existe_rota_de_auto_cadastro(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_painel_exige_autenticacao(): void
    {
        $this->get(route('painel.inicio'))->assertRedirect(route('login'));
    }
}
