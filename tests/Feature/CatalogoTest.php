<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CatalogoTest extends TestCase
{
    public function test_catalogo_responde_fora_de_producao(): void
    {
        $resposta = $this->get('/catalogo');

        $resposta->assertOk();
        $resposta->assertSee('Catálogo do design system');
    }

    /**
     * O catálogo é ferramenta de construção, não parte do produto: deixá-lo no
     * ar exporia a estrutura de telas sem necessidade nenhuma.
     *
     * Não dá para registrar as rotas de novo com o app em produção dentro do
     * mesmo processo, então o teste verifica a guarda que produz esse efeito.
     * Se alguém a remover, isto quebra.
     */
    public function test_registro_do_catalogo_esta_protegido_por_ambiente(): void
    {
        $this->assertTrue(Route::has('catalogo'));

        $this->assertStringContainsString(
            'if (! app()->isProduction())',
            (string) file_get_contents(base_path('routes/web.php')),
            'A rota do catálogo precisa continuar dentro da guarda de ambiente.',
        );
    }

    /**
     * Alpine e Livewire só entram nas telas que precisam deles. A página
     * inicial e o login carregam um bundle enxuto — a academia pode estar em
     * conexão instável, e o painel não pode cair por causa disso.
     */
    public function test_site_publico_nao_carrega_livewire(): void
    {
        $this->get(route('site.inicio'))->assertDontSee('livewireScriptConfig', escape: false);
        $this->get(route('login'))->assertDontSee('livewireScriptConfig', escape: false);
    }

    public function test_catalogo_carrega_o_bundle_do_painel(): void
    {
        $this->get('/catalogo')->assertSee('painel', escape: false);
    }
}
