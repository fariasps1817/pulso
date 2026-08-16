<?php

declare(strict_types=1);

use App\Http\Controllers\Catraca\AparelhoController;
use App\Http\Middleware\IdentificaAparelho;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aparelho biometrico — protocolo PUSH/ADMS
|--------------------------------------------------------------------------
|
| Rotas FORA do grupo `web`, e de proposito: o aparelho nao tem sessao, nao
| tem cookie e nao sabe o que e um token CSRF. A identidade dele e o numero de
| serie, conferido pelo middleware IdentificaAparelho.
|
| O caminho /iclock/* nao e escolha nossa — e o que o firmware procura.
|
| Toda resposta e texto puro. Toda resposta e 200. Um erro HTTP aqui faz o
| aparelho reenviar o lote inteiro na proxima tentativa.
|
*/

Route::prefix('iclock')
    ->middleware(IdentificaAparelho::class)
    ->group(function (): void {
        Route::match(['GET', 'POST'], 'cdata', [AparelhoController::class, 'cdata']);
        Route::get('getrequest', [AparelhoController::class, 'getrequest']);
        Route::match(['GET', 'POST'], 'devicecmd', [AparelhoController::class, 'devicecmd']);
        Route::match(['GET', 'POST'], 'rtdata', [AparelhoController::class, 'rtdata']);
        Route::match(['GET', 'POST'], 'registry', [AparelhoController::class, 'registry']);
        Route::match(['GET', 'POST'], 'push', [AparelhoController::class, 'ok']);
        Route::get('ping', [AparelhoController::class, 'ping']);

        /*
         * Qualquer outro caminho sob /iclock responde OK. Firmwares diferentes
         * procuram endpoints diferentes, e um 404 seria lido como falha de
         * rede — o aparelho tentaria de novo, para sempre.
         */
        Route::any('{qualquer}', [AparelhoController::class, 'ok'])->where('qualquer', '.*');
    });
