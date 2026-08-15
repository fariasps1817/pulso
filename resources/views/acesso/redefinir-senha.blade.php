<x-layout.acesso
    titulo="Nova senha"
    chamada="Criar uma senha nova"
    apoio="Escolha uma senha de pelo menos 8 caracteres."
>
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.campo
            nome="email"
            rotulo="E-mail"
            tipo="email"
            :valor="$request->email"
            :obrigatorio="true"
            autocomplete="username"
            readonly
        />

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-sm font-medium text-texto-2">Nova senha</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="new-password"
                autofocus
                @if ($errors->has('password')) aria-invalid="true" @endif
                class="min-h-toque w-full rounded-md border bg-superficie px-3.5 text-base text-texto
                       transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco
                       {{ $errors->has('password') ? 'border-vencido-forte' : 'border-borda-forte' }}"
            >
            @error('password')
                <p class="flex items-start gap-1.5 text-sm text-vencido-texto">
                    <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor" aria-hidden="true" focusable="false">
                        <path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9 5.5h2v6H9v-6Zm0 7.5h2v2H9v-2Z"/>
                    </svg>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password_confirmation" class="text-sm font-medium text-texto-2">Repita a nova senha</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie px-3.5
                       text-base text-texto transition-colors
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
            >
        </div>

        <x-ui.botao tipo="submit" variante="primario" tamanho="grande" class="w-full">
            Salvar a nova senha
        </x-ui.botao>
    </form>
</x-layout.acesso>
