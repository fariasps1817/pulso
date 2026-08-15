<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensagens de recuperacao de senha
|--------------------------------------------------------------------------
|
| "sent" e "user" respondem a mesma coisa de proposito: confirmar que um
| e-mail nao esta cadastrado revelaria quais contas existem no sistema.
|
*/

return [

    'reset' => 'Pronto, sua senha foi alterada.',
    'sent' => 'Se esse e-mail estiver cadastrado, o link de recuperação chegará em instantes.',
    'throttled' => 'Aguarde um pouco antes de pedir outro link.',
    'token' => 'Esse link de recuperação não vale mais. Peça um novo.',
    'user' => 'Se esse e-mail estiver cadastrado, o link de recuperação chegará em instantes.',

];
