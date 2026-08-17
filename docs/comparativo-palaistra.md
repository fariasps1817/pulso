# Pulso × Palaistra — o que aproveitar, o que descartar

> Avaliação do documento `docs/DOCUMENTACAO.md`, escrito para o **Palaistra** —
> outro sistema de gestão de academias, de um colega, também Laravel + PostgreSQL
> com isolamento por Row Level Security.
>
> Objetivo: atender ao máximo dos requisitos dele **sem** comprometer segurança
> e desempenho, e com o menor impacto possível no que já está feito.
>
> Escrito em 16/08/2026, comparando com o Pulso no commit `9622f0a`.

---

## Sumário

1. [Como ler este documento](#1-como-ler-este-documento)
2. [Os dois projetos, lado a lado](#2-os-dois-projetos-lado-a-lado)
3. [Três achados que precisam de decisão antes do deploy](#3-três-achados-que-precisam-de-decisão-antes-do-deploy)
4. [O que já atendemos, área por área](#4-o-que-já-atendemos-área-por-área)
5. [Quanto por cento estamos](#5-quanto-por-cento-estamos)
6. [O que vale importar](#6-o-que-vale-importar)
7. [O que não vale a pena](#7-o-que-não-vale-a-pena)
8. [Um caso em cima do muro](#8-um-caso-em-cima-do-muro-pessoa-unificada)
9. [Ordem recomendada](#9-ordem-recomendada)

---

## 1. Como ler este documento

O Palaistra **não é um concorrente a copiar**. É um segundo projeto que resolveu
o mesmo problema com escolhas diferentes, e por isso serve como revisão por
pares: onde ele tropeçou, nós podemos evitar; onde ele acertou algo que nos
falta, vale trazer.

Três coisas o tornam especialmente útil:

- ele **documenta os próprios defeitos** (seção 13.3 do documento dele), inclusive
  os que passaram por centenas de testes verdes;
- ele registra o **porquê** de cada decisão, não só o quê;
- ele é **honesto sobre o que não existe** — a seção 14.2 abre com *"toda
  fronteira com o mundo externo é simulação ou não existe"*.

> **Nota de método:** as porcentagens deste documento são estimativas com
> denominador declarado, não medição exata. Onde afirmo que algo "não temos",
> foi verificado no código em 16/08/2026.

---

## 2. Os dois projetos, lado a lado

| | Pulso (nosso) | Palaistra |
|---|---|---|
| Tabelas | 33 (19 de domínio) | 57 (~50 de domínio) |
| Models | 18 | 50 |
| Rotas | 76 | 135 |
| Testes | 373 | 572 |
| Interface | Livewire + Blade + Tailwind (Vite) | Blade puro + CSS único, sem build |
| Permissões | spatie/laravel-permission com *teams* | 28 Gates nativos |
| Chave primária | `bigint` autoincremento | `uuidv7()` nativo do PG 18 |
| Dinheiro | `numeric(10,2)` + bcmath | `integer` em centavos |
| Isolamento | RLS por `academia_id` | RLS por `tenant_id` |
| Tenant vem de | usuário autenticado | subdomínio (host) |
| Catraca | **protocolo ZKTeco real + simulador** | endpoint pronto, driver **não** |
| Segurança de login | bloqueio, inatividade, sessão única | não documentado |

**A leitura mais importante desta tabela:** ele é mais largo, nós somos mais
fundos onde a academia não funciona sem. Ele modelou treino, agenda, caixa, PDV
e fiscal — mas a catraca dele ainda não fala com equipamento nenhum, e a
cobrança é digitada por uma pessoa.

---

## 3. Três achados que precisam de decisão antes do deploy

São os itens em que o documento dele descreve um problema que **nós temos
agora**, verificado no código.

### 3.1 O fuso não está fixado nas nossas conexões

**O que eles viveram.** Aplicação em UTC, sessão do PostgreSQL em
`America/Sao_Paulo`. Todo `timestamptz` era gravado **3 horas no futuro**. E o
mais grave: **440 testes continuaram verdes**, porque a escrita e a leitura se
cancelavam — grava errado, lê errado do mesmo jeito, a diferença some.

**Como isso acontece, concretamente.** O Laravel manda a data para o banco como
texto, sem fuso:

```
INSERT INTO acessos (ocorreu_em) VALUES ('2026-08-16 09:00:00')
```

O PostgreSQL não recebe nenhuma informação de fuso nessa string. Ele usa o fuso
da **sessão** para interpretar. Então:

| Fuso da sessão do PG | O que fica gravado (em UTC) | Como o PHP lê de volta |
|---|---|---|
| `America/Fortaleza` (−3) | 12:00 UTC | 09:00 ✅ |
| `UTC` | 09:00 UTC | **06:00** ❌ |

**Nossa situação hoje.** Medido em 16/08/2026:

```
PHP: America/Fortaleza   |   PostgreSQL: America/Cayenne
```

Os dois são UTC−3 sem horário de verão, então o resultado bate por coincidência.
**Na VPS o PostgreSQL virá em UTC por padrão** — e aí toda passagem de catraca,
todo recebimento e todo `ocorreu_em` nasce 3 horas deslocado, sem sintoma
visível.

**A correção.** Uma linha em cada conexão de `config/database.php`:

```php
'pgsql' => [
    'driver' => 'pgsql',
    // ...
    'timezone' => env('DB_TIMEZONE', 'America/Fortaleza'),
],
```

> Fixar em `America/Fortaleza` (e não em UTC) mantém o comportamento atual do
> banco local e do que já está gravado. Fixar em UTC seria mais canônico, mas
> exigiria converter o histórico — e hoje não há histórico que valha isso.

**Custo:** minutos. **Risco de não fazer:** relatório de frequência e de caixa
com hora errada, descoberto semanas depois.

---

### 3.2 O `D-01` deles é uma bomba adormecida no nosso código

Este é o achado mais consequente do documento inteiro.

**O que é.** Nosso `ContextoAcademia` define a academia atual assim:

```php
DB::statement('SELECT set_config(?, ?, false)', ['pulso.academia_id', $id]);
//                                        ^^^^^ is_local = false → escopo de SESSÃO
```

Escopo de sessão significa: o valor vale enquanto a **conexão** viver. Hoje isso
é seguro, porque o Laravel abre e fecha a conexão a cada requisição. O comentário
que já está no nosso código diz exatamente isso:

> *"Se um dia entrar um pool de conexões persistentes, esta linha precisa ser
> revista — senão a academia de um request vaza para o seguinte."*

**Por que isso deixa de ser verdade com PgBouncer.** Em *transaction mode*, a
conexão de servidor volta ao pool **a cada transação**, e é reaproveitada por
outro cliente. Cenário real, com pool de uma conexão só:

```
Cliente A: set_config('pulso.academia_id', '1', false)   ← Alpha Fit
Cliente B: set_config('pulso.academia_id', '2', false)   ← Corpo em Movimento
Cliente A: SELECT * FROM alunos   →  devolve os alunos da academia 2
```

O documento é direto sobre a gravidade:

> *"Não é falha fechada. **É um tenant lendo os dados de outro.**"*

**A correção deles.** Trocar para `is_local = true` — o valor vale só dentro da
transação e some no COMMIT:

```php
DB::statement('SELECT set_config(?, ?, true)', ['pulso.academia_id', $id]);
```

**O preço.** Fora de uma transação o contexto não existe, e o banco devolve zero
linhas em silêncio. Eles pagaram esse preço com duas defesas: um listener de
`TransactionBeginning` que aplica o contexto sozinho, e um trait que **recusa**
consultas fora de transação com exceção, em vez de devolver lista vazia.

**O que recomendo.** Não mexer agora — o desenho atual está correto para o modo
como rodamos. Mas registrar a regra:

> **Adotar PgBouncer sem antes migrar para escopo de transação é vazamento entre
> academias.** As duas decisões andam juntas.

---

### 3.3 O `pg_dump` vai falhar no primeiro backup

**Por quê.** Nós usamos `FORCE ROW LEVEL SECURITY`, que faz a política valer
**inclusive para o dono da tabela**. É o que impede um script rodado às pressas
sob o papel de migração de enxergar todas as academias — decisão certa.

O efeito colateral: o `pg_dump` também é barrado, com a mensagem

```
ERROR: query would be affected by row-level security policy for table "alunos"
```

**Nossos papéis hoje** (`database/postgres/01-bancos-e-papeis.sql`):

| Papel | `BYPASSRLS` | Para quê |
|---|---|---|
| `pulso_app` | não | a aplicação |
| `postgres` | (superusuário) | migrations |

Fazer backup com `postgres` funciona, mas usa um superusuário para uma tarefa que
só precisa ler — e é exatamente o tipo de atalho que vira hábito.

**A solução deles.** Um terceiro papel, com o mínimo:

```sql
CREATE ROLE pulso_backup LOGIN BYPASSRLS;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO pulso_backup;
-- e nada mais: não escreve, não cria, não apaga
```

Tem `BYPASSRLS` porque precisa ler tudo; tem **só `SELECT`** porque backup não
escreve. Se a credencial vazar, o estrago é leitura — grave, mas não destrutivo.

---

## 4. O que já atendemos, área por área

Legenda: **✅ pronto** · **🟡 parcial** · **⬜ não existe** · **⭐ estamos à frente**

### 4.1 Isolamento multi-tenant — 🟡 ~85%

Os quatro detalhes que o documento destaca como "não cosméticos", nós temos:

| Detalhe | Por que importa | Temos? |
|---|---|---|
| `FORCE ROW LEVEL SECURITY` | sem ele, o dono da tabela ignora a política | ✅ |
| `WITH CHECK` além de `USING` | sem ele dá para **gravar** na academia alheia | ✅ |
| `NULLIF(..., '')` | faz "sem contexto" virar zero linhas em vez de erro 500 | ✅ |
| Papel da aplicação sem `BYPASSRLS` | senão o RLS é enfeite | ✅ |

**O que falta:**

- escopo de transação (item 3.2)
- papel de backup (item 3.3)
- **chave estrangeira composta** — explicada em 6.3

### 4.2 Aluno e matrícula — 🟡 ~85%

Temos cadastro completo, matrícula com experiência, conversão, suspensão e
encerramento, e o padrão de copiar `valor_mensal` na contratação (o mesmo
"snapshot" que eles descrevem em 12.4).

**Falta:**

- **Congelamento como período**, com data de início e fim. Hoje temos só a
  situação `Suspensa`, sem registrar quantos dias. O detalhe bom deles: o limite
  usa **janela móvel de 12 meses**, não ano civil — com ano civil o aluno tranca
  30 dias em dezembro e mais 30 em janeiro, dobrando o limite sem quebrar regra.
- **Fidelidade e multa de cancelamento.** A fórmula deles:

  ```
  multa = (mensalidade × meses_restantes_de_fidelidade × percentual) ÷ 100
  ```

  Com duas travas que valem copiar: **sem fidelidade combinada, multa zero**
  (cobrar de quem nunca combinou é passivo judicial), e **teto de 50%** no
  próprio campo, para uma configuração distraída não virar cláusula abusiva.

### 4.3 Financeiro a receber — 🟡 ~70%

Nosso desenho é o mesmo em espírito: mensalidade com pagamentos parciais,
estorno que **carimba data em vez de apagar**, situação derivada da soma.

**Falta a régua de cobrança.** É o que transforma o Radar em ação:

- marcos de 3, 7, 15 e 30 dias de atraso;
- cada marco dispara **uma vez por mensalidade** — garantido por índice único
  `(mensalidade_id, marco)` no banco, não por verificação em PHP;
- mudar a lista **não reenvia o passado**;
- envio às 09:00 — aviso que chega às três da madrugada parece spam.

### 4.4 Controle de acesso e catraca — ⭐ estamos à frente

| | Palaistra | Pulso |
|---|---|---|
| Endpoint HTTP | ✅ | ✅ |
| Model de dispositivo | ✅ | ✅ |
| Idempotência de reenvio | ✅ | ✅ |
| **Driver do equipamento** | ⬜ (`D-03` aberto) | ✅ protocolo ZKTeco PUSH |
| **Simulador** | ⬜ | ✅ fala o protocolo real |
| Dedução de entrada/saída | ⬜ | ✅ com tolerância e repique |

O documento deles diz: *"O driver do equipamento, não. Travado por payload/SDK
da ZKTeco."* É exatamente a peça que nós já temos.

Uma coisa deles vale copiar mesmo assim: eles separam **`ocorrido_em`** (relógio
do equipamento) de **`recebido_em`** (chegada no servidor), e expõem a diferença
como `atrasoEmMinutos()`. Nós só temos `ocorreu_em`. Com terminal offline que
acumula e reenvia, saber o atraso é o que distingue "a catraca está muda" de "a
catraca está atrasada".

### 4.5 Segurança de login — ⭐ estamos à frente

O documento não menciona bloqueio de tentativas, timeout de inatividade nem
sessão única. Nós temos os três, com testes.

### 4.6 Plataforma SaaS — 🟡 ~55%

Temos: super administrador isolado, cadastro de academia com unidade e dono na
mesma transação, situação com efeito real, avisos, contador de alunos.

**Falta:**

- **Trial** com data de término e verificação automática
- **Expurgo LGPD** — apagar os dados de quem cancelou, com prova do expurgo
  guardada **fora** do tenant que deixou de existir
- **Auditoria** append-only (ver 6.4)
- **Medição diária de uso.** O detalhe deles: *"medição não é retroativa — dia
  não coletado é dia perdido para sempre"*. Nosso contador de alunos é do
  **agora**; não dá para responder "quantos alunos a Alpha Fit tinha em março?"

### 4.7 Indicadores — 🟡 ~40%

Temos os que geram ação: vencido, vence hoje, sumidos, recebido no mês.

**Faltam** MRR, ticket médio, churn, renovação, LTV, frequência média.

Três regras de medição deles que valem adotar junto:

- **Churn com base zero devolve `null`, não zero.** Academia que abriu este mês
  tem churn *indefinido*. Um zero verde no painel de quem não tinha aluno nenhum
  é informação falsa. É a mesma regra que já aplicamos na catraca não integrada.
- **LTV só com contratos encerrados.** Quem ainda está na academia não terminou
  de gerar valor; incluir puxa a média para baixo, ao contrário do que o
  indicador quer dizer.
- **Inadimplência com denominador amplo** (tudo em aberto, não só o vencido).
  Usar só o vencido daria sempre 100%.

### 4.8 Saúde — 🟡 ~20%

Temos consentimento LGPD versionado. **Faltam** anamnese (PAR-Q) e atestado.

O desenho deles vale copiar: anamnese **versionada, nunca sobrescrita** — a
resposta de dois anos atrás é o que justifica a prescrição daquela época — com o
questionário estruturado em `jsonb` (consultável) e o texto livre em coluna
cifrada.

### 4.9 CRM e retenção — 🟡 ~15%

O Radar já aponta quem sumiu e cruza com quem deve. **Falta a tarefa**: hoje o
alerta morre na tela, e ninguém sabe se alguém ligou.

O detalhe bom deles é o índice parcial:

```sql
CREATE UNIQUE INDEX tarefas_uma_aberta_por_motivo
  ON tarefas (tenant_id, motivo, sobre_tipo, sobre_id)
  WHERE concluida_em IS NULL;
```

O job roda todo dia; sem isso, empilha dez tarefas para o mesmo aluno em dez
dias.

### 4.10 O que não existe de nenhum lado nosso — ⬜ 0%

Caixa e tesouraria · contas a pagar · PDV e estoque · treino e avaliação física ·
agenda de turmas e reservas · portal do aluno · fiscal NFS-e · pacotes e
personal.

---

## 5. Quanto por cento estamos

Depende do denominador, e por isso vão os três:

| Critério | Conta | Resultado |
|---|---|---|
| Tabelas de domínio | 19 de ~50 | **38%** |
| Módulos funcionais | 4–5 de 13 | **35%** |
| Ponderado pelo uso diário | ver abaixo | **42%** |

O ponderado sai assim:

| Bloco | Peso | Nosso estado | Contribuição |
|---|---|---|---|
| Núcleo operacional (aluno, matrícula, mensalidade, acesso, Radar, login, configurações) | 45% | 85% | 38,3 |
| Financeiro completo (caixa, tesouraria, a pagar, PDV, fiscal) | 20% | 0% | 0 |
| Técnico (treino, avaliação, agenda) | 20% | 0% | 0 |
| CRM, retenção e portal | 10% | 10% | 1,0 |
| Plataforma SaaS | 5% | 55% | 2,8 |
| | | | **≈ 42%** |

### A leitura que importa

**Cerca de 40% dos requisitos, mas ~85% do núcleo operacional.**

Os 60% que faltam não são buracos no que existe — são módulos inteiros que ainda
não começaram. Um sistema com 42% de cobertura mas 85% do núcleo **já opera uma
academia**: matricula, cobra, recebe, libera a catraca e avisa quem sumiu. Um
sistema com 42% espalhados uniformemente não operaria nada.

---

## 6. O que vale importar

### 6.1 Barato e alto valor — antes do deploy

| Item | Custo | O que evita |
|---|---|---|
| Fuso nas conexões (3.1) | minutos | Toda data 3 h deslocada, em silêncio |
| Papel de backup (3.3) | ~1 hora | `pg_dump` falhando, ou backup com superusuário |
| `REVOKE UPDATE, DELETE` nos livros-razão | ~1 hora | Ver 6.2 |
| CI com prova de fogo do RLS | ~3 horas | Ver 6.5 |

### 6.2 `REVOKE` nos livros-razão

Nós **já tratamos** `pagamentos` como append-only: estornar carimba
`estornado_em`, nunca apaga. Mas isso é disciplina do código — um `update`
distraído numa correção de madrugada passa.

Eles transformam a disciplina em garantia:

```sql
REVOKE UPDATE, DELETE ON pagamentos FROM pulso_app;
REVOKE UPDATE, DELETE ON acessos    FROM pulso_app;
```

Depois disso, nem a aplicação nem alguém com a credencial dela reescreve a
história do dinheiro. Correção passa a ser **contra-lançamento**, que é como
contabilidade funciona de verdade.

> **Cuidado ao adotar:** hoje o estorno faz `update` em `pagamentos` para gravar
> `estornado_em`. Com o `REVOKE`, isso deixa de funcionar. O caminho é o deles:
> o estorno vira uma **linha nova** apontando para a estornada. É mudança de
> desenho, não só de privilégio — por isso está aqui e não em "custo de minutos".

### 6.3 Chave estrangeira composta

**A ideia mais forte do documento que não temos.**

Hoje nossas FKs são simples:

```php
$tabela->foreignId('aluno_id')->constrained('alunos');
```

Isso garante que o aluno existe. **Não garante que ele é da mesma academia.** Se
o RLS falhasse, ou se alguém rodasse um script sob o papel de migração, seria
possível gravar uma matrícula da academia 1 apontando para um aluno da academia 2
— e o banco aceitaria.

O desenho deles fecha essa porta:

```php
// em cada tabela de domínio: o alvo das FKs compostas
$tabela->unique(['academia_id', 'id']);

// e a FK passa a levar a academia junto
$tabela->foreign(['academia_id', 'aluno_id'])
       ->references(['academia_id', 'id'])
       ->on('alunos');
```

Agora é **impossível no banco** cruzar academias — mesmo com o RLS desligado,
mesmo com bug na aplicação. É a terceira barreira, depois do filtro do Eloquent
e do RLS.

**Custo:** um índice único por tabela e refazer ~15 FKs. Uma migration grande,
sem mudança de código de aplicação. **Vale.**

### 6.4 Auditoria append-only

Uma tabela que registra: evento (criou/alterou/apagou), qual model, qual id, o
que mudou (`jsonb`), quem fez, IP e navegador. Com `REVOKE UPDATE, DELETE`.

Resolve duas coisas de uma vez:

- **Suporte:** "quem mudou o valor dessa matrícula?" hoje não tem resposta.
- **LGPD:** demonstrar quem acessou dado pessoal é exigência, não conforto.

Encaixa bem no que já temos, porque nossos models são poucos e passam quase todos
pelo mesmo trait (`PertenceAAcademia`).

### 6.5 CI com prova de fogo

Não temos CI nenhum. O deles roda a cada push e **quebra o build** se o papel da
aplicação ganhar `BYPASSRLS`:

```sql
SELECT rolname FROM pg_roles WHERE rolname = 'pulso_app' AND rolbypassrls;
-- qualquer linha aqui = build quebrado
```

Junto vale trazer o **teste de mutação manual** que eles descrevem: sabotar de
propósito a função que aplica o RLS e conferir quantos testes falham. No deles,
9 de 35. Suíte que continua verde com a proteção quebrada não estava medindo
nada.

### 6.6 Ideias de projeto (sem copiar código)

**"Indefinido ≠ zero."** Indicador sem base devolve `null` e a tela escreve `—`.
Já aplicamos isso na catraca não integrada; deve valer para todo indicador novo.

**Snapshot de dado que muda.** Já fazemos com `valor_mensal` na matrícula. A
lista deles mostra onde mais isso importa: nome e CREF do professor na ficha de
treino (responsabilidade técnica de quem assinou), preço na venda (o preço de
hoje não muda a venda de ontem), capacidade e professor na aula agendada.

**Sinal explícito no movimento financeiro.** Em vez de valor negativo, uma coluna
`sentido` com `'entrada'`/`'saida'` e valor sempre positivo. A razão é ótima:
soma errada de sinal é o bug de relatório financeiro mais difícil de achar,
**porque o total parece plausível**.

---

## 7. O que não vale a pena

| Item | Por que não |
|---|---|
| **UUIDv7 como chave primária** | Migração enorme em 19 tabelas e todas as FKs. O ganho (não expor contagem, não fragmentar índice) não paga o risco no nosso porte |
| **Abandonar Vite e Livewire** | Eles apostaram em Blade puro sem build. Nossas 25 telas, o design system, as máscaras e o catálogo dependem do que temos. Trocar não melhora nada para quem usa |
| **Trocar spatie por Gates nativos** | Os dois funcionam. Já temos papéis por academia com *teams*, testados. Reescrever 28 verificações é risco puro |
| **Dinheiro em centavos inteiros** | O motivo deles é evitar `float`. Nós usamos `numeric(10,2)` do PostgreSQL, que é **exato**, com bcmath na aritmética. O problema que a mudança resolveria, nós não temos |
| **Tabela `parametros` genérica** | Nossas três regras em colunas tipadas são mais seguras (o banco valida o tipo) e mais legíveis. A vantagem deles aparece com dezenas de parâmetros — revisitar se passarmos de ~10 |
| **Tenant pelo subdomínio** | Exigiria DNS curinga, certificado curinga e um subdomínio por cliente. Nossa escolha (domínio único, academia vinda do usuário) já está documentada e funciona |

---

## 8. Um caso em cima do muro: pessoa unificada

O argumento deles é bom, e vale registrar em vez de esquecer:

> *"O mesmo CPF pode ser aluno, responsável por um menor, fornecedor e
> colaborador. Duplicar cadastro significa telefone atualizado em um lugar e
> desatualizado nos outros."*

Eles resolvem com uma tabela `pessoas` como núcleo de identidade; `alunos`,
`fornecedores` e `colaboradores` apontam para ela.

Nós temos `alunos` e `profissionais` separados, cada um com o próprio CPF e
telefone.

**Recomendação:** não converger agora — o custo é alto (toca aluno, matrícula,
mensalidade, acesso) e a dor ainda não apareceu. Mas registrar a regra:

> **Não criar uma terceira tabela com CPF e telefone sem antes decidir isso.**

Se `fornecedores` entrar junto com contas a pagar, esse é o momento de decidir —
não depois de três tabelas duplicando o mesmo cadastro.

---

## 9. Ordem recomendada

### Antes do deploy — horas de trabalho

1. **Fixar o fuso nas conexões** (3.1)
2. **Criar o papel de backup** (3.3)
3. **Decidir sobre PgBouncer** (3.2) — se for usar, escopo de transação vira
   pré-requisito, não melhoria

### Fundação — dias de trabalho

4. **CI** com a prova de fogo do RLS (6.5)
5. **FK composta** (6.3) — a terceira barreira do isolamento
6. **Auditoria** (6.4)

### Domínio — a partir daqui é decisão comercial

7. **Régua de cobrança** (4.3) — é o que transforma o Radar em dinheiro
   recuperado, e encaixa no que já existe
8. **Tarefa de retenção** (4.9) — mesmo raciocínio: o alerta vira trabalho
9. Depois disso, a pergunta deixa de ser técnica:

| Se o objetivo for | Construir primeiro |
|---|---|
| Convencer o **dono** na primeira venda | Caixa, contas a pagar, indicadores (MRR, churn) |
| Convencer o **professor** e o aluno | Treino, avaliação física, agenda de turmas |
| Reduzir trabalho da **recepção** | PDV, portal do aluno |

Minha leitura: **treino e agenda** são o que o aluno percebe como valor e o que
justifica a mensalidade dele; **caixa e contas a pagar** são o que o dono sente
no bolso. Para uma primeira venda a academia pequena, o segundo grupo costuma
fechar negócio mais rápido — mas o primeiro é o que segura o cliente depois.

---

## Fecho

O Palaistra é mais largo. O Pulso é mais fundo onde a academia não funciona sem —
e tem, pronta, a integração que o documento dele lista como travada.

Nada no documento sugere que tenhamos escolhido errado nas decisões
estruturantes: RLS com `FORCE`, `WITH CHECK`, `NULLIF`, papel sem `BYPASSRLS`,
estado derivado em vez de coluna que alguém precisa virar de madrugada. Chegamos
às mesmas conclusões por caminhos independentes, o que é o melhor sinal possível
de que estão certas.

O que ele nos dá de mais valioso não é uma lista de módulos a copiar — é **três
armadilhas concretas** que ainda não pisamos, e a lembrança, repetida em duas
seções, de que o teste conveniente não exercita o caminho real.
