<x-layout.acesso
    titulo="Entrar"
    chamada="Entrar no Pulso"
    apoio="Use o e-mail cadastrado na sua academia."
>
    {{--
        Erro de autenticacao aparece uma vez, no topo, sem dizer se foi o e-mail
        ou a senha que errou — informar qual dos dois entrega ao atacante quais
        e-mails existem no sistema.
    --}}
    @if ($errors->has('email'))
        <div role="alert"
             class="mb-5 flex items-start gap-2 rounded-md border border-vencido-borda bg-vencido-fundo p-3.5 text-sm text-vencido-texto">
            <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M10 5.5v6M10 14.5v.5M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z" />
            </svg>
            <span>{{ $errors->first('email') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
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

        <div class="flex flex-col gap-1.5">
            <div class="flex items-baseline justify-between gap-3">
                <label for="password" class="text-sm font-medium text-texto-2">Senha</label>

                @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::resetPasswords()))
                    <a href="{{ route('password.request') }}"
                       tabindex="-1"
                       class="rounded-sm text-sm text-acao hover:underline
                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Esqueci a senha
                    </a>
                @endif
            </div>

            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                @if ($errors->has('password')) aria-invalid="true" @endif
                class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie px-3.5
                       text-base text-texto transition-colors placeholder:text-texto-mudo
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
            >
        </div>

        <label class="flex min-h-toque cursor-pointer items-center gap-2.5 text-texto-2">
            <input type="checkbox" name="remember" value="1"
                   class="size-5 rounded-sm border-borda-forte text-acao
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
            <span>Continuar conectado neste aparelho</span>
        </label>

        <x-ui.botao tipo="submit" variante="primario" tamanho="grande" class="w-full">
            Entrar
        </x-ui.botao>
    </form>

    <p class="mt-6 border-t border-borda pt-5 text-sm text-texto-mudo">
        Ainda não usa o Pulso na sua academia?
        <a href="{{ route('site.inicio') }}#contato"
           class="rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
        >Fale com a gente</a>.
    </p>
</x-layout.acesso>
