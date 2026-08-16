<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academias — o cliente do Pulso.
 *
 * Faz parte do "plano de controle": esta tabela NÃO recebe política de Row
 * Level Security por academia, porque é justamente ela que define o que é uma
 * academia. Quem a enxerga é o super administrador, e o acesso da própria
 * academia aos seus dados é limitado por autorização na aplicação.
 *
 * A tabela tem duas metades: a que a academia edita nas configurações e a que
 * só o super administrador altera (situação, bloqueio, vencimento da
 * assinatura). Ficam juntas porque separá-las obrigaria a um join em toda
 * tela; a divisão é de permissão, não de estrutura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academias', function (Blueprint $tabela): void {
            $tabela->id();

            // ---------- identificação ----------
            $tabela->string('nome');
            $tabela->string('razao_social')->nullable();
            $tabela->string('cnpj', 14)->nullable()->unique();
            $tabela->string('email');
            $tabela->string('telefone', 11)->nullable();
            $tabela->string('whatsapp', 11)->nullable();

            // ---------- endereço: cabeçalho dos PDFs ----------
            $tabela->string('cep', 8)->nullable();
            $tabela->string('logradouro')->nullable();
            $tabela->string('numero', 20)->nullable();
            $tabela->string('complemento')->nullable();
            $tabela->string('bairro')->nullable();
            $tabela->string('cidade')->nullable();
            $tabela->string('uf', 2)->nullable();

            // Logo da academia nos documentos. Não substitui a marca Pulso na
            // interface — o sistema continua sendo o Pulso.
            $tabela->string('logo_path')->nullable();

            // ---------- regras que a academia ajusta ----------
            // Dias de tolerância antes de a catraca bloquear por inadimplência.
            // Bloquear no dia seguinte ao vencimento gera briga no balcão;
            // nunca bloquear torna a catraca inútil como instrumento de cobrança.
            $tabela->smallInteger('dias_tolerancia_bloqueio')->default(5);

            // A partir de quantos dias sem passar na catraca o aluno entra no
            // Radar como baixa frequência.
            $tabela->smallInteger('dias_baixa_frequencia')->default(15);

            // Idade mínima para matrícula. Academia com atividade infantil baixa.
            $tabela->smallInteger('idade_minima')->default(12);

            // ---------- controle do SaaS: só o super administrador ----------
            $tabela->string('situacao', 20)->default('ativa');
            $tabela->date('assinatura_vence_em')->nullable();
            $tabela->timestampTz('bloqueada_em')->nullable();
            $tabela->text('motivo_bloqueio')->nullable();

            $tabela->timestampsTz();

            $tabela->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academias');
    }
};
