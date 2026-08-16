<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matrículas — o vínculo entre aluno, plano e unidade.
 *
 * `valor_mensal` é COPIADO do plano na contratação. Se a mensalidade lesse o
 * preço atual do plano, reajustar em janeiro mudaria retroativamente o que o
 * aluno devia em novembro.
 *
 * `dia_vencimento` fica entre 1 e 28: dia 31 não existe em fevereiro, e
 * aceitar 29–31 obrigaria a uma regra de ajuste que a recepção teria de
 * explicar toda vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $tabela->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $tabela->foreignId('plano_id')->constrained('planos')->restrictOnDelete();

            $tabela->string('tipo', 20)->default('regular');
            $tabela->string('situacao', 20)->default('ativa');

            // Matrícula regular não existe sem contrato assinado. A regra vive
            // aqui e é conferida na aplicação, não só na tela.
            $tabela->date('contrato_assinado_em')->nullable();

            // Só na experiência: quantas passagens na catraca já foram usadas.
            $tabela->smallInteger('sessoes_usadas')->default(0);

            $tabela->date('inicio_em');
            $tabela->date('fim_previsto_em')->nullable();
            $tabela->date('encerrada_em')->nullable();
            $tabela->string('motivo_encerramento')->nullable();

            $tabela->smallInteger('dia_vencimento')->default(5);
            $tabela->decimal('valor_mensal', 10, 2);

            $tabela->text('observacoes')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['academia_id', 'situacao']);
            $tabela->index(['aluno_id', 'situacao']);
            $tabela->index(['unidade_id', 'situacao']);
        });

        // O dia de vencimento é conferido pelo banco, não só pelo formulário:
        // importação e correção manual também passam por aqui.
        DB::statement('ALTER TABLE matriculas ADD CONSTRAINT matriculas_dia_vencimento_valido
            CHECK (dia_vencimento BETWEEN 1 AND 28)');

        DB::statement("ALTER TABLE matriculas ADD CONSTRAINT matriculas_tipo_valido
            CHECK (tipo IN ('experiencia', 'regular'))");

        DB::statement("ALTER TABLE matriculas ADD CONSTRAINT matriculas_situacao_valida
            CHECK (situacao IN ('experiencia', 'ativa', 'suspensa', 'encerrada', 'cancelada'))");

        // Matrícula regular exige contrato assinado. Esta é a regra de negócio
        // que mais se perde quando fica só na tela.
        DB::statement("ALTER TABLE matriculas ADD CONSTRAINT matriculas_regular_exige_contrato
            CHECK (tipo <> 'regular' OR contrato_assinado_em IS NOT NULL)");

        /*
         * O mesmo aluno não pode ter duas matrículas em vigor com período
         * sobreposto na mesma unidade.
         *
         * É a constraint EXCLUDE com btree_gist — no MySQL isso viraria
         * trigger ou torcida. `daterange` com limite superior aberto trata
         * corretamente a matrícula sem data de término.
         */
        DB::statement("
            ALTER TABLE matriculas ADD CONSTRAINT matriculas_sem_sobreposicao
            EXCLUDE USING gist (
                aluno_id   WITH =,
                unidade_id WITH =,
                daterange(inicio_em, COALESCE(encerrada_em, fim_previsto_em), '[)') WITH &&
            )
            WHERE (situacao IN ('experiencia', 'ativa', 'suspensa'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
