<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos — o dinheiro que efetivamente entrou.
 *
 * Tabela separada da mensalidade porque uma pode receber vários: o aluno paga
 * metade em dinheiro e metade no Pix, e o balcão precisa registrar os dois.
 *
 * Estorno não apaga o registro — marca `estornado_em`. Apagar dinheiro que
 * entrou e depois voltou destrói a conciliação com o extrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('mensalidade_id')->constrained('mensalidades')->cascadeOnDelete();
            $tabela->foreignId('cobranca_id')->nullable()->constrained('cobrancas')->nullOnDelete();

            $tabela->decimal('valor', 10, 2);
            $tabela->string('forma', 30);
            $tabela->date('recebido_em');

            // Quem deu baixa. Nulo = baixa automática por webhook do provedor.
            $tabela->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();

            $tabela->timestampTz('estornado_em')->nullable();
            $tabela->string('motivo_estorno')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['academia_id', 'recebido_em']);
            $tabela->index('mensalidade_id');
        });

        DB::statement("ALTER TABLE pagamentos ADD CONSTRAINT pagamentos_forma_valida
            CHECK (forma IN ('dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'transferencia'))");

        DB::statement('ALTER TABLE pagamentos ADD CONSTRAINT pagamentos_valor_positivo
            CHECK (valor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
