<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fila de comandos para o aparelho.
 *
 * Existe porque o sentido da conversa é fixo: o aparelho pergunta, o servidor
 * responde. Nunca o contrário. "Cadastrar o aluno no leitor" não é uma
 * chamada — é uma linha aqui, que o aparelho vai buscar no próximo polling.
 *
 * O CICLO TEM TRÊS ESTADOS, E NÃO DOIS
 *
 * `pendente` → `entregue` → `confirmado`. O estado do meio é o que evita a
 * perda silenciosa: se o comando saiu na resposta de um polling e a rede caiu
 * antes de o aparelho aplicá-lo, ninguém saberia. Passado o prazo sem ACK, o
 * comando volta para `pendente` e é reenviado.
 *
 * Reenviar é seguro porque os comandos do protocolo são declarativos —
 * "o usuário 7 passa a ser assim" —, não incrementais.
 *
 * `retorno` guarda o código do ACK. Zero é sucesso; o resto é diagnóstico que
 * vale ouro quando o aparelho está a duzentos quilômetros de distância.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comandos_dispositivo', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $tabela->foreignId('dispositivo_id')
                ->constrained('dispositivos_acesso')->cascadeOnDelete();

            // Para quem é o comando, quando faz sentido perguntar isso.
            $tabela->foreignId('aluno_id')->nullable()->constrained('alunos')->cascadeOnDelete();

            /*
             * O verbo separado do corpo para a tela poder dizer "cadastrar
             * digital" em vez de despejar a linha crua do protocolo na cara
             * da recepção.
             */
            $tabela->string('verbo', 40);
            $tabela->text('corpo');

            $tabela->string('situacao', 20)->default('pendente');
            $tabela->unsignedSmallInteger('tentativas')->default(0);

            $tabela->timestampTz('entregue_em')->nullable();
            $tabela->timestampTz('confirmado_em')->nullable();
            $tabela->integer('retorno')->nullable();

            $tabela->timestampsTz();
        });

        DB::statement("ALTER TABLE comandos_dispositivo ADD CONSTRAINT comandos_situacao_valida
            CHECK (situacao IN ('pendente', 'entregue', 'confirmado', 'falhou'))");

        /*
         * O índice que importa: a cada polling — a cada poucos segundos, por
         * aparelho — alguém pergunta "há comando pendente para este?". Parcial
         * porque a fila resolvida cresce para sempre e não interessa a essa
         * pergunta.
         */
        DB::statement("CREATE INDEX comandos_a_entregar
            ON comandos_dispositivo (dispositivo_id, id)
            WHERE situacao IN ('pendente', 'entregue')");

        // Tabela de domínio nova: entra no isolamento como as outras, com o
        // mesmo nome de política — há teste que percorre todas e cobra isso.
        DB::statement('ALTER TABLE comandos_dispositivo ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE comandos_dispositivo FORCE ROW LEVEL SECURITY');

        DB::statement('
            CREATE POLICY isolamento_academia ON comandos_dispositivo
                USING      (academia_id = pulso_academia_atual())
                WITH CHECK (academia_id = pulso_academia_atual())
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('comandos_dispositivo');
    }
};
