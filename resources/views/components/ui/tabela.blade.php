@props([
    /* Cabeçalhos: array de string, ou de ['rotulo' => ..., 'alinhamento' => 'direita'] */
    'colunas' => [],
])

{{--
    Tabela que vira lista de cartões no celular.

    Cada célula do corpo precisa de `data-rotulo="Nome da coluna"`: abaixo de
    768px o cabeçalho some e esse atributo vira o rótulo à esquerda, dentro do
    cartão. É CSS puro (ver resources/css/app.css) — sem JavaScript, sem
    duplicar a marcação, e imprime bem.

    Uso:
        <x-ui.tabela :colunas="['Aluno', 'Plano', ['rotulo' => 'Valor', 'alinhamento' => 'direita']]">
            <tr>
                <td data-rotulo="Aluno">Jose Maria da Silva</td>
                <td data-rotulo="Plano">Mensal</td>
                <td data-rotulo="Valor" class="numeros text-right">R$ 129,90</td>
            </tr>
        </x-ui.tabela>
--}}

<div {{ $attributes->merge(['class' => 'tabela-responsiva-container overflow-x-auto rounded-lg border border-borda bg-superficie']) }}>
    <table class="tabela-responsiva w-full text-left">
        <thead class="border-b border-borda">
            <tr>
                @foreach ($colunas as $coluna)
                    @php
                        $rotulo = is_array($coluna) ? $coluna['rotulo'] : $coluna;
                        $direita = is_array($coluna) && ($coluna['alinhamento'] ?? null) === 'direita';
                    @endphp
                    <th scope="col"
                        class="px-4 py-3 text-sm font-medium whitespace-nowrap text-texto-2 {{ $direita ? 'text-right' : '' }}">
                        {{ $rotulo }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y divide-borda">
            {{ $slot }}
        </tbody>
    </table>
</div>
