<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A troca obrigatória de senha no primeiro acesso.
 *
 * O gestor cria o usuário e o sistema mostra UMA vez uma senha temporária,
 * para ele repassar à pessoa. Isso resolve o cadastro sem depender de e-mail
 * configurado — mas deixa, por alguns minutos, uma senha conhecida por duas
 * pessoas.
 *
 * Esta coluna é o que fecha essa janela: enquanto ela for verdadeira, o
 * usuário não chega a tela nenhuma sem antes escolher a própria senha. O
 * gestor nunca fica sabendo a senha definitiva de ninguém.
 *
 * Vale também para redefinição: o gestor que gera uma senha nova para quem
 * esqueceu a sua marca a coluna de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->boolean('deve_trocar_senha')->default(false)->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->dropColumn('deve_trocar_senha');
        });
    }
};
