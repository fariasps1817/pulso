# Pulso — convenções de interface

O que vale em **toda** tela do sistema. Complementa [`../marca/README.md`](../marca/README.md), que trata de cor, tipografia e tom de voz; aqui é comportamento.

Documento de trabalho — versão 1, agosto/2026.

---

## 1. Premissa: a recepção trabalha em pé

A equipe usa o sistema no balcão, muitas vezes no celular, com o aluno esperando. Isso não é detalhe de acabamento — é o que decide cada regra abaixo.

- Alvo de toque nunca menor que **44 px**.
- No celular, a navegação principal fica **embaixo**: é onde o polegar alcança.
- Resumo antes do detalhe. Tabela financeira vira **lista de cartões**, com o valor à direita em `tabular-nums`.
- Estado sempre em pílula com **ícone + texto + cor**. Pontinho colorido sozinho é proibido.

---

## 2. Campos de formulário

### 2.1 Máscara, limite e teclado

Todo campo com formato conhecido tem os três ao mesmo tempo: máscara visual, limite rígido de caracteres e teclado certo no celular.

| Campo | Máscara | Dígitos | `inputmode` | Observação |
|---|---|:--:|---|---|
| CPF | `000.000.000-00` | 11 | `numeric` | Valida os dígitos verificadores ao sair do campo |
| CNPJ | `00.000.000/0000-00` | 14 | `numeric` | |
| Celular / WhatsApp | `(00) 00000-0000` | 11 | `numeric` | |
| Telefone fixo | `(00) 0000-0000` | 10 | `numeric` | |
| CEP | `00000-000` | 8 | `numeric` | Busca o endereço ao completar |
| Data | `00/00/0000` | 8 | `numeric` | **Sem calendário** — ver §2.2 |
| Dinheiro | `R$ 0.000,00` | — | `decimal` | Alinhado à direita, `tabular-nums` |

**Só dígitos.** Nos campos numéricos, tecla que não seja de `0` a `9` é recusada — não aceita e depois limpa, simplesmente não entra. Vale também para colar: o texto colado é filtrado.

**O que vai para o banco são só os dígitos.** A máscara é da tela. Gravar `085.996.085-96` obrigaria a limpar a pontuação em toda busca, todo relatório e toda integração. O banco guarda `08599608596`; a exibição formata.

### 2.2 Data se digita, não se escolhe

Campo de data é **texto com máscara**, nunca `<input type="date">` e nunca calendário que abre sozinho.

*Por quê:* no celular, `type="date"` abre o seletor nativo, e escolher uma data de nascimento de 1974 exige rolar dezenas de telas. Com campo de texto e `inputmode="numeric"`, o teclado numérico sobe e a recepcionista digita `15081974` em dois segundos.

Um botão discreto de calendário **pode** existir ao lado, para escolher datas próximas (vencimento, agendamento). O que não pode é ser o único caminho.

### 2.3 Endereço vem de API, não de digitação

| Campo | Origem |
|---|---|
| CEP → logradouro, bairro, cidade, UF | [ViaCEP](https://viacep.com.br/) — `https://viacep.com.br/ws/{cep}/json/` |
| Lista de estados | [IBGE](https://servicodados.ibge.gov.br/api/v1/localidades/estados) |
| Municípios de um estado | IBGE — `/api/v1/localidades/estados/{uf}/municipios` |

Regras:

- **Cidade e UF nunca são texto livre.** Digitação livre produz "Fortaleza", "fortaleza", "Fortaleza-CE" e "Frotaleza" na mesma base, e nenhum relatório por cidade funciona depois.
- As listas do IBGE são **guardadas em cache** (mudam raramente). O sistema não pode travar um cadastro porque uma API pública caiu.
- O ViaCEP preenche, mas **não trava**: CEP não encontrado deixa o usuário digitar à mão. Aluno em rua nova não pode ficar sem cadastro.
- Nenhuma dessas chamadas bloqueia o envio do formulário.

### 2.4 Nomes gravados em caixa de título

Digitou como digitou, grava `Jose Maria da Silva`. Regra completa em [`../dominio/README.md`](../dominio/README.md) §5.9. A normalização acontece ao gravar, não ao exibir.

### 2.5 Erros

- Nunca só a borda vermelha: **ícone + texto**, ligados ao campo por `aria-describedby`.
- O texto diz a saída, não a culpa: *"Não foi possível ler a digital. Tente de novo ou use o cartão."*
- Erro de formulário devolve o foco ao primeiro campo com problema.
- Campo obrigatório é o padrão; o opcional é que vem marcado `(opcional)`. Numa ficha de aluno quase tudo é obrigatório — marcar asterisco em tudo é ruído.

---

## 3. Padrão de CRUD

Toda entidade segue o mesmo caminho. Quem aprendeu a mexer em alunos sabe mexer em planos.

```
  LISTA                      DETALHES                    FORMULÁRIO
  ┌────────────────────┐     ┌────────────────────┐      ┌──────────────────┐
  │ Alunos      [Novo] │     │ ‹ Alunos           │      │ ‹ Cancelar       │
  │ ┌────────────────┐ │     │ Jose Maria da Silva│      │                  │
  │ │ 🔍 buscar      │ │     │ [Editar] [⋯]       │      │ Nome ..........  │
  │ └────────────────┘ │     │                    │      │ CPF  ..........  │
  │                    │     │ Dados pessoais     │      │ Nascimento ....  │
  │ Jose Maria  ✓ Em   │────▶│ Matrícula ativa    │─────▶│ WhatsApp ......  │
  │ Ana Beatriz ! Venc │     │ Mensalidades       │      │                  │
  │ Carlos Ed.  ⏱ A ven│     │ Frequência         │      │ [Salvar]         │
  │                    │     │ Biometria          │      │                  │
  │ ‹ 1 2 3 ›          │     │                    │      │                  │
  └────────────────────┘     └────────────────────┘      └──────────────────┘
```

**Lista**
- Botão **Novo** no canto superior direito, sempre no mesmo lugar.
- Busca no topo, com resultado enquanto se digita.
- **O nome é o link** para os detalhes. Não há coluna de ícones de ação disputando o toque.
- No celular a tabela vira lista de cartões.
- Filtro por situação em pílulas, não em combo escondido.
- Paginação embaixo, com o total sempre visível ("312 alunos").

**Detalhes**
- Título é o nome do registro; abaixo, as pílulas de estado.
- Botão **Editar** no topo. Ações menos usadas (excluir, suspender) em menu `⋯`, para não errar o toque.
- Conteúdo em blocos, não em formulário desabilitado.
- Voltar sempre visível.

**Formulário**
- Usado tanto para criar quanto para editar — mesma tela, mesmo layout.
- Campos em grade de quartos — ver §3.1.
- **Salvar** fixo no rodapé em telas longas: rolar até o fim para salvar é motivo de cadastro perdido.
- Confirmação ao sair com alteração não salva.
- Exclusão sempre com modal que diz o que será perdido, e digitando o nome quando for irreversível.

### 3.1 Grade de formulário: quartos

Todo formulário usa `<x-ui.grade-formulario>`, que é uma grade de **quatro colunas** no desktop. Cada campo declara quanto ocupa:

| `largura` | Ocupa | Para quê |
|---|---|---|
| `25` | ¼ | CPF, data, telefone, CEP, número, estado |
| `50` | ½ | E-mail, rua, bairro, complemento |
| `75` | ¾ | Nome completo, cidade |
| `100` | tudo | Observações, campos longos |

```blade
<x-ui.grade-formulario>
    <x-ui.campo   largura="75" nome="nome" rotulo="Nome completo" />
    <x-ui.selecao largura="25" nome="sexo" rotulo="Sexo" :opcoes="$sexos" />
</x-ui.grade-formulario>
```

**A largura acompanha o conteúdo esperado.** "Sexo" tem três opções e não merece o mesmo espaço de um nome completo — campo largo demais sugere que cabe mais texto do que cabe. Quatro campos curtos numa linha (CPF, nascimento, WhatsApp, telefone) leem melhor do que dois pares empilhados.

No celular tudo vira **uma coluna só**, qualquer que seja a largura declarada: uma fração de 4 numa tela de 360 px produziria campo intocável.

**Nenhum formulário escreve `grid-cols` à mão.** As classes de span vivem em `x-ui.grupo-campo`, escritas literalmente — o Tailwind varre o código-fonte, e uma classe montada em tempo de execução nunca seria gerada, deixando o campo sem largura nenhuma.

---

## 4. Layout do painel

```
┌──────────────────────────────────────────────────────┐
│ [≡] Pulso        Unidade: Centro ▾      🔔  ☾  Ana ▾ │  cabeçalho
├────────────┬─────────────────────────────────────────┤
│ ◉ Radar    │  ⚠ Sua assinatura vence em 5 dias.  [×] │  aviso
│ ○ Alunos   │                                         │
│ ○ Matríc.  │  Alunos                        [ Novo ] │
│ ○ Mensal.  │  ┌─────────────────────────────────┐    │
│ ○ Planos   │  │ 🔍                              │    │
│ ○ Acesso   │  └─────────────────────────────────┘    │
│ ○ Config.  │                                         │
│            │                                         │
│ [ ‹ ]      │                                         │  recolher
└────────────┴─────────────────────────────────────────┘
```

- **Barra lateral recolhível.** O estado (recolhida/expandida) é preferência do usuário e viaja com ele — `users.preferencias`.
- **Seletor de unidade** no cabeçalho, só aparece para quem tem acesso a mais de uma.
- **Avisos do super administrador** no topo do conteúdo, acima do título. Dispensável quando informativo; fixo quando é bloqueio iminente.
- No celular: barra lateral vira gaveta, e os quatro itens mais usados vão para uma barra **inferior**.
- Menu do usuário: perfil, preferências, sair.

---

## 5. Componentes

Todos visíveis em **`/catalogo`** (fora de produção), nos dois temas.

| Componente | Blade | Situação |
|---|---|:--:|
| Logo | `x-marca.logo` | ✅ |
| Botão | `x-ui.botao` | ✅ |
| Campo de texto | `x-ui.campo` | ✅ |
| Campo com máscara | `x-ui.campo-mascara` | ✅ |
| Campo de dinheiro | `x-ui.campo-dinheiro` | ✅ |
| Área de texto | `x-ui.area-texto` | ✅ |
| Seletor | `x-ui.selecao` | ✅ |
| Caixa de seleção | `x-ui.caixa-selecao` | ✅ |
| Interruptor | `x-ui.interruptor` | ✅ |
| Envio de imagem | `x-ui.envio-imagem` | ✅ |
| Invólucro de campo | `x-ui.grupo-campo` | ✅ |
| Grade de formulário | `x-ui.grade-formulario` | ✅ |
| Pílula de estado | `x-ui.pilula` | ✅ |
| Cartão | `x-ui.cartao` | ✅ |
| Aviso | `x-ui.aviso` | ✅ |
| Aviso rápido | `x-ui.notificacoes` | ✅ |
| Tabela responsiva | `x-ui.tabela` | ✅ |
| Paginação | `x-ui.paginacao` | ✅ |
| Barra de busca | `x-ui.busca` | ✅ |
| Cabeçalho de página | `x-ui.cabecalho-pagina` | ✅ |
| Abas | `x-ui.abas` + `x-ui.painel-aba` | ✅ |
| Menu suspenso | `x-ui.menu` + `x-ui.menu-item` | ✅ |
| Confirmação | `x-ui.modal` | ✅ |
| Estado vazio | `x-ui.estado-vazio` | ✅ |
| Esqueleto de carregamento | `x-ui.esqueleto` | ✅ |
| Alternador de tema | `x-ui.alternador-tema` | ✅ |
| Layout do painel | `x-layout.painel` | ✅ |
| Item de navegação | `x-painel.item-navegacao` | ✅ |
| Seletor de cidade/UF (IBGE) | — | ⬜ com o CRUD de endereço |
| Seletor com busca | — | ⬜ quando houver listas grandes |

### 5.1 Dois bundles, de propósito

| Bundle | Quem carrega | Peso |
|---|---|---|
| `app.js` | Site institucional, login | ~0,6 kB comprimido |
| `painel.js` | Painel e catálogo | ~87 kB comprimido |

Alpine e Livewire só entram no painel. A página inicial precisa abrir rápido numa conexão ruim, e a tela de login não usa nada disso.

### 5.2 Duas armadilhas que já custaram caro aqui

**O Alpine só inicializa dentro de uma raiz `x-data`.** Um `<input x-mask="...">` solto na página nunca é processado — o campo fica sem máscara e nada indica o motivo. Por isso `x-ui.campo-mascara` e `x-ui.campo-dinheiro` trazem um `x-data` vazio. Ao criar componente novo com diretiva do Alpine, lembre disso.

**O script do `@livewireScripts` roda antes do nosso bundle.** Ele é clássico e executa durante a análise do HTML; o nosso é módulo e roda depois. Resultado: o Alpine já teria iniciado quando fôssemos registrar os plugins, e `x-mask` jamais existiria. Por isso o Livewire é importado dentro de `resources/js/painel.js`, e o layout usa `@livewireScriptConfig` no lugar de `@livewireScripts`.

**Propriedade em português vs. atributo HTML em inglês.** Os componentes usam `tipo`, `rotulo`, `variante`; o HTML usa `type`, `label`. Escrever `type="submit"` — o reflexo de quem conhece HTML — fazia o valor cair nos atributos extras, e o botão saía com **dois** `type`: o do componente primeiro, o de quem chamou depois. O navegador usa o primeiro, então o botão não enviava o formulário. Sem erro e sem aviso — foi assim que o "Sair" ficou inerte.

`x-ui.botao`, `x-ui.campo` e `x-ui.menu-item` passaram a aceitar as duas grafias e a remover o atributo duplicado. **Ao criar componente novo que renderiza um atributo a partir de propriedade, faça o mesmo.**

**Classe estática do Tailwind briga com `:class` do Alpine.** `class="translate-x-1"` junto de `:class="ligado ? 'translate-x-6' : 'translate-x-1'"` não alterna: quem decide é a ordem no CSS, não a do atributo. O valor que muda vive só no binding.

---

## 6. Acessibilidade — o piso

Já verificado no guia de marca; aqui é o que se cobra em cada tela nova:

- Contraste **≥ 4,5:1** em texto, **≥ 3:1** em contorno de campo e anel de foco.
- Foco sempre visível, e a ordem de tabulação segue a leitura.
- Nunca a cor sozinha carregando informação.
- Todo campo com rótulo de verdade — `placeholder` não é rótulo.
- `prefers-reduced-motion` respeitado.
- Tabela com cabeçalho marcado, para leitor de tela anunciar a coluna.
