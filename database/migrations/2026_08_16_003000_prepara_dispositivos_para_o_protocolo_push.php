<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O aparelho biométrico como cliente do protocolo PUSH/ADMS da ZKTeco.
 *
 * O fato que organiza tudo: o aparelho NÃO é servidor. Ele é um cliente HTTP
 * que faz polling no Pulso. Nunca abrimos conexão com ele — enfileiramos
 * comandos e ele os busca. Toda chamada dele traz o número de série, e é dele
 * que sai a academia.
 *
 * O PROBLEMA DO OVO E DA GALINHA, E COMO ELE SE RESOLVE
 *
 * `dispositivos_acesso` está sob Row Level Security, e com razão. Mas a
 * consulta que traduz o número de série em academia acontece ANTES de existir
 * "academia atual" — é ela que define o contexto. Sob RLS, essa consulta
 * devolveria zero linhas, sempre.
 *
 * Três saídas eram possíveis, e duas são ruins:
 *
 *   1. Tirar a tabela do RLS. Enfraquece uma garantia provada por teste para
 *      resolver um caso.
 *   2. Consultar pela conexão de manutenção. Daria ao endpoint público — o
 *      único que responde sem login — um papel que atravessa o isolamento.
 *      É exatamente onde menos se quer esse poder.
 *   3. Uma função SECURITY DEFINER estreita. É esta.
 *
 * A função abaixo devolve APENAS a tripla de roteamento (dispositivo,
 * academia, unidade) para um número de série exato. A aplicação não ganha
 * "ler dispositivos"; ganha "traduzir um serial que ela já conhece". Depois
 * disso o contexto é definido e todo o resto volta a passar pelo RLS normal.
 *
 * `SET search_path` é obrigatório numa função SECURITY DEFINER: sem ele,
 * quem chama pode plantar um schema no caminho e fazer a função ler outra
 * tabela. É a armadilha clássica desse recurso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos_acesso', function (Blueprint $tabela): void {
            // A ficha que o aparelho manda no handshake (table=options) e o
            // INFO do polling. JSONB porque a lista muda entre firmwares e
            // nunca é consultada em WHERE.
            $tabela->jsonb('informacoes')->nullable();

            $tabela->string('firmware', 60)->nullable();

            /*
             * Segredo compartilhado do protocolo. Quando ligado no aparelho,
             * ele o envia no handshake — é o que separa "meu aparelho" de
             * "qualquer um que descobriu a URL".
             */
            $tabela->string('chave_push', 120)->nullable();

            // Heartbeat: distinto de ultima_sincronizacao_em, que é sobre
            // dados. Este é só "está vivo".
            $tabela->timestampTz('ultimo_contato_em')->nullable();
        });

        /*
         * O serial identifica o aparelho no mundo inteiro — não pode repetir
         * entre academias, senão o roteamento fica ambíguo. Índice parcial
         * porque o cadastro pode nascer sem o serial (o técnico ainda vai
         * instalar).
         */
        DB::statement('CREATE UNIQUE INDEX dispositivos_serie_unica
            ON dispositivos_acesso (numero_serie)
            WHERE numero_serie IS NOT NULL');

        DB::statement('
            CREATE OR REPLACE FUNCTION pulso_dispositivo_por_serie(serie text)
            RETURNS TABLE (dispositivo_id bigint, academia_id bigint, unidade_id bigint)
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = public
            AS $$
                SELECT id, academia_id, unidade_id
                FROM dispositivos_acesso
                WHERE numero_serie = serie
                  AND ativo
                LIMIT 1
            $$
        ');

        // Ninguém executa por padrão; só o papel da aplicação.
        DB::statement('REVOKE ALL ON FUNCTION pulso_dispositivo_por_serie(text) FROM PUBLIC');

        $app = (string) config('database.connections.pgsql.username');

        if ($app !== '') {
            $papel = '"'.str_replace('"', '""', $app).'"';

            DB::statement("GRANT EXECUTE ON FUNCTION pulso_dispositivo_por_serie(text) TO {$papel}");
        }
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS pulso_dispositivo_por_serie(text)');
        DB::statement('DROP INDEX IF EXISTS dispositivos_serie_unica');

        Schema::table('dispositivos_acesso', function (Blueprint $tabela): void {
            $tabela->dropColumn(['informacoes', 'firmware', 'chave_push', 'ultimo_contato_em']);
        });
    }
};
