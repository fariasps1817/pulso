<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada passagem (ou tentativa) na catraca. É a tabela que mais cresce.
 *
 * O `motivo` do bloqueio fica AQUI, no banco, e NUNCA vai para o display da
 * catraca. Acesso negado por inadimplência mostra "Procure a recepção" — expor
 * a dívida com outros alunos na fila é constrangimento vedado pelo Código de
 * Defesa do Consumidor (art. 42). O motivo existe para a recepção entender, e
 * é isso.
 *
 * `ocorreu_em` é timestamptz: o instante importa, e o banco guarda em UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acessos', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $tabela->foreignId('dispositivo_id')->nullable()
                ->constrained('dispositivos_acesso')->nullOnDelete();

            // Nulo quando o equipamento não reconheceu ninguém.
            $tabela->foreignId('aluno_id')->nullable()->constrained('alunos')->cascadeOnDelete();

            $tabela->timestampTz('ocorreu_em');
            $tabela->string('resultado', 20);
            $tabela->string('motivo', 40)->nullable();
            $tabela->string('tipo_credencial', 20)->nullable();

            $tabela->timestampsTz();
        });

        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_resultado_valido
            CHECK (resultado IN ('liberado', 'bloqueado'))");

        /*
         * Índice por aluno e data, descendente: é a consulta da baixa
         * frequência ("quem não passou nos últimos N dias") e a da ficha do
         * aluno ("últimas passagens"). Ordem descendente porque ambas querem
         * o mais recente primeiro.
         */
        DB::statement('CREATE INDEX acessos_por_aluno
            ON acessos (aluno_id, ocorreu_em DESC)
            WHERE aluno_id IS NOT NULL');

        DB::statement('CREATE INDEX acessos_por_unidade ON acessos (unidade_id, ocorreu_em DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos');
    }
};
