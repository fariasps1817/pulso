{{--
    Os recados do Pulso para esta academia.

    Aparecem no topo de TODA tela do painel — é o único canal do fornecedor com
    o cliente dentro do sistema, e o caso que ele existe para atender é "sua
    assinatura vence em cinco dias" chegando antes do bloqueio, sem ninguém
    precisar telefonar.

    O aviso NÃO DISPENSÁVEL é o ponto do recurso: um alerta de bloqueio
    iminente que some ao ser fechado deixa o dono descobrir na segunda-feira,
    com a academia parada. Quem decide isso é quem escreve o aviso.
--}}

@php
    $usuario = auth()->user();

    // Os não dispensáveis primeiro: são os que não podem passar batidos.
    $avisos = $usuario?->academia_id === null
        ? collect()
        : App\Models\AvisoAcademia::query()
            ->visiveisPara($usuario->academia_id)
            ->orderBy('dispensavel')
            ->orderBy('id')
            ->get();

    $dispensados = $usuario?->preferencias['avisos_dispensados'] ?? [];

    $avisos = $avisos->reject(
        fn ($aviso) => $aviso->dispensavel && in_array($aviso->id, $dispensados, true),
    );
@endphp

@if ($avisos->isNotEmpty())
    <div class="flex flex-col gap-3">
        @foreach ($avisos as $aviso)
            <x-ui.aviso
                :tipo="$aviso->tipo"
                :titulo="$aviso->titulo"
                :dispensavel="$aviso->dispensavel"
                :dispensar-em="$aviso->dispensavel ? route('avisos.dispensar', $aviso) : null"
            >
                {{ $aviso->mensagem }}
            </x-ui.aviso>
        @endforeach
    </div>
@endif
