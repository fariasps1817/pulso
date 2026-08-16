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
- Uma coluna. Duas colunas em tela de balcão só quando os campos são curtos e relacionados.
- **Salvar** fixo no rodapé em telas longas: rolar até o fim para salvar é motivo de cadastro perdido.
- Confirmação ao sair com alteração não salva.
- Exclusão sempre com modal que diz o que será perdido, e digitando o nome quando for irreversível.

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

## 5. Componentes que faltam construir

Existem hoje: botão, campo de texto, pílula de estado, cartão, logo e alternador de tema.

| Componente | Para quê |
|---|---|
| Layout do painel | Cabeçalho, barra lateral, área de conteúdo |
| Barra lateral | Recolhível, com estado salvo no perfil |
| Cabeçalho de página | Título, subtítulo, ações à direita |
| Tabela responsiva | Vira lista de cartões abaixo de certa largura |
| Paginação | Com total de registros |
| Campo com máscara | CPF, CNPJ, telefone, CEP, dinheiro |
| Campo de data | Texto com máscara e teclado numérico |
| Seletor (combo) com busca | Plano, unidade, profissional |
| Seletor de cidade/UF | Alimentado pelo IBGE, com cache |
| Área de texto | Observações |
| Caixa de seleção e opção | Marcações do cadastro |
| Interruptor | Ativo/inativo |
| Modal de confirmação | Exclusão e ações irreversíveis |
| Aviso de topo | Recados do super administrador |
| Abas | Blocos da tela de detalhes |
| Upload de imagem | Foto do aluno, logo da academia |
| Estado vazio | "Nenhum aluno ainda" com o caminho para o primeiro |
| Indicador de carregamento | Esqueleto, não roda girando |
| Notificação de ação | "Pagamento registrado" |
| Barra de busca | Com resultado enquanto digita |

**São ~19 componentes.** Construí-los antes das telas de CRUD evita reescrever cada um três vezes.

---

## 6. Acessibilidade — o piso

Já verificado no guia de marca; aqui é o que se cobra em cada tela nova:

- Contraste **≥ 4,5:1** em texto, **≥ 3:1** em contorno de campo e anel de foco.
- Foco sempre visível, e a ordem de tabulação segue a leitura.
- Nunca a cor sozinha carregando informação.
- Todo campo com rótulo de verdade — `placeholder` não é rótulo.
- `prefers-reduced-motion` respeitado.
- Tabela com cabeçalho marcado, para leitor de tela anunciar a coluna.
