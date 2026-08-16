<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entrada e saída numa catraca que não sabe para que lado girou.
 *
 * A montagem é a mais simples possível: o leitor reconhece o aluno, fecha um
 * contato seco por um segundo, e a catraca libera o giro. Um equipamento por
 * catraca. Nada nesse caminho diz se a pessoa entrou ou saiu — e o próprio
 * protocolo confirma, mandando `Status=255` ("sem estado") em todo registro.
 *
 * Então o sentido é DEDUZIDO, e o banco guarda a dedução, não uma leitura.
 * Por isso `presumida` existe: uma saída que ninguém registrou e que o
 * sistema concluiu ter acontecido não pode se parecer com uma que aconteceu.
 * Um relatório de permanência que trate as duas igual mente com confiança.
 *
 * `encerrada_em` fica na linha da ENTRADA. Assim "quem está na academia
 * agora" é uma consulta a um índice parcial, e não um pareamento de linhas
 * feito em memória a cada carregamento da tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $tabela): void {
            $tabela->string('sentido', 10)->default('entrada');

            // Só na linha de entrada. Nulo = a pessoa ainda está dentro.
            $tabela->timestampTz('encerrada_em')->nullable();
            $tabela->boolean('encerrada_presumida')->default(false);

            /*
             * O PIN cru como veio do aparelho, mesmo quando não casa com
             * aluno nenhum. Sem ele, um leitor mal cadastrado produz uma
             * fileira de acessos anônimos e nenhuma pista de qual matrícula
             * o aparelho estava mandando.
             */
            $tabela->string('pin', 30)->nullable();

            /*
             * Idempotência do protocolo, e a razão de ela existir: o aparelho
             * REENVIA o lote inteiro se não receber "OK". O ATTLOG não traz
             * identificador próprio, então a chave é derivada da tupla
             * (série, PIN, instante, status, método) — recomendação da
             * própria especificação do fabricante.
             *
             * A garantia mora no índice único, não no código que confere
             * antes. É o mesmo princípio da geração de mensalidades.
             */
            $tabela->string('chave_origem', 64)->nullable();
        });

        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_sentido_valido
            CHECK (sentido IN ('entrada', 'saida'))");

        // Vários nulos convivem num índice único no PostgreSQL: acesso criado
        // à mão (importação, ajuste) não precisa de chave de origem.
        DB::statement('CREATE UNIQUE INDEX acessos_origem_unica
            ON acessos (chave_origem)
            WHERE chave_origem IS NOT NULL');

        // "Quem está na academia agora", numa consulta só.
        DB::statement("CREATE INDEX acessos_presentes
            ON acessos (unidade_id, ocorreu_em DESC)
            WHERE sentido = 'entrada' AND encerrada_em IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS acessos_presentes');
        DB::statement('DROP INDEX IF EXISTS acessos_origem_unica');
        DB::statement('ALTER TABLE acessos DROP CONSTRAINT IF EXISTS acessos_sentido_valido');

        Schema::table('acessos', function (Blueprint $tabela): void {
            $tabela->dropColumn([
                'sentido', 'encerrada_em', 'encerrada_presumida', 'pin', 'chave_origem',
            ]);
        });
    }
};
