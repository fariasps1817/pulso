<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantos alunos ativos cada academia tem — para o super administrador.
 *
 * A COLUNA EXISTE PORQUE O ISOLAMENTO NÃO TEM EXCEÇÃO
 *
 * O super administrador não enxerga `alunos`: as políticas de Row Level
 * Security não abrem para ele, e essa decisão é o que faz uma conta da equipe
 * do Pulso comprometida não dar acesso a dado de aluno nenhum.
 *
 * Mas a cobrança do SaaS depende do porte da academia — e do número de
 * filiais. Filial ele já conta direto, porque `unidades` é plano de controle.
 * Aluno, não.
 *
 * Então a aplicação — que TEM contexto — mantém o total aqui. O super
 * administrador lê um NÚMERO, nunca uma pessoa. É a diferença entre saber que
 * a academia tem 180 alunos e poder abrir a ficha de qualquer um deles.
 *
 * O total é recalculado a cada mudança de matrícula e reconferido de
 * madrugada — contador incrementado a cada evento acumula erro, e um número
 * de cobrança errado é pior do que uma consulta a mais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academias', function (Blueprint $tabela): void {
            $tabela->unsignedInteger('total_alunos_ativos')->default(0);
            $tabela->timestampTz('contagem_atualizada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('academias', function (Blueprint $tabela): void {
            $tabela->dropColumn(['total_alunos_ativos', 'contagem_atualizada_em']);
        });
    }
};
