<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Academia\ContextoAcademia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define, no início de cada requisição autenticada, qual academia está em foco.
 *
 * A academia vem do usuário — nunca da URL, de parâmetro ou de cabeçalho. Se
 * viesse de fora, bastaria trocar um número para ler os dados de outra
 * academia, e o isolamento inteiro dependeria de nunca esquecermos de validar
 * esse número.
 *
 * O contexto é propagado para a sessão do PostgreSQL, onde as políticas de Row
 * Level Security o consultam.
 */
final class DefinirAcademiaAtual
{
    public function __construct(private readonly ContextoAcademia $contexto) {}

    public function handle(Request $request, Closure $proximo): Response
    {
        $usuario = Auth::user();

        /*
         * Super administrador (academia_id nulo) fica SEM academia definida.
         * Não é privilégio: com a variável vazia, as políticas de RLS não
         * casam com linha nenhuma e ele simplesmente não enxerga dado de
         * academia alguma — que é exatamente o desenho acordado.
         */
        $this->contexto->definir($usuario?->academia_id);

        // Também informa o pacote de permissões, para que os papéis sejam
        // resolvidos dentro da academia certa: quem é gerente numa não é
        // gerente na outra.
        if ($usuario?->academia_id !== null) {
            setPermissionsTeamId($usuario->academia_id);
        }

        return $proximo($request);
    }
}
