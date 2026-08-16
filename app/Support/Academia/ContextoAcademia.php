<?php

declare(strict_types=1);

namespace App\Support\Academia;

use Illuminate\Support\Facades\DB;

/**
 * Qual academia está em foco nesta requisição.
 *
 * Guarda o identificador em memória (para o filtro do Eloquent) e o propaga
 * para a sessão do PostgreSQL (para as políticas de Row Level Security). São
 * duas barreiras com propósitos diferentes:
 *
 *   - o filtro do Eloquent é o do dia a dia, e produz consultas eficientes;
 *   - o RLS é a rede de proteção, e vale mesmo quando o filtro é esquecido.
 *
 * Registrado como singleton: uma requisição, uma academia.
 */
final class ContextoAcademia
{
    private ?int $academiaId = null;

    /**
     * Define a academia da requisição e propaga para o banco.
     *
     * `set_config(..., false)` vale para a sessão da conexão, não só para a
     * transação. Como o Laravel abre e fecha a conexão a cada requisição, o
     * valor não sobrevive de uma para outra. **Se um dia entrar um pool de
     * conexões persistentes, esta linha precisa ser revista** — senão a
     * academia de um request vaza para o seguinte.
     */
    public function definir(?int $academiaId): void
    {
        $this->academiaId = $academiaId;

        DB::statement(
            'SELECT set_config(?, ?, false)',
            ['pulso.academia_id', $academiaId === null ? '' : (string) $academiaId],
        );
    }

    public function id(): ?int
    {
        return $this->academiaId;
    }

    public function definida(): bool
    {
        return $this->academiaId !== null;
    }

    public function limpar(): void
    {
        $this->definir(null);
    }

    /**
     * Executa algo no contexto de outra academia e devolve o anterior.
     *
     * Existe para rotinas que percorrem várias academias — a geração diária
     * de mensalidades, por exemplo. Não é atalho para telas: numa requisição
     * web a academia vem do usuário autenticado e não muda.
     *
     * @template T
     *
     * @param  callable(): T  $acao
     * @return T
     */
    public function paraAcademia(int $academiaId, callable $acao): mixed
    {
        $anterior = $this->academiaId;

        $this->definir($academiaId);

        try {
            return $acao();
        } finally {
            $this->definir($anterior);
        }
    }
}
