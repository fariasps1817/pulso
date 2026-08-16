<?php

declare(strict_types=1);

use App\Http\Controllers\AvisoController;
use App\Http\Controllers\PreferenciaController;
use App\Http\Controllers\UnidadeAtualController;
use App\Livewire\Acesso;
use App\Livewire\Administracao;
use App\Livewire\Alunos;
use App\Livewire\Configuracoes as ConfiguracoesDaAcademia;
use App\Livewire\Matriculas;
use App\Livewire\Mensalidades;
use App\Livewire\Painel;
use App\Livewire\Planos;
use App\Livewire\Usuarios;
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

/*
 * Troca da senha temporaria. Autenticada, mas FORA do painel: quem chega aqui
 * ainda nao passou pela porta que o middleware ExigirTrocaDeSenha guarda.
 */
Route::middleware(['auth'])->get('/senha', Acesso\TrocarSenha::class)->name('senha.trocar');
Route::redirect('/esqueci-a-senha', '/forgot-password')->name('acesso.esqueci-a-senha');

/*
|--------------------------------------------------------------------------
| Painel (autenticado)
|--------------------------------------------------------------------------
|
| Destino pos-login (config/fortify.php -> 'home'): o Radar.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/painel', Painel\Radar::class)->name('painel.inicio');

    Route::post('/preferencias', [PreferenciaController::class, 'salvar'])->name('preferencias.salvar');
    Route::post('/unidade-atual', [UnidadeAtualController::class, 'trocar'])->name('painel.trocar-unidade');
    Route::post('/avisos/{aviso}/dispensar', [AvisoController::class, 'dispensar'])->name('avisos.dispensar');

    /*
     * Alunos.
     *
     * O caminho é sempre o mesmo: lista → detalhes → editar. Quem aprendeu a
     * mexer em alunos sabe mexer em planos.
     *
     * "novo" vem antes de "{aluno}" de propósito: registrada depois, a rota
     * com parâmetro capturaria /alunos/novo e tentaria buscar um aluno
     * chamado "novo".
     */
    Route::prefix('alunos')->name('alunos.')->group(function (): void {
        Route::get('/', Alunos\Lista::class)->name('lista');
        Route::get('/novo', Alunos\Formulario::class)->name('novo');
        Route::get('/{aluno}', Alunos\Detalhes::class)->name('detalhes');
        Route::get('/{aluno}/editar', Alunos\Formulario::class)->name('editar');
    });

    Route::prefix('matriculas')->name('matriculas.')->group(function (): void {
        Route::get('/', Matriculas\Lista::class)->name('lista');
        Route::get('/nova', Matriculas\Formulario::class)->name('nova');
        Route::get('/{matricula}', Matriculas\Detalhes::class)->name('detalhes');
    });

    /*
     * Controle de acesso. O simulador fica fora do ar em producao: e
     * ferramenta de construcao, como o catalogo do design system.
     */
    Route::prefix('acesso')->name('acesso.')->group(function (): void {
        Route::get('/', Acesso\Painel::class)->name('painel');

        if (! app()->isProduction()) {
            Route::get('/simulador', Acesso\Simulador::class)->name('simulador');
        }
    });

    Route::prefix('mensalidades')->name('mensalidades.')->group(function (): void {
        Route::get('/', Mensalidades\Lista::class)->name('lista');
        Route::get('/{mensalidade}', Mensalidades\Detalhes::class)->name('detalhes');
    });

    /*
     * Configuracoes da academia — o guarda-chuva do que ela ajusta por conta
     * propria. Hoje so usuarios; dados da academia, unidades e regras de
     * cobranca entram aqui.
     */
    Route::view('/configuracoes', 'configuracoes.painel')->name('configuracoes.painel');

    Route::prefix('configuracoes')->name('configuracoes.')->group(function (): void {
        Route::get('/academia', ConfiguracoesDaAcademia\DadosDaAcademia::class)->name('academia');
        Route::get('/regras', ConfiguracoesDaAcademia\RegrasDaAcademia::class)->name('regras');
    });

    Route::prefix('configuracoes/usuarios')->name('usuarios.')->group(function (): void {
        Route::get('/', Usuarios\Lista::class)->name('lista');
        Route::get('/novo', Usuarios\Formulario::class)->name('novo');
        Route::get('/{usuario}/editar', Usuarios\Formulario::class)->name('editar');
    });

    Route::prefix('planos')->name('planos.')->group(function (): void {
        Route::get('/', Planos\Lista::class)->name('lista');
        Route::get('/novo', Planos\Formulario::class)->name('novo');
        Route::get('/{plano}', Planos\Detalhes::class)->name('detalhes');
        Route::get('/{plano}/editar', Planos\Formulario::class)->name('editar');
    });
});

/*
|--------------------------------------------------------------------------
| Administracao do SaaS (super administrador)
|--------------------------------------------------------------------------
|
| A area da equipe do Pulso. Quem entra aqui tem `academia_id` nulo — nao
| pertence a academia nenhuma —, e por isso as politicas de Row Level Security
| nao casam com linha nenhuma para ele: aluno, mensalidade e biometria ficam
| fora do alcance mesmo com a conta comprometida.
|
| O middleware SepararSuperAdministrador cuida das duas direcoes: manda o
| super administrador para ca, e barra a academia que tente entrar.
|
*/

Route::middleware(['auth'])->prefix('administracao')->name('administracao.')->group(function (): void {
    Route::prefix('academias')->name('academias.')->group(function (): void {
        Route::get('/', Administracao\Academias::class)->name('lista');
        Route::get('/nova', Administracao\NovaAcademia::class)->name('nova');
        Route::get('/{academia}', Administracao\DetalhesDaAcademia::class)->name('detalhes');
    });

    Route::get('/avisos', Administracao\Avisos::class)->name('avisos');
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
