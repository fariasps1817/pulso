<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Preferências de interface do usuário autenticado.
 *
 * Ficam no perfil, e não só no navegador, porque a equipe alterna entre o
 * computador do balcão e o próprio celular: quem recolheu a barra lateral no
 * balcão espera encontrá-la recolhida no celular também.
 */
final class PreferenciaController extends Controller
{
    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tema' => ['sometimes', Rule::in(config('pulso.tema.opcoes'))],
            'sidebar_recolhida' => ['sometimes', 'boolean'],
            'itens_por_pagina' => ['sometimes', 'integer', 'in:10,25,50,100'],
        ]);

        $usuario = $request->user();

        /*
         * As preferências são um documento JSON: mesclar preserva as chaves
         * que esta requisição não mencionou. Substituir apagaria o tema ao
         * salvar só o estado da barra lateral.
         */
        $usuario->preferencias = array_merge($usuario->preferencias ?? [], $dados);
        $usuario->save();

        return response()->json(['preferencias' => $usuario->preferencias]);
    }
}
