/**
 * Bundle público — site institucional e telas de acesso.
 *
 * Aqui NÃO entram Livewire nem Alpine: a página inicial e o login não
 * precisam deles, e a academia pode estar numa conexão instável. O painel
 * carrega o bundle próprio (painel.js).
 */

import { iniciar as iniciarTema } from './tema';
import { iniciar as iniciarModais } from './modais';

iniciarTema();
iniciarModais();
