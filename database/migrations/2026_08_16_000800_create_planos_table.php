<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planos — o que a academia vende.
 *
 * Plano descontinuado não é apagado: matrículas antigas continuam apontando
 * para ele, e o histórico precisa saber o que foi contratado. Desativa-se.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();

            $tabela->string('nome');
            $tabela->text('descricao')->nullable();

            // numeric, não float: dinheiro não admite arredondamento binário.
            $tabela->decimal('valor_mensal', 10, 2);
            $tabela->decimal('taxa_matricula', 10, 2)->default(0);
            $tabela->decimal('multa_cancelamento', 10, 2)->default(0);

            // 1 = mensal; 3, 6, 12 = com prazo.
            $tabela->smallInteger('duracao_meses')->default(1);

            $tabela->boolean('acesso_todas_unidades')->default(false);

            // A experiência acaba pelo que vier primeiro: os dias ou as
            // sessões. Uma academia usa "7 dias", outra usa "3 aulas", e uma
            // terceira usa os dois. Zero desliga o critério.
            $tabela->smallInteger('dias_experiencia')->default(0);
            $tabela->smallInteger('sessoes_experiencia')->default(0);

            $tabela->boolean('ativo')->default(true);

            $tabela->timestampsTz();
            $tabela->softDeletesTz();

            $tabela->index(['academia_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
