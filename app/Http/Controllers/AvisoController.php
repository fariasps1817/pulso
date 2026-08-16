<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AvisoAcademia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispensa de um aviso do Pulso.
 *
 * Fica no perfil, e não no navegador, porque a equipe alterna entre o
 * computador do balcão e o celular — quem fechou num lugar não quer rever no
 * outro.
 */
final class AvisoController extends Controller
{
    public function dispensar(Request $requisicao, AvisoAcademia $aviso): JsonResponse
    {
        $usuario = $requisicao->user();

        /*
         * Aviso não dispensável não se dispensa nem por chamada direta. O
         * botão nem aparece na tela, mas a regra tem que valer aqui: é este
         * endereço que decide, não o desenho.
         */
        abort_unless($aviso->dispensavel, 403);

        // E só se ele for para esta academia — ou para todas.
        abort_unless(
            $aviso->academia_id === null || $aviso->academia_id === $usuario->academia_id,
            403,
        );

        $preferencias = $usuario->preferencias ?? [];
        $dispensados = $preferencias['avisos_dispensados'] ?? [];

        $preferencias['avisos_dispensados'] = array_values(
            array_unique([...$dispensados, $aviso->id]),
        );

        $usuario->forceFill(['preferencias' => $preferencias])->save();

        return response()->json(['dispensado' => true]);
    }
}
