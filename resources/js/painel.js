/**
 * Bundle do painel — Livewire, Alpine e os componentes interativos.
 *
 * POR QUE EMPACOTAR O LIVEWIRE AQUI, em vez de usar @livewireScripts
 * -----------------------------------------------------------------
 * O script que o @livewireScripts injeta é clássico e roda durante a análise
 * do HTML; o nosso, sendo módulo, roda depois. Resultado: o Alpine do Livewire
 * já teria iniciado quando fôssemos registrar os plugins, e `x-mask` e
 * `x-dinheiro` nunca chegariam a existir — foi exatamente o que aconteceu na
 * primeira tentativa.
 *
 * Importando o Livewire para dentro deste módulo, a ordem passa a ser nossa:
 * registramos os plugins e só então chamamos Livewire.start(). No layout,
 * @livewireScriptConfig substitui @livewireScripts.
 */

import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import mask from '@alpinejs/mask';

import { registrar as registrarMascaras } from './mascaras';
import { registrar as registrarNotificacoes } from './notificacoes';

Alpine.plugin(mask);

registrarMascaras(Alpine);
registrarNotificacoes(Alpine);

Livewire.start();
