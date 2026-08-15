@props([
    'titulo' => null,
    'descricao' => 'Sistema de gestão de academias: matrículas, mensalidades, controle de acesso por catraca e alertas de inadimplência e evasão.',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $descricao }}">
    <meta name="theme-color" content="#0A5673">

    <title>{{ $titulo ? $titulo.' · '.config('pulso.marca.nome') : config('pulso.marca.nome').' — '.config('pulso.marca.assinatura') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icone-app.svg') }}">

    {{--
        Aplicacao do tema ANTES da primeira pintura. Sem este bloco sincrono a
        tela pisca branca para quem usa o tema escuro. Tres estados: "sistema"
        nao marca nada no HTML e se resolve por prefers-color-scheme.
    --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('pulso.tema');
                if (tema === 'escuro') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (tema === 'claro') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
                document.documentElement.dataset.temaEscolhido = tema || 'sistema';
            } catch (e) {
                /* localStorage bloqueado: segue o tema do sistema. */
            }
        })();
    </script>

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh bg-fundo text-texto antialiased']) }}>
    {{ $slot }}
</body>
</html>
