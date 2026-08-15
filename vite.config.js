import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/*
 * Tipografia do Pulso — Sora nos titulos, Inter no texto e nos numeros.
 *
 * O guia de marca exige fontes auto-hospedadas: a academia pode estar em
 * conexao instavel e o painel nao pode cair para fonte de sistema no meio do
 * expediente. O provider `fontsource` traz os arquivos pelo npm, entao o build
 * na VPS tambem nao depende de rede externa.
 *
 * Subconjuntos latin + latin-ext cobrem acentuacao e cedilha do portugues.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                fontsource('Sora', {
                    weights: [600, 700],
                    subsets: ['latin', 'latin-ext'],
                    variable: '--fonte-titulo-web',
                    fallbacks: ['Segoe UI', 'system-ui', 'sans-serif'],
                }),
                fontsource('Inter', {
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext'],
                    variable: '--fonte-texto-web',
                    fallbacks: ['Segoe UI', 'system-ui', 'sans-serif'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
