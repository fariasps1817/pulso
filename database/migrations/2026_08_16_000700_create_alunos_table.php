<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alunos.
 *
 * Cadastrados POR ACADEMIA, não por unidade: numa rede, o mesmo CPF não pode
 * virar dois cadastros. Onde ele treina é assunto da matrícula.
 *
 * CPF, data de nascimento e WhatsApp são obrigatórios por decisão de produto —
 * são o que sustentam a lista de aniversariantes, o controle de menor de idade
 * e o contrato. O CPF guarda só os 11 dígitos; a pontuação é da tela.
 *
 * Exclusão é lógica: o aluno some das listas, mas a mensalidade que ele pagou
 * em março continua existindo. O template biométrico, esse sim, é apagado de
 * verdade (ver credenciais_acesso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();

            // Gravado em caixa de título brasileira pelo model.
            $tabela->string('nome');
            $tabela->string('cpf', 11);
            $tabela->date('data_nascimento');
            $tabela->string('sexo', 1)->nullable();

            $tabela->string('email')->nullable();
            $tabela->string('telefone', 11)->nullable();
            // Separado do telefone: nem todo telefone recebe WhatsApp, e é por
            // ele que a cobrança e o aviso de vencimento saem.
            $tabela->string('whatsapp', 11);

            $tabela->string('cep', 8)->nullable();
            $tabela->string('logradouro')->nullable();
            $tabela->string('numero', 20)->nullable();
            $tabela->string('complemento')->nullable();
            $tabela->string('bairro')->nullable();
            $tabela->string('cidade')->nullable();
            $tabela->string('uf', 2)->nullable();

            // Foto de identificação da recepção. NÃO é template biométrico:
            // aquele é cifrado, vive em credenciais_acesso, e a imagem do
            // rosto nunca é armazenada.
            $tabela->string('foto_path')->nullable();

            $tabela->text('observacoes')->nullable();

            // Obrigatórios quando menor de 18 — regra validada na aplicação,
            // porque depende da idade na data do cadastro.
            $tabela->string('responsavel_nome')->nullable();
            $tabela->string('responsavel_cpf', 11)->nullable();
            $tabela->string('responsavel_telefone', 11)->nullable();
            $tabela->string('responsavel_parentesco', 40)->nullable();

            $tabela->timestampsTz();
            $tabela->softDeletesTz();

            $tabela->index(['academia_id', 'nome']);
            // Aniversariantes do dia: o Radar consulta por mês e dia.
            $tabela->index(['academia_id', 'data_nascimento']);
        });

        DB::statement('CREATE UNIQUE INDEX alunos_cpf_por_academia
            ON alunos (academia_id, cpf) WHERE deleted_at IS NULL');

        // E-mail único por academia já nasce preparado para virar login do
        // "Meu Pulso", evitando uma migration de correção lá na frente.
        DB::statement('CREATE UNIQUE INDEX alunos_email_por_academia
            ON alunos (academia_id, email)
            WHERE email IS NOT NULL AND deleted_at IS NULL');

        // Busca por nome com erro de digitação ("Joao Siva" acha "João Silva").
        // É o índice que justifica a extensão pg_trgm.
        DB::statement('CREATE INDEX alunos_nome_busca
            ON alunos USING gin (nome gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
