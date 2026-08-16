<?php

declare(strict_types=1);

namespace Tests\Feature\Interface;

use App\Models\User;
use Tests\ContextoDeAcademia;

/**
 * Toda tela com Livewire tem que trazer o pacote `painel.js`.
 *
 * O ERRO QUE ESTE CASO EXISTE PARA PEGAR
 *
 * Esquecer `com-livewire` no layout não quebra nada visivelmente: o Livewire
 * se injeta sozinho, a página abre, os botões respondem. O que não sobe são os
 * nossos plugins do Alpine, que moram em `painel.js` — então o aviso de
 * sucesso nunca aparece, a máscara de CNPJ não formata, e a única pista é uma
 * linha no console do navegador.
 *
 * Aconteceu exatamente assim na área da administração: descoberto por quem
 * abriu o console, não por teste. Este caso fecha essa porta para os layouts
 * que existem hoje e para os próximos.
 */
final class PacoteDeScriptsTest extends ContextoDeAcademia
{
    /**
     * O nome do arquivo vem do ponto de entrada do Vite, então o prefixo é
     * estável mesmo com o hash mudando a cada build.
     */
    private const PACOTE = '/build/assets/painel-';

    public function test_o_painel_da_academia_carrega_o_pacote(): void
    {
        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('painel.inicio'))
            ->assertOk()
            ->assertSee(self::PACOTE, escape: false);
    }

    /** Foi esta que faltou. */
    public function test_a_administracao_do_saas_carrega_o_pacote(): void
    {
        $superAdministrador = User::factory()->superAdministrador()->create();

        $this->actingAs($superAdministrador)
            ->get(route('administracao.academias.lista'))
            ->assertOk()
            ->assertSee(self::PACOTE, escape: false);
    }

    public function test_a_troca_de_senha_carrega_o_pacote(): void
    {
        $usuario = $this->usuarioCom('recepcao', ['deve_trocar_senha' => true]);

        $this->actingAs($usuario)
            ->get(route('senha.trocar'))
            ->assertOk()
            ->assertSee(self::PACOTE, escape: false);
    }

    /**
     * A recíproca também importa: o site institucional NÃO carrega o pacote do
     * painel. São 270 KB de Livewire e Alpine numa página que só precisa de um
     * menu — e é a primeira impressão de quem chega pelo celular.
     */
    public function test_o_site_publico_fica_leve(): void
    {
        $this->get(route('site.inicio'))
            ->assertOk()
            ->assertDontSee(self::PACOTE, escape: false);
    }
}
