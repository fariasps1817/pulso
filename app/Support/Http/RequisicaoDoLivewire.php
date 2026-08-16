<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * É uma chamada interna do Livewire?
 *
 * Existe porque acertar isso "no olho" já falhou: dois middlewares liberavam o
 * caminho `livewire/*`, e o endpoint real é `livewire-635c8419/update` — o
 * Livewire OFUSCA o prefixo, e ele muda entre instalações. O padrão nunca
 * casava, e toda interação virava um redirecionamento.
 *
 * A identificação é pelo NOME da rota, que o Livewire mantém estável mesmo
 * mudando o caminho. O curinga à esquerda cobre o prefixo que a versão 4
 * acrescentou (`default-livewire.update`).
 *
 * Qualquer middleware que desvie navegação — troca de senha obrigatória,
 * separação de áreas — precisa deixar estas passar. Desviar a requisição
 * interna não protege nada: ela vem do mesmo usuário, na mesma sessão, para a
 * mesma tela que ele já tem aberta. Só quebra a tela.
 */
final class RequisicaoDoLivewire
{
    public static function ehInterna(Request $requisicao): bool
    {
        return $requisicao->routeIs(
            '*livewire.update',
            '*livewire.upload-file',
            '*livewire.preview-file',
        );
    }
}
