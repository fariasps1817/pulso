@php
    use App\Support\Academia\Papeis;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Usuários"
        subtitulo="Quem tem acesso ao Pulso nesta academia."
        :voltar-para="['rotulo' => 'Configurações', 'url' => route('configuracoes.painel')]"
    >
        @can('create', App\Models\User::class)
            <x-ui.botao :href="route('usuarios.novo')">Novo usuário</x-ui.botao>
        @endcan
    </x-ui.cabecalho-pagina>

    @if ($senhaTemporaria)
        {{-- Ocupa o topo da tela: sair daqui sem copiar significa gerar outra.
             Guardá-la em algum lugar para poder mostrar de novo seria pior. --}}
        <x-ui.cartao destaque class="flex flex-col gap-4">
            <div>
                <h2 class="font-titulo text-lg text-texto">Senha nova para {{ $senhaDe }}</h2>
                <p class="mt-1 text-texto-2">
                    Ela é temporária: no primeiro acesso o sistema exige que a própria pessoa
                    escolha a dela. As sessões que estavam abertas foram encerradas.
                </p>
            </div>

            <p class="numeros rounded-md border border-borda-forte bg-superficie-2 px-4 py-3
                      text-center font-titulo text-2xl tracking-wider text-texto select-all">
                {{ $senhaTemporaria }}
            </p>

            <p class="text-sm text-texto-mudo">Esta senha não será mostrada de novo.</p>

            <div>
                <x-ui.botao wire:click="fecharSenha" variante="secundario">Já anotei</x-ui.botao>
            </div>
        </x-ui.cartao>
    @endif

    <div class="relative">
        <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
             fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
        </svg>
        <input type="search" wire:model.live.debounce.400ms="termo" placeholder="Buscar por nome ou e-mail…"
               aria-label="Buscar usuário"
               class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                      text-base text-texto placeholder:text-texto-mudo
                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
    </div>

    @if ($usuarios->isEmpty())
        <x-ui.estado-vazio
            titulo="Ninguém encontrado"
            descricao="Nenhum usuário corresponde à busca."
            icone="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0"
        />
    @else
        <x-ui.tabela :colunas="[
            'Nome',
            'Papel',
            'Unidade',
            ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
        ]">
            @foreach ($usuarios as $pessoa)
                <tr wire:key="usuario-{{ $pessoa->id }}" class="transition-colors hover:bg-superficie-2">
                    <td data-rotulo="Nome" data-principal class="px-4 py-3">
                        @can('update', $pessoa)
                            <a href="{{ route('usuarios.editar', $pessoa) }}"
                               class="rounded-sm text-acao hover:underline
                                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                {{ $pessoa->name }}
                            </a>
                        @else
                            <span class="text-texto">{{ $pessoa->name }}</span>
                        @endcan
                        <span class="block text-sm text-texto-mudo">{{ $pessoa->email }}</span>
                    </td>

                    <td data-rotulo="Papel" class="px-4 py-3 text-texto-2">
                        {{ Papeis::rotulo($pessoa->getRoleNames()->first()) }}
                        @if ($pessoa->id === auth()->id())
                            <span class="block text-sm text-texto-mudo">você</span>
                        @endif
                    </td>

                    <td data-rotulo="Unidade" class="px-4 py-3 text-texto-2">
                        @if ($pessoa->acessa_todas_unidades)
                            Todas
                        @else
                            {{ $pessoa->unidadePadrao?->nome ?? '—' }}
                            @if ($pessoa->pode_alternar_unidade)
                                <span class="block text-sm text-texto-mudo">pode alternar</span>
                            @endif
                        @endif
                    </td>

                    <td data-rotulo="Situação" class="px-4 py-3 text-right">
                        @can('redefinirSenha', $pessoa)
                            <button type="button" wire:click="redefinirSenha({{ $pessoa->id }})"
                                    wire:confirm="Gerar uma senha nova para {{ $pessoa->name }}? A senha atual deixa de valer na hora e as sessões abertas caem."
                                    class="mr-3 rounded-sm text-sm text-acao hover:underline
                                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                Nova senha
                            </button>
                        @endcan

                        @if (! $pessoa->ativo)
                            <x-ui.pilula estado="vencido">Inativo</x-ui.pilula>
                        @elseif ($pessoa->deve_trocar_senha)
                            {{-- Estado real e útil: é a resposta para "mandei a
                                 senha e a pessoa ainda não entrou". --}}
                            <x-ui.pilula estado="avencer">Senha temporária</x-ui.pilula>
                        @else
                            <x-ui.pilula estado="pago">Ativo</x-ui.pilula>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.tabela>
    @endif
</div>
