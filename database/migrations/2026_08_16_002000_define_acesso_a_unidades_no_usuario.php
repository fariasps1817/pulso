<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acesso a unidades deixa de ser inferido e passa a ser declarado.
 *
 * O QUE ESTAVA ERRADO
 * -------------------
 * O alcance do usuário vinha da tabela `unidade_user`: sem nenhum vínculo, ele
 * enxergava TODAS as unidades. A intenção era atender o dono, mas o efeito
 * colateral era grave — uma recepcionista cadastrada às pressas, sem vincular
 * unidade, ganhava a rede inteira. E em silêncio.
 *
 * Isso falhava ABRINDO, o contrário do resto do sistema: no Row Level
 * Security, sem academia definida o banco devolve zero linhas.
 *
 * O QUE PASSA A VALER
 * -------------------
 * `acessa_todas_unidades` é a única fonte da verdade. Vínculo vazio não
 * significa mais nada: quem não tem o campo marcado nem unidade vinculada não
 * enxerga nenhuma — e o sistema avisa, em vez de liberar tudo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            // Falso por padrão: o alcance amplo é exceção, e exceção se declara.
            $tabela->boolean('acessa_todas_unidades')->default(false)->after('academia_id');

            // Qual filial abre ao entrar. Nula = a primeira acessível.
            $tabela->foreignId('unidade_padrao_id')->nullable()->after('acessa_todas_unidades')
                ->constrained('unidades')->nullOnDelete();

            /*
             * Se o seletor do cabeçalho funciona. Separado do acesso de
             * propósito: há quem precise VER dados de várias filiais sem
             * OPERAR em todas — um gerente que acompanha a rede mas atende num
             * balcão só.
             */
            $tabela->boolean('pode_alternar_unidade')->default(false)->after('unidade_padrao_id');
        });

        /*
         * Migração dos usuários existentes.
         *
         * Quem é dono passa a ter o campo marcado — mantém o que já enxergava.
         * O resto fica com o campo desmarcado e continua limitado ao que
         * estiver em `unidade_user`. Se alguém dependia do vínculo vazio para
         * ver tudo, perde o acesso agora, de forma visível — que é exatamente
         * o ponto.
         */
        $donos = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'dono')
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->pluck('model_has_roles.model_id');

        if ($donos->isNotEmpty()) {
            DB::table('users')->whereIn('id', $donos)->update([
                'acessa_todas_unidades' => true,
                'pode_alternar_unidade' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->dropConstrainedForeignId('unidade_padrao_id');
            $tabela->dropColumn(['acessa_todas_unidades', 'pode_alternar_unidade']);
        });
    }
};
