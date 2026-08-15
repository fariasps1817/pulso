<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Tests\TestCase;

final class PaginaInicialTest extends TestCase
{
    public function test_pagina_inicial_responde_e_traz_a_marca(): void
    {
        $resposta = $this->get(route('site.inicio'));

        $resposta->assertOk();
        $resposta->assertSee(config('pulso.slogans.principal'));
        $resposta->assertSee('gestão de academias');
    }

    public function test_pagina_inicial_publica_os_contatos_configurados(): void
    {
        $resposta = $this->get(route('site.inicio'));

        $resposta->assertSee(config('pulso.contato.email'));
        $resposta->assertSee('wa.me/'.config('pulso.contato.whatsapp'), escape: false);
        $resposta->assertSee(config('pulso.contato.cidade'));
    }

    /**
     * A regra do CDC art. 42 aparece na propria pagina de venda porque e um
     * argumento do produto, nao letra miuda.
     */
    public function test_pagina_inicial_declara_que_nao_expoe_o_aluno_inadimplente(): void
    {
        $resposta = $this->get(route('site.inicio'));

        $resposta->assertSee('Procure a recepção');
    }

    public function test_pagina_inicial_leva_para_o_login(): void
    {
        $resposta = $this->get(route('site.inicio'));

        $resposta->assertSee(route('login'), escape: false);
    }
}
