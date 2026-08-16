<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Troca a unidade em que o usuário está operando.
 *
 * A validação não confia no que veio do formulário: confere se a unidade está
 * entre as ACESSÍVEIS do usuário e se ele tem permissão para alternar. Sem
 * isso, bastaria trocar o número no HTML para operar numa filial alheia — e o
 * seletor visual não seria controle nenhum, só decoração.
 */
final class UnidadeAtualController extends Controller
{
    public function trocar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->podeTrocarDeUnidade()) {
            throw ValidationException::withMessages([
                'unidade_id' => 'Seu acesso está fixado em uma unidade.',
            ]);
        }

        $dados = $request->validate([
            'unidade_id' => ['required', 'integer'],
        ]);

        $permitida = $usuario->unidadesAcessiveis()->contains('id', (int) $dados['unidade_id']);

        if (! $permitida) {
            throw ValidationException::withMessages([
                'unidade_id' => 'Você não tem acesso a essa unidade.',
            ]);
        }

        $usuario->preferencias = array_merge($usuario->preferencias ?? [], [
            'unidade_id' => (int) $dados['unidade_id'],
        ]);
        $usuario->save();

        return back()->with('pulso.aviso', [
            'tipo' => 'sucesso',
            'texto' => 'Unidade alterada.',
        ]);
    }
}
