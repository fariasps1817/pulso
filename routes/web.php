<?php

declare(strict_types=1);

use App\Http\Controllers\PreferenciaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site institucional
|--------------------------------------------------------------------------
*/

Route::view('/', 'site.inicio')->name('site.inicio');

/*
|--------------------------------------------------------------------------
| Acesso
|--------------------------------------------------------------------------
|
| As rotas de autenticacao em si sao registradas pelo Fortify (/login,
| /forgot-password, /reset-password). As telas sao nossas — ver
| App\Providers\FortifyServiceProvider.
|
| Os atalhos abaixo existem so para quem digita o endereco em portugues; a
| URL canonica continua sendo a do Fortify. Traduzir os caminhos exigiria
| substituir as rotas do pacote por controllers proprios — vale reavaliar
| quando o painel estiver de pe.
|
*/

Route::redirect('/entrar', '/login')->name('acesso.entrar');
Route::redirect('/esqueci-a-senha', '/forgot-password')->name('acesso.esqueci-a-senha');

/*
|--------------------------------------------------------------------------
| Painel (autenticado)
|--------------------------------------------------------------------------
|
| Placeholder do destino pos-login (config/fortify.php -> 'home'). O painel de
| verdade — Radar, alunos, mensalidades — entra nas proximas etapas.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::view('/painel', 'painel.inicio')->name('painel.inicio');

    Route::post('/preferencias', [PreferenciaController::class, 'salvar'])->name('preferencias.salvar');
});

/*
|--------------------------------------------------------------------------
| Catálogo do design system
|--------------------------------------------------------------------------
|
| Todos os componentes numa página só, para revisar aparência e comportamento
| nos dois temas antes de montar as telas de verdade. Fica fora do ar em
| producao: é ferramenta de construção, não parte do produto.
|
*/

if (! app()->isProduction()) {
    Route::view('/catalogo', 'catalogo.index')->name('catalogo');
}
