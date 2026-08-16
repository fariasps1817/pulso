<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobranças emitidas no provedor de pagamento — o Pix ou o link de cartão.
 *
 * O provedor ainda não foi escolhido, então a tabela é deliberadamente
 * NEUTRA: `provedor`, `id_externo`, `payload` e situação. Asaas, Mercado Pago,
 * Efí ou integração direta com banco encaixam nesse formato sem migration.
 *
 * `payload` guarda o retorno cru do provedor e dos webhooks. É JSONB com
 * índice GIN: quando algo não bater com o extrato, a resposta original estará
 * lá, consultável — e não perdida num log rotacionado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('mensalidade_id')->constrained('mensalidades')->cascadeOnDelete();

            $tabela->string('provedor', 40);
            $tabela->string('id_externo');
            $tabela->string('tipo', 20);
            $tabela->decimal('valor', 10, 2);
            $tabela->string('situacao', 20)->default('emitida');

            $tabela->text('pix_copia_cola')->nullable();
            $tabela->text('url')->nullable();
            $tabela->timestampTz('expira_em')->nullable();

            $tabela->jsonb('payload')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['academia_id', 'situacao']);
            // O webhook chega identificando a cobrança pelo id do provedor.
            $tabela->unique(['provedor', 'id_externo']);
        });

        DB::statement("ALTER TABLE cobrancas ADD CONSTRAINT cobrancas_situacao_valida
            CHECK (situacao IN ('emitida', 'paga', 'expirada', 'cancelada'))");

        DB::statement("ALTER TABLE cobrancas ADD CONSTRAINT cobrancas_tipo_valido
            CHECK (tipo IN ('pix', 'cartao'))");

        DB::statement('CREATE INDEX cobrancas_payload ON cobrancas USING gin (payload)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
