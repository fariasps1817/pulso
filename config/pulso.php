<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pulso — configuracao institucional
|--------------------------------------------------------------------------
|
| Ponto unico de verdade para nome, slogans e contatos da marca. Nenhuma
| view escreve esses valores direto: tudo sai daqui, para que trocar um
| telefone ou o dominio seja uma alteracao em um lugar so.
|
| Base editorial: docs/marca/README.md
|
*/

return [

    'marca' => [
        'nome' => 'Pulso',
        'assinatura' => 'gestão de academias',
        'mantenedor' => env('PULSO_MANTENEDOR', 'Vladir Sistemas'),
    ],

    /*
     * Regra do guia de marca: um slogan por peca. O principal nunca aparece
     * ao lado de outro na mesma tela.
     */
    'slogans' => [
        'principal' => 'O pulso da sua academia.',
        'comercial' => 'Gestão com pulso firme.',
        'anuncio' => 'Passe livre pro aluno. Pulso firme no caixa.',
        'redes' => 'Sua academia no ritmo certo.',
        'origem' => 'Feito no Nordeste, no ritmo da sua academia.',
    ],

    'contato' => [
        // Somente digitos, com codigo do pais — formato exigido pelo wa.me.
        'whatsapp' => env('PULSO_WHATSAPP', '5585996085960'),
        'email' => env('PULSO_EMAIL', 'vladir.alencar@gmail.com'),
        'cidade' => env('PULSO_CIDADE', 'Cascavel'),
        'uf' => env('PULSO_UF', 'CE'),
    ],

    /*
     * Dominio ainda nao registrado. Ao registrar, basta ajustar APP_URL no
     * .env — nada no codigo repete o endereco.
     */
    'site' => [
        'url' => env('APP_URL', 'http://pulso.test'),
    ],

    /*
     * Tema: claro, escuro e "sistema". O terceiro nao marca nada no HTML e se
     * resolve por prefers-color-scheme. A preferencia do usuario autenticado
     * fica no banco (perfil), nao apenas no navegador — a equipe alterna
     * entre o balcao e o celular.
     */
    'tema' => [
        'padrao' => 'sistema',
        'opcoes' => ['claro', 'escuro', 'sistema'],
    ],

];
