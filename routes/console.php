<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Rotinas agendadas
|--------------------------------------------------------------------------
|
| Na VPS, um único cron chama o agendador a cada minuto:
|
|     * * * * * cd /caminho/do/pulso && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
 * Geração das mensalidades do mês.
 *
 * Roda de madrugada, quando ninguém está no balcão. É idempotente — o índice
 * único (matricula_id, competencia) impede duplicata —, então rodar todo dia
 * é seguro e cobre a matrícula criada ontem.
 *
 * `withoutOverlapping` evita duas execuções simultâneas numa academia grande,
 * em que a geração leve mais de um minuto.
 *
 * Roda pela conexão da aplicação: o comando define o contexto de cada academia
 * e o Row Level Security o autoriza uma a uma, sem precisar de um papel que
 * atravessa o isolamento.
 */
Schedule::command('pulso:gerar-mensalidades')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Fechamento das entradas abandonadas na catraca.
 *
 * A catraca e de contato seco e nao conta para que lado girou: o sentido e
 * deduzido pela alternancia. Quem entra e vai embora sem passar de novo
 * ficaria "dentro da academia" para sempre, e o numero que a recepcao usa
 * para saber se pode fechar viraria ficcao.
 *
 * Roda de madrugada, depois de a academia ter fechado.
 */
Schedule::command('pulso:fechar-acessos')
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->onOneServer();
