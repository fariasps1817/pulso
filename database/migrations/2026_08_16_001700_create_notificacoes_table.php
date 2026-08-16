<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O que foi enviado ao aluno — hoje por WhatsApp.
 *
 * UMA LINHA POR ENVIO, de propósito: a regra "mensalidade vencida gera UM
 * lembrete, não uma sequência" (CDC art. 42 — cobrança não pode constranger)
 * passa a ser verificável com uma consulta, em vez de confiada à memória do
 * código.
 *
 * A tabela é neutra quanto ao provedor: serve para a API oficial da Meta ou
 * para um intermediador, sem mudança de estrutura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $tabela->foreignId('mensalidade_id')->nullable()
                ->constrained('mensalidades')->cascadeOnDelete();

            $tabela->string('canal', 20)->default('whatsapp');
            $tabela->string('modelo', 40);
            $tabela->string('destino', 20);

            $tabela->string('situacao', 20)->default('na_fila');
            $tabela->timestampTz('enviada_em')->nullable();
            $tabela->text('erro')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['aluno_id', 'modelo']);
            $tabela->index(['academia_id', 'situacao']);
        });

        DB::statement("ALTER TABLE notificacoes ADD CONSTRAINT notificacoes_situacao_valida
            CHECK (situacao IN ('na_fila', 'enviada', 'entregue', 'lida', 'falhou'))");

        // Um lembrete por modelo, por mensalidade. É o banco recusando a
        // cobrança repetida, e não uma condição no código que alguém pode
        // esquecer de escrever no próximo lugar.
        DB::statement('CREATE UNIQUE INDEX notificacoes_um_lembrete_por_mensalidade
            ON notificacoes (mensalidade_id, modelo)
            WHERE mensalidade_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
