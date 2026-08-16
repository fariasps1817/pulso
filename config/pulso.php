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

    /*
     * Catraca e leitor biometrico (protocolo PUSH/ADMS da ZKTeco).
     *
     * O leitor reconhece o aluno e fecha um contato seco por um segundo; a
     * catraca ve o contato e libera o giro. Nada nesse caminho diz para que
     * lado a pessoa girou — o sentido e deduzido aqui.
     */
    'catraca' => [
        /*
         * Segundos entre cada consulta do aparelho a fila de comandos. Dois
         * segundos e bom para bancada; em producao, com varias unidades,
         * subir para 10 reduz muito o trafego sem atrasar nada que importe.
         */
        'intervalo_polling' => (int) env('CATRACA_INTERVALO_POLLING', 10),

        /*
         * Duas deteccoes do mesmo aluno dentro desta janela sao a MESMA
         * passagem: repique do rele, ou a pessoa passando o rosto duas vezes
         * porque a catraca demorou a destravar. Contar as duas inverteria o
         * sentido e marcaria uma saida que nao houve.
         */
        'janela_de_repique' => (int) env('CATRACA_JANELA_REPIQUE', 45),

        /*
         * Horas ate presumir que o aluno saiu sem registrar. Passado isso, a
         * proxima deteccao e uma NOVA entrada, e a anterior e encerrada como
         * presumida. Quatro horas cobrem treino, banho e conversa com folga,
         * sem deixar ninguem "dentro da academia" a noite inteira.
         */
        'horas_ate_presumir_saida' => (int) env('CATRACA_HORAS_PRESUMIR_SAIDA', 4),

        /*
         * Minutos sem confirmacao ate reenviar um comando ja entregue. Cobre
         * o caso de a rede cair entre a entrega e a aplicacao.
         */
        'minutos_para_reenviar_comando' => (int) env('CATRACA_MINUTOS_REENVIO', 5),
    ],

];
