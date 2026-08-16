<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mensalidades — o que o aluno deve.
 *
 * "VENCIDA NÃO É COLUNA." É `situacao = 'aberta' AND vencimento < hoje`.
 * Guardar como estado exigiria uma rotina diária virando a chave, e no dia em
 * que ela falhasse o Radar mentiria para o dono — silenciosamente. O custo de
 * desempenho se resolve com índice parcial, logo abaixo.
 *
 * `vencimento` e `competencia` são `date`, nunca instante: com fuso no meio, o
 * dia viraria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensalidades', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $tabela->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            // Redundante em relação à matrícula, de propósito: o Radar e a
            // ficha consultam por aluno, e evitar o join vale a coluna.
            $tabela->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();

            // Primeiro dia do mês de referência (2026-08-01).
            $tabela->date('competencia');
            $tabela->date('vencimento');

            $tabela->decimal('valor', 10, 2);
            $tabela->decimal('desconto', 10, 2)->default(0);

            $tabela->string('situacao', 20)->default('aberta');
            $tabela->date('paga_em')->nullable();

            $tabela->text('observacoes')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['aluno_id', 'vencimento']);
        });

        DB::statement("ALTER TABLE mensalidades ADD CONSTRAINT mensalidades_situacao_valida
            CHECK (situacao IN ('aberta', 'paga', 'cancelada'))");

        // A rotina que gera as mensalidades é idempotente: rodar duas vezes no
        // mesmo dia não duplica, porque o banco não deixa.
        DB::statement('CREATE UNIQUE INDEX mensalidades_uma_por_competencia
            ON mensalidades (matricula_id, competencia)');

        /*
         * O índice que sustenta o Radar. Parcial de propósito: só as abertas
         * interessam para "vencido" e "vence hoje", e as pagas, que com o
         * tempo serão a maioria esmagadora das linhas, ficam de fora — o
         * índice permanece uma fração do tamanho da tabela.
         *
         * É exatamente a vantagem do PostgreSQL que motivou a escolha do banco.
         */
        DB::statement("CREATE INDEX mensalidades_em_aberto
            ON mensalidades (academia_id, vencimento)
            WHERE situacao = 'aberta'");
    }

    public function down(): void
    {
        Schema::dropIfExists('mensalidades');
    }
};
