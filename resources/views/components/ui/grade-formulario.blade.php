{{--
    Grade de formulário: quatro colunas no desktop, uma no celular.

    Cada campo declara quanto ocupa — 25, 50, 75 ou 100 por cento — pela
    propriedade `largura`. A largura acompanha o CONTEÚDO esperado: "Sexo",
    com três opções, não merece a mesma largura de "Nome completo". Campo
    largo demais sugere que cabe mais texto do que cabe.

    No celular tudo vira uma coluna só: qualquer fração de 4 numa tela de
    360px produziria campo intocável.

    Uso:
        <x-ui.grade-formulario>
            <x-ui.campo nome="nome" rotulo="Nome completo" largura="75" />
            <x-ui.selecao nome="sexo" rotulo="Sexo" largura="25" :opcoes="..." />
        </x-ui.grade-formulario>

    Nenhum formulário do sistema escreve grid-cols à mão.
--}}

<div {{ $attributes->merge(['class' => 'grid gap-5 md:grid-cols-4']) }}>
    {{ $slot }}
</div>
