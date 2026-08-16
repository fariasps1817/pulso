<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria das tentativas de login.
 *
 * O bloqueio em si acontece no limitador de taxa, em cache, porque precisa ser
 * rápido. Esta tabela existe para responder depois: "quem tentou entrar na
 * conta da recepção às três da manhã?" — pergunta que o cache não responde,
 * porque ele esquece.
 *
 * Não tem academia_id: a tentativa acontece ANTES de sabermos quem é, e o
 * e-mail digitado pode nem existir. Fica fora do RLS por isso, e só o super
 * administrador a consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentativas_login', function (Blueprint $tabela): void {
            $tabela->id();

            // O que foi digitado, mesmo que não corresponda a ninguém.
            $tabela->string('email');
            $tabela->ipAddress('ip');
            $tabela->string('agente')->nullable();
            $tabela->boolean('sucesso')->default(false);

            $tabela->timestampTz('ocorreu_em');

            $tabela->index(['email', 'ocorreu_em']);
            $tabela->index(['ip', 'ocorreu_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentativas_login');
    }
};
