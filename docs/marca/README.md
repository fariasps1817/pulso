# Pulso — bases de marca e identidade visual

Sistema web de gestão de academias · PHP/Laravel + PostgreSQL · pt-BR · `America/Fortaleza`
Documento de planejamento — versão 1, agosto/2026.

---

## 1. O que está decidido aqui

| Item | Decisão |
|---|---|
| Nome | **Pulso** |
| Assinatura | *gestão de academias* |
| Slogan | **O pulso da sua academia.** |
| Cor primária | **Azul Maré** `#0A6B8C` |
| Cor secundária | **Amarelo Sol** `#FAB01B` |
| Símbolo | Cápsula Maré com traçado de pulso em Sol |
| Tipografia | Sora (títulos) + Inter (texto e números) |
| Temas | Claro e escuro, ambos verificados em WCAG 2.1 AA |

Arquivos desta pasta: `tokens.css` (fonte da verdade das cores), `tokens.json` (mesma paleta em formato legível por ferramenta), `assets/` (SVGs do logo, ícones e estados).

---

## 2. O nome

### 2.1 O que o mercado já ocupa

Os sistemas consolidados no Brasil concentram-se em dois padrões de nome: **descritivo com sufixo "fit"** (Tecnofit, Next Fit, GoFit, FitSystem) e **substantivo abstrato curto** (Pacto, Evo). Segundo o material publicado pelos próprios fornecedores, Tecnofit informa mais de 16.500 clientes e Pacto mais de 5.800 negócios fitness em 2026, com mensalidades de sistema na faixa de R$ 150 a R$ 1.200 conforme os módulos.

Duas leituras disso:

1. **Sufixo "fit" está saturado.** Um nome novo com "fit" nasce parecido com quatro concorrentes maiores e sem força de registro no INPI.
2. **O segundo padrão está aberto.** "Pacto" provou que uma palavra comum curta funciona como marca séria nesse mercado — é o espaço em que o nome novo tem chance de virar referência.

### 2.2 Critérios usados

Peso maior para o que gera custo se errar: registrabilidade (nome genérico não se defende), clareza ao telefone (a equipe vai ditar o nome para aluno e fornecedor) e amplitude (o alvo inicial é o Nordeste, mas o nome não pode virar teto para expandir).

### 2.3 Lista curta avaliada

Nota de 0 a 5 em cada critério; a coluna final é a média ponderada.

| Nome | Sentido | Registro | Fala/escrita | Amplitude | Encaixe no produto | Nota |
|---|---|:--:|:--:|:--:|:--:|:--:|
| **Pulso** | batimento (frequência), *pulso firme* (gestão), punho (biometria) | 4 | 5 | 5 | 5 | **4,8** |
| Mandacaru | resistência do sertão, floresce na seca | 5 | 3 | 3 | 3 | 3,5 |
| Giro | a catraca gira, o capital de giro circula | 2 | 5 | 4 | 5 | 3,9 |
| Jangada | símbolo de Fortaleza, quem conduz | 5 | 4 | 3 | 2 | 3,4 |
| Alísio | os ventos constantes do Nordeste | 5 | 3 | 4 | 2 | 3,3 |
| Farol | orientação, monitoramento (Mucuripe) | 2 | 5 | 4 | 4 | 3,6 |
| Aurora | onde o sol nasce primeiro no país | 2 | 4 | 4 | 2 | 2,9 |
| Cadência | o ritmo do treino e do caixa | 4 | 3 | 4 | 4 | 3,7 |

### 2.4 Por que Pulso

O produto tem três núcleos — **acesso** (facial/biometria na catraca), **dinheiro** (mensalidades) e **retenção** (frequência) — e "pulso" é a única palavra da lista que cobre os três sem esforço:

- **Batimento** — cada passagem na catraca é uma batida; a academia tem um pulso mensurável, e é exatamente isso que o painel mostra.
- **Pulso firme** — expressão corrente em português para gestão sob controle. O slogan sai pronto do próprio nome.
- **Punho** — onde se mede a pulsação e onde encosta a pulseira. Amarra o nome ao equipamento de acesso.

Duas sílabas, escrita sem ambiguidade, sem acento, sem dígrafo. Ao telefone não precisa soletrar. E, por ser palavra **arbitrária** no ramo (não descreve software de gestão), tem boa força como marca — o oposto de "Giro", que descreveria a catraca e por isso é frágil no INPI.

*Contraponto honesto:* "Pulso" não carrega o Nordeste no nome. A escolha aqui foi deixar a regionalidade na **paleta** (mar e sol) e no **tom de voz**, e manter o nome nacionalmente expansível. Se a preferência for cravar a origem no próprio nome, **Mandacaru** é a segunda opção — mais memorável e mais fácil de registrar, ao custo de quatro sílabas e de um teto simbólico fora do Nordeste.

### 2.5 Verificações que faltam — fazer antes de fechar

Não foi possível consultar registro.br nem a base do INPI deste ambiente (bloqueio de rede), e a busca na web não encontrou sistema de gestão fitness brasileiro chamado Pulso. Isso **não** substitui a busca formal. Antes de investir em identidade:

1. **INPI** — busca de anterioridade nas classes **9** (software) e **42** (SaaS/serviços de TI). A classe 42 é das mais disputadas; considerar também a **41** (serviços de treinamento físico) se houver plano de marca voltada ao aluno.
2. **Domínios** — `pulso.com.br` provavelmente está tomado (palavra comum). Alternativas realistas: `usepulso.com.br`, `pulsoacademias.com.br`, `pulso.app`, `sistemapulso.com.br`. Registre a variante escolhida e as defensivas antes de anunciar.
3. **Redes** — mesmo handle em Instagram e WhatsApp Business.

---

## 3. Arquitetura de marca

Um nome-mãe, módulos em português direto. Nada de nomes fantasia para tela interna: a recepcionista precisa achar o que procura no primeiro clique.

| Peça | Nome | O que é |
|---|---|---|
| Marca-mãe | **Pulso** | A empresa e o sistema |
| Painel administrativo | **Pulso** (web, responsivo) | Alunos, profissionais, matrículas, planos, mensalidades, relatórios |
| Área de alertas | **Radar** | Vencidas, a vencer, baixa frequência, risco de evasão — a tela que a gestão abre primeiro |
| Terminal de acesso | **Pulso Acesso** | Integração facial/biometria ↔ catraca |
| Aluno | **Meu Pulso** | Consulta de plano, vencimento, Pix e frequência |

"Radar" é sub-marca porque é a tela que dá o argumento de venda — vale ter nome próprio. As demais são só rótulos funcionais.

---

## 4. Frase-conceito e slogans

**Frase-conceito** (uso interno, orienta as decisões de produto e comunicação):

> Toda academia tem um pulso: gente entrando, treino acontecendo, dinheiro circulando. Quando esse pulso some do radar, o prejuízo aparece antes do dono perceber. O Pulso mede a batida — acesso por acesso, mensalidade por mensalidade — e avisa a tempo.

**Slogan principal:** **O pulso da sua academia.**

| Uso | Frase |
|---|---|
| Site (hero) | O pulso da sua academia. |
| Comercial / proposta | Gestão com pulso firme. |
| Anúncio (dor) | Passe livre pro aluno. Pulso firme no caixa. |
| Redes sociais | Sua academia no ritmo certo. |
| Institucional (origem) | Feito no Nordeste, no ritmo da sua academia. |
| Assinatura sob o logo | gestão de academias |

Regra: **um slogan por peça**. O principal nunca aparece junto de outro.

---

## 5. Tom de voz

**Direto, próximo e sem jargão.** Quem usa o sistema é recepcionista, gerente e dono — não analista de TI.

- **No produto:** trate por *você*. Frases curtas. Botão diz o que acontece ("Registrar pagamento", e o aviso responde "Pagamento registrado").
- **No marketing:** *a gente* é permitido. Regionalismo com naturalidade, nunca como caricatura — nada de "arretado" em material de venda.
- **Erros explicam a saída:** "Não foi possível ler a digital. Tente de novo ou use o cartão." Sem pedido de desculpas, sem culpar o usuário.
- **Nomeie pelo que a pessoa reconhece:** *mensalidade*, *matrícula*, *catraca* — não *webhook*, *endpoint*, *registro*.

### 5.1 Cobrança — a regra que não se quebra

O aluno inadimplente **não pode ser exposto nem constrangido** (Código de Defesa do Consumidor, art. 42). Isso vale para a mensagem no WhatsApp, para o que aparece na tela da catraca e para o que a recepção fala em voz alta.

Na catraca, acesso negado por inadimplência mostra **"Procure a recepção"** — nunca "Mensalidade vencida" com outros alunos na fila.

### 5.2 Modelos de notificação

O WhatsApp exige modelos aprovados previamente para mensagens iniciadas pela empresa — escreva-os já nesse formato.

**3 dias antes**
> Oi, {nome}! Sua mensalidade da {academia} vence dia {data} — R$ {valor}. Dá pra pagar por Pix aqui: {link}. Bom treino! 💪

**No dia**
> {nome}, sua mensalidade vence hoje ({data}), R$ {valor}. Pix em um toque: {link}

**Vencida (1 lembrete, sem cobrança repetida)**
> {nome}, sua mensalidade de {mês} está em aberto. Pra continuar treinando sem interrupção, é só regularizar: {link}. Qualquer coisa, fala com a gente.

---

## 6. Logo

### 6.1 Construção

Cápsula de cantos suaves (raio 30% do lado) em **Azul Maré**, com um traçado de pulso em **Amarelo Sol**. O traçado entra na horizontal, dá a batida e **sai mais alto do que entrou** — a leitura é batimento *e* crescimento.

O logotipo "PULSO" é **monolinear geométrico desenhado sob medida**: mesma espessura de traço e mesmas pontas arredondadas do símbolo. Não é fonte — é vetor fechado, e não deve ser redigitado.

### 6.2 Arquivos

| Arquivo | Quando usar |
|---|---|
| `assets/logo-horizontal.svg` | Padrão, fundo claro |
| `assets/logo-horizontal-escuro.svg` | Fundo escuro — sem cápsula, pulso em Sol |
| `assets/logo-vertical.svg` | Espaços estreitos, adesivo, camiseta |
| `assets/logo-mono-branco.svg` | Uma cor, fundo escuro ou foto |
| `assets/logo-mono-preto.svg` | Uma cor, impressão econômica, fax/carimbo |
| `assets/simbolo.svg` | Avatar, topo do app, ícone de aba |
| `assets/simbolo-reduzido.svg` | **≤ 24 px** — traçado simplificado e mais grosso |
| `assets/favicon.svg` | Favicon |
| `assets/icone-app.svg` | Ícone de aplicativo, 1024×1024 |
| `assets/acesso-liberado.svg` / `acesso-bloqueado.svg` | Display da catraca |

### 6.3 Regras de uso

- **Respiro:** margem livre em todos os lados igual à altura da cápsula ÷ 2. Nada entra nessa área.
- **Tamanho mínimo:** logo horizontal a partir de **96 px** de largura (24 mm impresso); abaixo disso, use só o símbolo. Símbolo abaixo de 24 px → `simbolo-reduzido.svg`.
- **Proibido:** distorcer, recolorir fora da paleta, aplicar sombra ou contorno, girar, trocar a fonte do logotipo, colocar a versão colorida sobre foto de baixo contraste.

---

## 7. Ícone e estados de acesso

O **ícone de app** é a cápsula com o pulso ocupando 78% do quadro — margem de segurança suficiente para o recorte circular do Android.

O **display da catraca** é lido em movimento, a mais de um metro, por quem já está passando. Por isso usa forma antes de cor: círculo cheio + símbolo branco (✓ ou ✕), com a palavra sempre presente. Verde `#127A4F` para liberado, Brasa `#C42A1E` para bloqueado.

---

## 8. Cores

### 8.1 Primária e secundária

| | Cor | Hex | Papel |
|---|---|---|---|
| **Primária** | Azul Maré | `#0A6B8C` | Ações, links, navegação ativa, marca |
| **Secundária** | Amarelo Sol | `#FAB01B` | Energia da marca, destaques, atenção |

O par é o Nordeste sem clichê: o mar e o sol de quem está no litoral do Ceará, traduzidos em cores que funcionam num painel financeiro. O azul carrega confiança e sustenta texto branco; o amarelo dá o calor que os concorrentes, majoritariamente frios, não têm.

Escalas completas de 50 a 950 em `tokens.css`.

**Neutros:** rampa *Areia Fria*, com leve viés de azul-esverdeado — cinza puro ao lado do Maré parece sujo.

### 8.2 Estados financeiros

Nunca comunicados só por cor: **sempre ícone + texto + cor**.

| Estado | Claro (texto/fundo) | Escuro (texto/fundo) | Ícone |
|---|---|---|---|
| Paga / em dia | `#0B5C3B` sobre `#E7F6EE` | `#7BE8B4` sobre `#0D2A1E` | ✓ |
| A vencer | `#7A4E00` sobre `#FFF3D6` | `#FFD07A` sobre `#2E2008` | ⏱ |
| Vencida | `#93211A` sobre `#FDEAE7` | `#FFB3A8` sobre `#2E1512` | ! |
| Baixa frequência | `#4B33A8` sobre `#EFEBFD` | `#C9BCFF` sobre `#1E1840` | ↓ |

Baixa frequência ganhou **roxo** de propósito: é risco de evasão, não problema de caixa. Misturar com o vermelho de inadimplência faria a gestão tratar as duas coisas do mesmo jeito.

### 8.3 Gráficos

Ordem fixa, nunca reciclada. Uma sétima série vira "Outros".

- **Claro:** `#1290BC` `#C77400` `#7A5AF8` `#2E9E5B` `#3B4EA0` `#C2417A`
- **Escuro:** `#2A96C0` `#C58514` `#7E67DE` `#269960` `#6274CE` `#CE4A80`
- **Sequencial** (mapa de calor de frequência por horário): rampa Maré de claro a escuro, `--seq-1` a `--seq-6`.

As cores de estado (verde/âmbar/vermelho/roxo) são **reservadas** — não entram como série de gráfico.

### 8.4 Verificação

Todos os pares foram calculados, não estimados:

- Texto e ações: **≥ 4,5:1** nos dois temas (menor valor medido: 4,76:1, texto secundário sobre o fundo claro).
- Contornos de campo e anel de foco: **≥ 3:1**.
- Paleta de gráficos: aprovada nos testes de banda de luminosidade, saturação mínima, separação para deuteranopia/protanopia/tritanopia e contraste contra a superfície, nos dois temas.

Ao mexer em qualquer valor, recalcule antes de subir.

---

## 9. Temas claro e escuro

**Ambos são oficiais.** O claro é o padrão da recepção (tela iluminada, uso o dia inteiro). O escuro serve à consulta no celular à noite e ao terminal da catraca em sala de pouca luz.

Três estados, não dois: escolha explícita clara, escolha explícita escura, e o padrão "sistema" — que não marca nada no HTML e só se resolve por `prefers-color-scheme`. `tokens.css` cobre os três: `:root` define o claro completo, o bloco `@media` redefine **apenas os tokens** com o guard `:not([data-theme="light"])`, e `:root[data-theme="dark"]` repete para a escolha manual.

Regras que evitam o bug clássico de tela ilegível:

- Componente nenhum declara cor dentro de `@media` ou `[data-theme]` — só os tokens mudam ali.
- `body` sempre pinta `background` a partir de um token.
- O escuro **não é inversão** do claro: as superfícies têm um viés azul-esverdeado (`#0B1519`, `#101F25`) e as cores de ação e estado foram reescolhidas, não clareadas automaticamente.

Guarde a preferência do usuário no perfil (banco), não só no `localStorage` — a equipe alterna entre balcão e celular.

---

## 10. Tipografia

| Papel | Fonte | Observação |
|---|---|---|
| Títulos | **Sora** | Geométrica, ecoa o monolinear do logotipo. Pesos 600/700. |
| Texto e interface | **Inter** | Legibilidade em tela pequena; pesos 400/500/600. |
| Números | **Inter** com `tabular-nums` | Obrigatório em valor, data e contagem. |

Ambas são de licença aberta (SIL OFL) — uso comercial liberado. **Auto-hospede** as fontes (não use CDN): a academia pode estar em conexão instável e o painel não pode cair para fonte de sistema no meio do expediente.

Escala 1,25 a partir de 16 px, definida em `tokens.css`. Linha de texto corrido no máximo em ~65 caracteres.

---

## 11. Aplicação no produto

**Mobile-first de verdade**, porque o requisito é monitorar pelo celular:

- Alvo de toque mínimo **44 px** (`--alvo-toque`).
- No celular, a navegação principal fica **embaixo** — polegar alcança.
- **Radar é a tela inicial no celular.** Abriu o app, a gestão vê: total vencido, quantos vencem hoje, quantos alunos sumiram. Detalhe depois do resumo.
- Tabela financeira não vira tabela apertada no celular: vira lista de cartões, com valor à direita em `tabular-nums`.
- Estado sempre em pílula com ícone + texto, nunca um pontinho colorido sozinho.

---

## 12. Implementação (Laravel + Tailwind)

**Fuso e idioma** — todas as academias-alvo operam em `America/Fortaleza` (UTC−3, sem horário de verão desde 2019):

```php
// config/app.php
'timezone' => 'America/Fortaleza',
'locale' => 'pt_BR',
'faker_locale' => 'pt_BR',
```

Grave `timestamptz` no PostgreSQL (UTC no banco) e converta na aplicação. Vencimento de mensalidade é **data**, não instante: use `date` para não virar o dia por causa de fuso.

**Tokens no Tailwind v4** — importe `tokens.css` e mapeie o que precisar em `@theme`:

```css
@import "./tokens.css";

@theme {
  --color-mare-600: var(--mare-600);
  --color-sol-400:  var(--sol-400);
  --color-acao:     var(--cor-acao);
  --color-superficie: var(--cor-superficie);
}
```

No Tailwind v3, o mesmo mapeamento vai em `theme.extend.colors`. Em qualquer versão: **componente não usa hex literal** — só token.

---

## 13. Acessibilidade e conformidade

**WCAG 2.1 AA** é o piso: contrastes já verificados, foco sempre visível, nunca cor como único portador de informação, `prefers-reduced-motion` respeitado.

**LGPD — biometria é dado sensível** (art. 11). Isso tem consequência de marca, não só jurídica: o sistema pede o rosto e a digital do aluno, então precisa *parecer* confiável e *ser* transparente. Segundo a orientação corrente e as prioridades de fiscalização da ANPD sobre reconhecimento facial:

- **Consentimento específico e separado** do contrato de matrícula, com a finalidade escrita ("controle de acesso e frequência").
- **Alternativa obrigatória** a quem não quiser dar biometria — cartão, QR ou PIN. O sistema tem que suportar isso desde o primeiro dia; não é item de backlog.
- **Exclusão ao cancelar** a matrícula, com registro de que foi excluída.
- Armazene **template biométrico**, nunca a imagem do rosto; cifrado, com acesso auditado.

Na interface, escreva isso em português claro na tela de cadastro — a transparência é parte da identidade, não letra miúda.

---

## 14. Próximos passos

1. Busca de anterioridade no INPI (classes 9 e 42) e registro dos domínios — **antes** de qualquer aplicação da marca.
2. Vetorizar o logotipo em arquivo editável (`.ai`/`.svg` com traçados expandidos) e fechar o manual de aplicação.
3. Prototipar as duas telas que definem o produto — **Radar** e **ficha do aluno** — nos dois temas, e testar com uma recepcionista de verdade, no celular dela.
4. Definir a arquitetura de integração de acesso: equipamentos com API/SDK documentados (Control iD iDFace, Topdata, Henry, Intelbras) e o modelo de sincronização de templates biométricos.
5. Cadastrar os modelos de mensagem do WhatsApp para aprovação — o prazo de aprovação entra no cronograma de lançamento.

---

## 15. Fontes consultadas

- [Os 15 melhores sistemas para academia em 2026 — Sistema Pacto](https://blog.sistemapacto.com.br/melhores-sistemas-para-academias/)
- [Sistema para academia com catraca — Next Fit](https://blog.nextfit.com.br/sistema-academia-com-catraca/)
- [Sistemas para academia: 6 principais softwares — eNotas](https://enotas.com.br/blog/sistemas-para-academia/)
- [Informações da catraca — API Control iD](https://www.controlid.com.br/docs/access-api-pt/reconhecimento-facial/informacoes-catraca/)
- [Catracas e leitores compatíveis — Sistema SCA](https://www.sistemasca.com/online/equipamentos)
- [LGPD e dados biométricos — Confidata](https://confidata.com.br/blog/lgpd-dados-biometricos-reconhecimento-facial-digital)
- [Controle de acesso por biometria: ANPD em 2026 — Porter](https://porter.com.br/controle-de-acesso-biometria/)
- [LGPD em academia: guia prático — Sistema Pacto](https://blog.sistemapacto.com.br/lgpd-tudo-o-que-voce-precisa-saber-para-nao-ser-pego-de-surpresa/)
- [Classe 42 do INPI: o que é e como registrar — Jusbrasil](https://www.jusbrasil.com.br/artigos/classe-42-do-inpi-o-que-e-quais-servicos-abrange-e-como-registrar-sua-marca/4401388760)
