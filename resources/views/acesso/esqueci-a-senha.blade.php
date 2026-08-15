<x-layout.acesso
    titulo="Esqueci a senha"
    chamada="Recuperar acesso"
    apoio="Informe o e-mail cadastrado e enviaremos um link para você criar uma senha nova."
>
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.campo
            nome="email"
            rotulo="E-mail"
            tipo="email"
            :obrigatorio="true"
            autocomplete="username"
            autofocus
            placeholder="voce@academia.com.br"
        />

        <x-ui.botao tipo="submit" variante="primario" tamanho="grande" class="w-full">
            Enviar link de recuperação
        </x-ui.botao>
    </form>

    <p class="mt-6 border-t border-borda pt-5 text-sm text-texto-mudo">
        Lembrou a senha?
        <a href="{{ route('login') }}"
           class="rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
        >Voltar para o login</a>.
    </p>
</x-layout.acesso>
