<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensagens de autenticacao
|--------------------------------------------------------------------------
|
| "failed" e proposital: nao diz se foi o e-mail ou a senha que errou, porque
| revelar isso entrega a quem tenta invadir quais e-mails existem no sistema.
|
*/

return [

    'failed' => 'E-mail ou senha incorretos.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de acesso. Aguarde :seconds segundos e tente de novo.',

];
