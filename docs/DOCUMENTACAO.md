# Palaistra — Documentação Técnica e de Negócio

> Sistema de gestão para academias, entregue como **SaaS multi-tenant**.
> Documento gerado a partir da leitura do código em 15/08/2026, branch
> `fase-0/fundacao-multi-tenant`.
>
> **Referências normativas:** LR-ACAD-001 v2.0 (levantamento de requisitos) ·
> GI-ACAD-001 v1.0 (guia da Fase 0). Os códigos `RF-xx-xx`, `RN-xx`, `RNF-xx`
> e `RES-xx` citados ao longo do texto são desses documentos.

---

## Sumário

1. [Missão e objetivos](#1-missão-e-objetivos)
2. [Decisão estruturante: por que multi-tenant](#2-decisão-estruturante-por-que-multi-tenant)
3. [Tecnologias adotadas](#3-tecnologias-adotadas)
4. [Banco de dados](#4-banco-de-dados)
5. [Segurança](#5-segurança)
6. [Níveis de usuário e matriz de permissões](#6-níveis-de-usuário-e-matriz-de-permissões)
7. [Modelo de dados](#7-modelo-de-dados)
8. [Módulos, telas e CRUDs](#8-módulos-telas-e-cruds)
9. [Regras de negócio](#9-regras-de-negócio)
10. [Indicadores e suas fórmulas](#10-indicadores-e-suas-fórmulas)
11. [Rotinas automáticas](#11-rotinas-automáticas)
12. [Padrões de implementação](#12-padrões-de-implementação)
13. [Testes e integração contínua](#13-testes-e-integração-contínua)
14. [Estado atual, débitos e pendências](#14-estado-atual-débitos-e-pendências)
15. [Glossário](#15-glossário)

---

## 1. Missão e objetivos

### 1.1 O problema

Academia é um negócio de **receita recorrente com evasão alta**. O LR-ACAD-001
descreve a cadeia causal que o sistema existe para quebrar:

```
inadimplência sem cobrança sistemática  →  perda de receita
queda de frequência não percebida       →  cancelamento
sem histórico de treino e avaliação     →  percepção de baixo valor  →  cancelamento
contas a pagar sem previsão             →  estouro de caixa, juros e multas
```

Os quatro elos têm a mesma característica: **o dano é silencioso e conhecido
tarde demais**. O aluno não avisa que vai cancelar — ele para de aparecer três
semanas antes. A inadimplência não estoura de uma vez — ela cresce enquanto
ninguém somou. O caixa não fura no dia do vencimento — fura porque a despesa
recorrente nunca foi projetada.

### 1.2 A missão

**Transformar dado silencioso em ação a tempo.** Não é um sistema de registro
que também mostra relatórios; é um sistema que dispara trabalho:

| O que o sistema percebe | O que ele faz |
|---|---|
| Aluno sem passar na catraca há N dias | Abre **tarefa de contato** para a recepção/professor |
| Parcela cruzou um marco de atraso | Dispara **aviso de cobrança** (uma vez por marco) |
| Ficha de treino vencendo | Avisa o professor para reavaliar |
| Despesa recorrente do mês | Gera a conta a pagar antes do financeiro abrir o sistema |
| Divergência no fechamento de caixa | **Exige justificativa** — não deixa fechar sem |
| CREF do professor vencido | **Impede publicar** ficha de treino |

O indicador mais valioso do sistema não é o MRR: é a **lista de quem sumiu**.
Todos os outros descrevem o que já aconteceu; esse permite agir antes.

### 1.3 Princípios de projeto

Cinco princípios foram aplicados de forma consistente e explicam a maioria das
decisões técnicas do restante deste documento:

1. **O isolamento entre clientes é garantido pelo banco, não pela aplicação.**
   Um `where tenant_id = ?` esquecido é um bug de 30 segundos com consequência
   de fim de empresa. O PostgreSQL recusa a linha; o código não tem como errar.

2. **Indefinido ≠ zero.** Academia que abriu este mês tem churn *indefinido*,
   não churn zero. Um zero verde no painel de quem não tinha aluno nenhum é
   informação falsa. Indicadores sem base devolvem `null` e a interface escreve
   `—`.

3. **Dinheiro é inteiro em centavos.** Float acumula erro de arredondamento, e
   num carnê de 12 parcelas isso vira divergência de centavos que ninguém
   consegue explicar ao cliente. A mesma regra vale para medidas (gramas, cm,
   centésimos de %).

4. **Livro-razão não se apaga.** Movimento financeiro, movimento de estoque e
   trilha de auditoria são *append-only* — garantido por `REVOKE UPDATE, DELETE`
   no banco, não por disciplina do programador. Erro se corrige com
   contra-lançamento.

5. **O teste conveniente não exercita o caminho real.** Vários defeitos graves
   deste projeto passaram por centenas de testes verdes porque a escrita e a
   leitura se cancelavam, ou porque o teste injetava o usuário sem tocar o
   banco. Os casos estão documentados na seção 13.

---

## 2. Decisão estruturante: por que multi-tenant

O LR-ACAD-001 descreve um sistema para **uma** academia. A decisão de produto
foi entregá-lo como **SaaS multi-tenant** — várias academias no mesmo banco,
isoladas por linha.

### 2.1 Consequências da escolha

| Aspecto | Sistema de academia única | Palaistra (SaaS) |
|---|---|---|
| Isolamento | Não existe o problema | **É o problema central** |
| Parametrização | `config()` e constantes | Tabela `parametros` por tenant |
| Identidade visual | Fixa | White-label por tenant (M18) |
| Custo de um bug de vazamento | — | Fim da empresa |
| Ciclo de vida | Instalar | Trial → ativo → suspenso → cancelado → expurgo |
| Medição | Não necessária | `uso_diario` — cobrança e capacidade |

### 2.2 Hierarquia

```
tenant  (a academia cliente do SaaS — resolvida pelo subdomínio)
  └── empresa   (CNPJ; uma rede pode ter mais de um)
        └── unidade   (o endereço físico; catraca, sala, caixa vivem aqui)
```

Toda tabela de domínio carrega `tenant_id`. Tabelas com escopo físico carregam
também `unidade_id`.

### 2.3 Ciclo de vida do tenant

Estados em `App\Models\Tenant`:

| Status | Significado | Resposta HTTP nas rotas do tenant |
|---|---|---|
| `trial` | Período de teste, com `trial_termina_em` | Acesso normal |
| `ativo` | Assinatura em dia | Acesso normal |
| `suspenso` | Trial vencido ou inadimplência do SaaS | **402 Payment Required** |
| `cancelado` | Encerrado; aguarda expurgo | **410 Gone** |
| *(host desconhecido)* | — | **404** — não revela se o tenant existe |

A suspensão é **recuperável**: os dados continuam lá, o acesso é que fecha. O
expurgo (LGPD) é o que apaga, e roda a partir de `tenants.expurgo_em`.

---

## 3. Tecnologias adotadas

### 3.1 Núcleo

| Camada | Escolha | Versão |
|---|---|---|
| Linguagem | PHP | **8.5.5** (ZTS, VS17 x64) |
| Framework | Laravel | **13.25** |
| Banco | PostgreSQL | **18** |
| Pooler | PgBouncer (*transaction mode*) | CI e produção |
| Servidor dev | Laragon (Windows 11) | — |
| Testes | PHPUnit | 12.5 |
| Estilo | Laravel Pint | 1.27 |
| CI | GitHub Actions | 2 jobs |

### 3.2 O que deliberadamente **não** foi adotado

Esta lista é tão importante quanto a anterior, porque cada ausência foi uma
decisão:

- **Nenhum pacote de multi-tenancy** (stancl/tenancy e similares). O isolamento
  é RLS no banco; um pacote colocaria uma camada PHP entre a aplicação e a
  garantia, e a garantia passaria a depender dessa camada.
- **Nenhum build step de front-end.** Não há Vite, npm run build, nem bundle.
  As telas são Blade + um CSS único (`public/css/painel.css`), com
  *cache-busting* por `filemtime`. Consequência: nenhuma dependência de 200 KB
  para desenhar 24 barras de um gráfico.
- **Nenhuma biblioteca de gráficos.** Anéis são SVG inline (~40 linhas);
  barras são CSS puro.
- **Nenhum admin gerado (Filament, Nova).** Escolha de UI é reversível;
  isolamento não é. A UI foi escrita à mão para caber na matriz de permissões.
- **Nenhum pacote de permissões (spatie/laravel-permission).** São 28 Gates
  nativos, cada um com o comentário do *porquê* — a matriz 14.1 do LR-ACAD-001
  é pequena e estável o bastante para não justificar tabelas de RBAC.

### 3.3 Extensões e recursos do PostgreSQL usados

- `uuidv7()` **nativo do PG 18** como default de toda chave primária —
  ordenável por tempo, sem fragmentar índice como UUIDv4.
- `jsonb` para dados de forma variável (`endereco`, `parq`, `metricas`,
  `alteracoes` da auditoria, `avisos_enviados`).
- `timestamptz` em toda coluna de instante.
- **Índices parciais** (`CREATE UNIQUE INDEX ... WHERE`) para invariantes do
  tipo "no máximo um X aberto por Y".
- **Row Level Security** com `FORCE` — a espinha dorsal (seção 5).
- `set_config(..., is_local = true)` — GUC em escopo de transação.
- `INSERT ... ON CONFLICT DO NOTHING` para ingestão idempotente de lote.

---

## 4. Banco de dados

### 4.1 Tipo e topologia

**PostgreSQL 18**, banco único, schema único, **57 tabelas**, isolamento por
linha via RLS. Três roles distintas (seção 5.2), sendo que a aplicação em
runtime **não é dona de nenhuma tabela** e **não tem `BYPASSRLS`**.

O acesso em produção passa por **PgBouncer em transaction mode** — o que
determinou a decisão mais importante da arquitetura de tenancy (seção 5.3).

### 4.2 Convenções de schema

Toda tabela de domínio segue o mesmo padrão:

```php
$t->uuid('id')->primary()->default(DB::raw('uuidv7()'));
$t->tenantId();                                    // macro do projeto
$t->timestampsTz();

$t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
$t->unique(['tenant_id', 'id']);                   // alvo de FK composta
```

**Por que `unique(tenant_id, id)` se `id` já é PK:** ele é o *alvo* das chaves
estrangeiras compostas. Toda FK entre tabelas de domínio é
`(tenant_id, x_id) → (tenant_id, id)`. Isso torna **impossível no banco** um
contrato do tenant A apontar para um aluno do tenant B — mesmo que o RLS fosse
desligado, mesmo que a aplicação tivesse um bug.

### 4.3 `NO ACTION` em vez de `RESTRICT`

Decisão registrada no schema com justificativa. Os dois recusam apagar um plano
que ainda tem contrato; a diferença é **quando** verificam. `RESTRICT` checa na
hora; no expurgo LGPD a cascata remove pai e filho no mesmo comando, e o
resultado passaria a depender da ordem interna com que o Postgres processa a
cascata. Fragilidade inaceitável num job destrutivo que roda de madrugada.

### 4.4 Tipos de dado por natureza

| Natureza | Tipo | Exemplo | Razão |
|---|---|---|---|
| Dinheiro | `integer` (centavos) | `valor_centavos` | Float acumula erro |
| Alíquota | `smallint` (centésimos de %) | `aliquota_centesimos = 200` → 2,00% | Idem |
| Peso | `integer` (gramas) | `peso_gramas` | Idem |
| Altura | `smallint` (cm) | `altura_cm` | Idem |
| % gordura | `smallint` (centésimos) | `gordura_centesimos` | Idem |
| Instante | `timestamptz` | `ocorrido_em` | Fuso explícito |
| Data civil | `date` | `vencimento` | Vencimento não tem hora |
| Forma variável | `jsonb` | `endereco`, `parq` | Modelagem fina adiada |
| Sensível | `text` cifrado | `salario_centavos` | Cast `encrypted` (seção 5.6) |

> **Nota:** `colaboradores.salario_centavos` é `text` no banco justamente
> porque é cifrado — o ciphertext não cabe num `integer`.

### 4.5 Invariantes garantidas pelo banco

Estas regras **não** estão em PHP. Estão em índices e privilégios, porque
verificação em PHP falha sob concorrência.

| Invariante | Mecanismo |
|---|---|
| No máximo **um caixa aberto** por conta | `CREATE UNIQUE INDEX caixas_um_aberto_por_conta ON caixas (tenant_id, conta_id) WHERE fechado_em IS NULL` |
| No máximo **uma tarefa aberta** por motivo/objeto | `CREATE UNIQUE INDEX tarefas_uma_aberta_por_motivo ... WHERE concluida_em IS NULL` |
| Movimento financeiro **não se altera nem se apaga** | `REVOKE UPDATE, DELETE ON movimentos FROM app_runtime` |
| Movimento de estoque idem | `REVOKE UPDATE, DELETE ON movimentos_estoque FROM app_runtime` |
| Trilha de auditoria idem | `REVOKE UPDATE, DELETE ON auditoria FROM app_runtime` |
| Passagem de catraca **não duplica** no reenvio | `unique (tenant_id, dispositivo_id, identificador_externo, ocorrido_em)` |
| **Uma nota fiscal** por origem | `unique (tenant_id, origem_tipo, origem_id)` |
| Despesa recorrente **não gera duas contas** na mesma competência | `unique (tenant_id, recorrente_id, competencia)` |
| Cada marco da régua dispara **uma vez** por parcela | `unique (tenant_id, parcela_id, marco)` |
| **Uma sala não tem duas turmas** no mesmo horário | `unique (tenant_id, sala_id, dia_da_semana, hora_inicio)` |
| Abrir a grade **duas vezes** não duplica aula | `unique (tenant_id, aula_id, inicia_em)` |
| **Uma reserva** por aluno por aula | `unique (tenant_id, aula_agendada_id, aluno_id)` |
| Anamnese **versionada**, nunca sobrescrita | `unique (tenant_id, aluno_id, versao)` |
| Consentimento versionado por finalidade | `unique (tenant_id, pessoa_id, finalidade, versao)` |
| CPF/CNPJ único **dentro** do tenant | `unique (tenant_id, documento)` |
| Um snapshot de uso por dia | `unique (tenant_id, data)` |

### 4.6 Fuso horário

`config/database.php` fixa `'timezone' => env('DB_TIMEZONE', 'UTC')` nas
conexões. Isso corrige um bug sistêmico descrito na seção 13.3.

---

## 5. Segurança

A segurança do Palaistra tem **cinco camadas independentes**. Nenhuma delas
depende das outras estarem corretas — é a propriedade que as torna úteis.

```
┌─────────────────────────────────────────────────────────┐
│ 5. Auditoria — o que aconteceu, append-only             │
├─────────────────────────────────────────────────────────┤
│ 4. Criptografia — dado sensível cifrado em repouso      │
├─────────────────────────────────────────────────────────┤
│ 3. Autorização — dentro do cliente, quem pode o quê     │
├─────────────────────────────────────────────────────────┤
│ 2. Autenticação — dois guards separados                 │
├─────────────────────────────────────────────────────────┤
│ 1. ISOLAMENTO — RLS no PostgreSQL  ◄── a garantia       │
└─────────────────────────────────────────────────────────┘
```

### 5.1 Camada 1 — Row Level Security

Toda tabela com `tenant_id` passa por `App\Support\Database\Rls::protect()`:

```sql
ALTER TABLE {tabela} ENABLE ROW LEVEL SECURITY;
ALTER TABLE {tabela} FORCE  ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON {tabela}
  USING      (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
  WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid);

GRANT SELECT, INSERT, UPDATE, DELETE ON {tabela} TO app_runtime;
```

Três detalhes que não são cosméticos:

**`FORCE`** faz a política valer **também para o dono da tabela**. Sem isso,
qualquer script rodado às pressas em produção sob a role do migrator enxerga
todos os clientes.

**`NULLIF(..., '')`** resolve os dois modos de "sem contexto":
- variável nunca definida → `current_setting(..., true)` devolve `NULL` → zero linhas ✅
- contexto explicitamente limpo → grava `''`, e `''::uuid` **levanta exceção**
  (`invalid input syntax for type uuid`) → o sistema falharia com 500 em vez de
  falhar fechado ❌

Com `NULLIF`, os dois convergem para `NULL` e o resultado é sempre zero linhas.

**`WITH CHECK`** além de `USING`: `USING` filtra leitura; `WITH CHECK` recusa
escrita com `tenant_id` de outro. Sem ele, seria possível *gravar* na conta
alheia mesmo sem conseguir *ler*.

Existe ainda `Rls::semForcaPara(array $tabelas, Closure $fn)` — suspende o
`FORCE` durante migrações de **dados**. Um backfill que lê todas as linhas de
todos os tenants não tem tenant no GUC, e o Postgres devolveria **zero linhas em
silêncio**: a migração "passa" e o dado não foi copiado. A suspensão é segura
porque acontece dentro de uma única migração, sob `app_migrator` (nunca a role
de runtime), e o `finally` restaura mesmo se o callback lançar.

### 5.2 Roles do banco

| Role | Dono de | `BYPASSRLS` | Privilégios | Uso |
|---|---|---|---|---|
| `app_migrator` | todas as tabelas | **não** | DDL + `CREATEDB` | Migrações e restore |
| `app_runtime` | nada | **não** | `SELECT, INSERT, UPDATE, DELETE` (menos as tabelas-razão) | A aplicação |
| `app_backup` | nada | **sim** | `SELECT` apenas | `pg_dump` |

`app_backup` existe porque **`FORCE RLS` bloqueia até o dono** — o `pg_dump`
falhava com *"query would be affected by row-level security policy"*. A role
tem `BYPASSRLS` (precisa ler tudo) mas **só `SELECT`**: não escreve nada.

`app_migrator` tem `CREATEDB` apenas para a verificação semanal de restore
(subir um banco do lado e restaurar o dump). Não afeta RLS.

O CI tem um passo dedicado — *"Prova de fogo — runtime sem BYPASSRLS"* — que
consulta `pg_roles` e **quebra o build** se a flag aparecer.

### 5.3 O contexto em escopo de transação (débito D-01)

Esta é a decisão de segurança mais consequente do projeto, e ela **inverteu** a
recomendação do guia original.

O GI-ACAD-001 recomendava modo *sessão* (`set_config` com `is_local = false`)
mais salvaguardas. **O CI provou que não basta.** Sob PgBouncer em transaction
mode, com dois clientes concorrentes disputando uma conexão de servidor, o
`set_config` de um **sobrescreve** o do outro:

> Não é falha fechada. **É um tenant lendo os dados de outro.**

A correção é estrutural, não uma salvaguarda a mais:

```php
DB::connection('pgsql')
    ->statement("SELECT set_config('app.tenant_id', ?, true)", [self::id()]);
//                                                     ^^^^ is_local = true
```

Com `is_local = true` o valor vale **apenas dentro da transação corrente** e
desaparece no COMMIT/ROLLBACK. O ganho:

- vazamento entre requisições deixa de ser possível **por construção**;
- não há resíduo a limpar;
- não há reaplicação após reconexão;
- não há ordem de middleware que possa dar errado;
- a conexão volta ao pool **sempre limpa**.

**O preço:** toda leitura e escrita de dados de tenant exige transação aberta.
Fora de uma, o GUC não existe e o banco devolveria zero linhas em silêncio.
Duas defesas impedem que isso vire bug invisível:

1. o listener de `TransactionBeginning` aplica o contexto sozinho — qualquer
   `DB::transaction()` já nasce com tenant;
2. o trait `PertenceAoTenant` **recusa** consultas fora de transação com
   `ContextoForaDeTransacaoException`, em vez de devolver lista vazia.

O job `pool` do CI roda esse cenário exato (pool de 1 conexão, 2 clientes
concorrentes) a cada push.

### 5.4 Resolução do tenant e ordem de middleware

`ResolverTenant` resolve o tenant **pelo host** e é seguido por
`TransacaoDoTenant`. O grupo `tenant` é **deliberadamente não global**: o site
institucional, o cadastro de nova academia e o health check rodam sem tenant.
Quem exige contexto declara o grupo — e a ausência dele vira exceção na
primeira consulta, falha alta e óbvia em vez de vazamento silencioso.

A ordem exigiu intervenção no framework:

```php
$middleware->prependToPriorityList(
    before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
    prepend: \App\Tenancy\Middleware\TransacaoDoTenant::class,
);
```

**Por quê:** o `Authenticate` do Laravel implementa `AuthenticatesRequests`, que
consta da lista de prioridade do framework. Middleware fora dessa lista — como
os nossos — é **reordenado para depois dele**, mesmo declarado antes. O efeito
era `Auth::user()` recarregar o usuário da sessão sem contexto e fora de
transação. Em teste passava despercebido porque `actingAs()` injeta o usuário
sem tocar o banco: **só aparece com sessão de verdade.**

### 5.5 Camada 2 — Autenticação

Dois guards **separados**, com tabelas, telas e sessões distintas:

| Guard | Tabela | Quem | Login |
|---|---|---|---|
| `web` | `users` | Equipe da academia | `/entrar` |
| `aluno` | `alunos` | Alunos, no portal | `/portal/entrar` |

Pontos de atenção resolvidos:

- **Rotas de gestão usam `auth:web` explícito**, não `auth`. Com `auth` genérico,
  um aluno autenticado no guard `aluno` (que passa a ser o guard padrão da
  requisição) **entrava em `/painel`**.
- **Redirecionamento de visitante por área.** Sem isso, o aluno com sessão
  expirada em `/portal/aulas` caía no login da *equipe* — tela que ele não tem
  como usar, e que ainda revela que existe um sistema de gestão ali.
- `Aluno` expõe `$hidden = ['senha', 'remember_token', 'restricoes_saude']`.
- Usuário desativado no meio do expediente perde tudo imediatamente (seção 5.7),
  mesmo com a sessão ainda válida.

### 5.6 Camada 4 — Criptografia em repouso

Cast `encrypted` / `encrypted:array` do Laravel, aplicado ao que a LGPD trata
como **dado sensível** (art. 5º, II) e ao que é sigiloso por natureza:

| Campo | Modelo | Natureza |
|---|---|---|
| `restricoes_saude` | `Aluno` | Saúde |
| `respostas` | `Anamnese` | Saúde (texto livre do PAR-Q) |
| `observacao` | `Atestado` | Saúde |
| `medidas` | `AvaliacaoFisica` | Saúde (`encrypted:array`) |
| `salario_centavos` | `Colaborador` | RH |

> **Limitação conhecida:** a chave é a `APP_KEY` da aplicação, única para todos
> os tenants. Chave por tenant é débito aberto (seção 14).

### 5.7 Camada 3 — Autorização

Detalhada na seção 6. Um ponto de segurança merece destaque aqui — o portão
único:

```php
Gate::before(function (mixed $usuario) {
    if (! $usuario instanceof User) {
        return false;                       // aluno não passa por gate de equipe
    }
    return $usuario->ativo ? null : false;  // desativado não pode nada
});
```

O parâmetro é `mixed`, não `User`. Com a assinatura tipada, um `Aluno` chegando
ao Gate estourava `TypeError` e **a resposta virava 500 em vez de negação** —
pior de duas maneiras: esconde a decisão de autorização atrás de um erro, e
erro de servidor costuma ser tratado como falha de infraestrutura em vez de
tentativa de acesso indevido.

### 5.8 Camada 5 — Auditoria

Trait `Auditavel` grava em `auditoria`: evento (`created`/`updated`/`deleted`),
FQCN do model, id, `jsonb` com as alterações, usuário, IP e user-agent. A tabela
tem `REVOKE UPDATE, DELETE` — **nem a aplicação nem um invasor com a role de
runtime conseguem reescrever a trilha.**

### 5.9 Arquivos

`TenantStorage` grava em disco **privado**, sob `tenants/{tenant_id}/`. O
download passa por rota autenticada com **URL assinada e temporária**
(`/arquivos/{caminho}`), nunca por link direto ao filesystem. O teste
`TIso05ForaDoBancoTest` verifica que um tenant não alcança o arquivo do outro.

### 5.10 API de dispositivos (catraca)

Endpoint `POST /api/dispositivos/acesso`. Características de segurança:

- Autenticação por **`X-Dispositivo-Serie` + `X-Dispositivo-Chave`**, com a
  chave guardada como **hash** (`chave_hash`) e comparada com função resistente
  a *timing attack*.
- A consulta roda **sob o contexto do tenant resolvido pelo host** — um
  equipamento de uma academia não se autentica no endereço de outra, mesmo com
  a chave correta.
- Erro genérico (`credencial invalida`, 401): não revela se a série existe ou se
  só a chave está errada.
- Rotação de chave disponível (`POST /dispositivos/{id}/nova-chave`, gerência).
- O endpoint **não autoriza a passagem** — o equipamento aciona o relê sozinho e
  este endpoint só registra o que já aconteceu. Recusar aqui não impediria a
  entrada, apenas apagaria o registro dela.
- Aceita **lote de até 500 eventos** (terminal offline acumula e reenvia), com
  `insertOrIgnore` e chave única para que o reenvio não infle a frequência.
- Identificador não-UUID é tratado antes da consulta: consultar direto
  estouraria `invalid input syntax for type uuid` e **abortaria a transação**,
  derrubando o lote inteiro por causa de um evento malformado.

### 5.11 Outras medidas

- **Identificadores em SQL dinâmico** (nomes de tabela nas migrations) passam por
  `preg_match('/^[a-z_][a-z0-9_]*$/')` — não podem ser parametrizados por bind.
- **Erros em JSON** apenas para `api/*` ou `Accept: application/json`.
- Host desconhecido → **404**, para não confirmar a existência de um tenant.
- Sessão persistida em tabela com `tenant_id`.
- `password_reset_tokens` tem `tenant_id` com DEFAULT no banco, mantendo o
  `WITH CHECK` válido para o mecanismo interno do Laravel.

---

## 6. Níveis de usuário e matriz de permissões

### 6.1 Os papéis

Definidos em `App\Models\User::PAPEIS`:

| Papel | Constante | Perfil |
|---|---|---|
| **Gerente** | `gerente` | Responde pela academia: preço, encerramento, dinheiro que sai, quem tem acesso |
| **Recepção** | `recepcao` | Balcão: matrícula, baixa, caixa, venda, agenda |
| **Professor** | `professor` | Técnico: ficha clínica e treino. **Não vê dinheiro** |

Mais o **Aluno**, que não é `User` — vive em outro guard e só alcança o portal.

A divisão segue **o dinheiro e o acesso**. Um caixa que pode alterar o próprio
preço de venda e criar o próprio usuário não tem controle nenhum.

### 6.2 Matriz completa (28 permissões)

`✔` permitido · `—` negado

| Permissão | Gerente | Recepção | Professor | Razão registrada no código |
|---|:--:|:--:|:--:|---|
| `gerenciar-usuarios` | ✔ | — | — | Quem cria login decide quem entra no sistema |
| `gerenciar-planos` | ✔ | — | — | Preço é combinado comercial |
| `encerrar-contrato` | ✔ | — | — | Idem |
| `ver-relatorios` | ✔ | — | — | Faturamento é número de gerência |
| `ver-indicadores` | ✔ | — | — | A matriz 14.1 tira o dashboard gerencial da recepção |
| `ver-fiscal` | ✔ | — | — | Nota fiscal é assunto de quem responde perante o fisco |
| `ver-tesouraria` | ✔ | — | — | Saldo de todas as contas é informação de dono |
| `gerenciar-contas-a-pagar` | ✔ | — | — | Quem está no balcão não decide pagamento a fornecedor |
| `estornar-recebimento` | ✔ | — | — | **Quem recebe não deve poder desfazer sozinho** |
| `gerenciar-produtos` | ✔ | — | — | Mexe em preço e é onde some mercadoria |
| `gerenciar-dispositivos` | ✔ | — | — | Cadastrar terminal = decidir quem entra no prédio |
| `gerenciar-colaboradores` | ✔ | — | — | Salário e vínculo são dado de gerência |
| `bloquear-acesso` | ✔ | — | — | Barra alguém na porta por decisão de pessoa, não por regra |
| `configurar` | ✔ | — | — | Quem mexe na tolerância decide quem entra amanhã |
| `vender` | ✔ | ✔ | — | O professor não está no caixa |
| `matricular` | ✔ | ✔ | — | Dinheiro |
| `dar-baixa` | ✔ | ✔ | — | Dinheiro |
| `ver-inadimplencia` | ✔ | ✔ | — | Dinheiro |
| `operar-caixa` | ✔ | ✔ | — | Caixa é de quem recebe dinheiro |
| `gerenciar-agenda` | ✔ | — | ✔ | Criar turma e cancelar aula afetam todos os inscritos |
| `prescrever-treino` | ✔ | — | ✔ | Prescrição não é balcão |
| `ver-restricoes-de-saude` | ✔ | — | ✔ | **A ficha clínica é do professor antes do gerente** |
| `gerenciar-alunos` | ✔ | ✔ | ✔ | O professor precisa achar quem vai treinar |
| `ver-frequencia` | ✔ | ✔ | ✔ | — |
| `ver-ficha-de-saude` | ✔ | ✔ | ✔ | "Quem entra?" — o balcão precisa responder |
| `registrar-atestado` | ✔ | ✔ | ✔ | O papel chega no balcão |
| `operar-agenda` | ✔ | ✔ | ✔ | É o telefonema do aluno pedindo vaga |
| `trabalhar-retencao` | ✔ | ✔ | ✔ | **Retenção é operação, não gerência** |

### 6.3 Três decisões de granularidade que merecem explicação

**Saúde é dividida em três permissões, não uma.** Juntar tudo obrigaria a dar o
acesso mais amplo a quem tem a necessidade mais estreita:

| Permissão | Pergunta que responde | Quem precisa |
|---|---|---|
| `ver-ficha-de-saude` | "esta pessoa pode entrar?" | Balcão |
| `registrar-atestado` | "chegou o papel" | Balcão |
| `ver-restricoes-de-saude` | lesão, medicação | Clínico (professor) |

**Retenção é de todo mundo.** Trancar a fila de contato com o dono garantiria
que ninguém a trabalhasse — quem liga para o aluno que sumiu é a recepção ou o
professor.

**O gate não substitui a checagem de CREF.** Gerente e professor *montam* a
ficha; **assinar** exige registro vigente, e quem decide isso é
`FichaDeTreino::publicar` (seção 9.6) — não o Gate.

### 6.4 Proteção do último gerente

`User` conta gerentes **ativos** do tenant para impedir que o último se rebaixe,
se desative ou se apague. Sem isso, uma academia se tranca fora do próprio
sistema com dois cliques.

### 6.5 A interface não oferece o que a rota recusaria

O menu lateral filtra cada item pela permissão antes de renderizar:

```php
$visiveis = array_filter($itens, fn ($i) => $i[3] === null || auth()->user()->can($i[3]));
```

Isso é **ergonomia, não segurança** — a rota checa de novo.

---

## 7. Modelo de dados

**57 tabelas**, 50 models Eloquent. Organizadas por domínio:

### 7.1 Tenancy e plataforma (7)

| Tabela | Papel |
|---|---|
| `tenants` | O cliente do SaaS. Status, trial, tema (white-label), `expurgo_em` |
| `empresas` | CNPJ, razão social, IE — única por `(tenant, cnpj)` |
| `unidades` | Endereço físico, ativa/inativa |
| `users` | Equipe. `papel`, `ativo` |
| `parametros` | Chave/valor por tenant, com `alterado_por` |
| `uso_diario` | Snapshot para cobrança e capacidade (medição não é retroativa) |
| `auditoria` | Trilha append-only |

### 7.2 Pessoas e matrícula (7)

| Tabela | Papel |
|---|---|
| `pessoas` | **Núcleo de identidade.** Aluno, fornecedor e colaborador apontam para cá |
| `alunos` | Vínculo com `pessoa`, unidade, senha do portal, bloqueio manual, restrições |
| `planos` | Valor, duração, fidelidade, taxa de adesão, ciclo |
| `plano_modalidade` | Quais modalidades o plano inclui |
| `contratos` | Matrícula. Fidelidade, dia de vencimento, congelamento, multa |
| `congelamentos` | Períodos de trancamento |
| `leads` | Funil comercial: origem, etapa, conversão |

> **Por que `pessoas` separada de `alunos`:** o mesmo CPF pode ser aluno,
> responsável por um menor, fornecedor e colaborador. Duplicar cadastro
> significa telefone atualizado em um lugar e desatualizado nos outros.

### 7.3 Financeiro — a receber (4)

`parcelas` · `recebimentos` · `cobrancas` · (projeções em `parcelas`)

### 7.4 Financeiro — tesouraria (4)

`contas` · `plano_de_contas` (hierárquico, `pai_id`) · `caixas` · `movimentos`

### 7.5 Financeiro — a pagar (4)

`fornecedores` · `despesas_recorrentes` · `contas_a_pagar` · `pagamentos`

### 7.6 Saúde e LGPD (4)

`atestados` · `anamneses` (versionadas, PAR-Q em `jsonb`) · `consentimentos`
(por finalidade e versão, com IP/user-agent e revogação) · campos cifrados em
`alunos`/`pessoas`

### 7.7 Treino (5)

`exercicios` · `fichas_de_treino` (com **snapshot** de `professor_nome` e
`professor_cref`) · `treino_itens` (divisão A/B/C/D, séries, repetições, carga,
descanso) · `treino_execucoes` · `avaliacoes_fisicas`

### 7.8 Agenda (5)

`modalidades` · `salas` (capacidade) · `aulas` (turma recorrente) ·
`aulas_agendadas` (ocorrência materializada, com **cópia** de capacidade e
professor) · `reservas` (confirmada / espera / cancelada / no-show, check-in)

### 7.9 Balcão e estoque (4)

`produtos` (com NCM/CFOP/CEST para o fiscal) · `movimentos_estoque` ·
`vendas` · `venda_itens`

### 7.10 Serviços avulsos (3)

`contratos_de_personal` (mensal ou % por sessão) · `pacotes` (sessões
contratadas, validade) · `sessoes`

### 7.11 CRM e retenção (2)

`interacoes` (polimórfica: `sobre_tipo`/`sobre_id`) · `tarefas` (com o índice
parcial de uma aberta por motivo)

### 7.12 Acesso físico (2)

`dispositivos` (chave hasheada, último contato) · `acessos` (liberado / negado /
desconhecido, `ocorrido_em` vs `recebido_em`)

### 7.13 Fiscal (1)

`notas_fiscais` — máquina de estados de 6 posições, com `tentativas` e
`proxima_tentativa_em` para o *backoff*.

### 7.14 Infraestrutura do framework (5)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
`password_reset_tokens`.

---

## 8. Módulos, telas e CRUDs

**135 rotas**, 31 controllers, 62 views.

### 8.1 CRUDs completos

| Recurso | Rotas | Permissão |
|---|---|---|
| **Alunos** | index · create · store · edit · update · destroy | `gerenciar-alunos` |
| **Planos** | index · create · store · edit · update · destroy | `gerenciar-planos` |
| **Exercícios** | index · create · store · edit · update · destroy | `prescrever-treino` |
| **Usuários** | index · create · store · edit · update | `gerenciar-usuarios` |
| **Colaboradores** | index · create · store · edit · update | `gerenciar-colaboradores` |
| **Dispositivos** | index · create · store · edit · update · **rotacionar chave** | `gerenciar-dispositivos` |
| **Contas a pagar** | index · create · store · **pagar** | `gerenciar-contas-a-pagar` |

> Não há `destroy` para usuários, colaboradores e dispositivos: são desativados
> (`ativo = false`), porque apagar quebraria a trilha de auditoria e as FKs
> históricas.

### 8.2 Fluxos de operação (não são CRUD)

| Módulo | Ações |
|---|---|
| **Contratos** | index · create · store · show · **congelar** · **descongelar** · **encerrar** |
| **Agenda** | grade · **criar turma** · **abrir grade** · **reservar** · **cancelar reserva** · **check-in** · **fechar chamada** · **cancelar aula** |
| **Treinos** | index · create · show · atualizar · adicionar item · remover item · **publicar** · registrar execução |
| **Caixa** | index · **abrir** · **movimentar** (sangria/suprimento) · **fechar** |
| **Balcão (PDV)** | index · **vender** · **cancelar venda** · produtos · movimentar estoque · **inventariar** |
| **Parcelas** | **pagar** (baixa) · **estornar recebimento** |
| **Saúde** | mostrar · atualizar · registrar atestado · responder anamnese · consentimentos · **bloquear** · **desbloquear** |
| **Retenção/CRM** | fila · funil · criar lead · mover lead · criar tarefa · concluir tarefa · registrar contato · timeline |
| **Fiscal** | index · **reenfileirar** · **cancelar nota** |
| **Avaliações** | index · store |
| **Configurações** | mostrar · salvar (a tela se desenha a partir de `config/parametros.php`) |

### 8.3 Telas de leitura

`/painel` · `/indicadores` · `/relatorios` · `/frequencia` · `/inadimplencia` ·
`/tesouraria` · `/perfil`

### 8.4 Portal do aluno (guard `aluno`)

`/portal` · `/portal/treino` (com **registro de execução**) · `/portal/aulas`
(reservar e cancelar) · `/portal/financeiro` · `/portal/evolucao` ·
`/portal/senha`

### 8.5 Área do provedor (sem tenant)

`palaistra.test/` (institucional) · `/cadastro` (auto-serviço de nova academia)
· `/up` (health check)

---

## 9. Regras de negócio

Esta é a seção central. Cada regra traz o **código do requisito**, o **cálculo**
e a **razão** — porque a razão é o que impede a regra de ser "simplificada"
errado no futuro.

### 9.1 Acesso do aluno — a decisão de porta (RF-10-04)

`Aluno::motivoDeBloqueio()` devolve **o motivo**, não um booleano. A recepção
precisa dizer *por que* a catraca barrou. A ordem de avaliação é significativa:

```
1. Bloqueio manual        → o motivo registrado pela administração
2. Sem contrato ativo     → "Sem matrícula ativa."
3. TODOS os contratos congelados → "Matrícula trancada."
4. Parcela vencida além da tolerância → "Mensalidade em atraso."
5. Atestado vencido (SE configurado)  → "Atestado médico vencido..."
→ null = pode entrar
```

**Por que "todos" no passo 3 (RN-05):** quem tranca a musculação e continua na
natação **entra pela natação**. Basta um contrato ativo não-congelado.

**Por que existe tolerância (padrão 10 dias):** barrar alguém na porta no dia
seguinte ao vencimento, na frente das outras pessoas, **custa mais em
cancelamento do que a mensalidade atrasada** — e a régua de cobrança ainda está
agindo dentro dessa janela. O número é da academia, não do código.

**Por que o atestado é condicional (RN-02):** o padrão é **não** bloquear. A
maioria das academias quer o registro e o alerta sem barrar ninguém na porta.
São três parâmetros distintos (exigir / validade / bloquear) porque juntá-los
num interruptor só forçaria a escolha errada nas duas pontas.

### 9.2 Fidelidade e multa de cancelamento (RF-03-11 / RN-06)

```
multa = (mensalidade × meses_restantes_de_fidelidade × percentual) ÷ 100
```

- **Sem fidelidade vigente → multa ZERO.** Cobrar de quem nunca combinou
  fidelidade é exatamente o tipo de cláusula que gera passivo judicial.
- A base são **apenas as mensalidades que faltam**, não o contrato inteiro.
- O percentual é parametrizado com **teto de 50%** e padrão de 10%.

O CDC exige penalidade *"proporcional ao período restante e ao desconto
concedido, **nunca confiscatória**"*. O teto baixo no próprio campo é o que
impede uma configuração distraída de virar cláusula abusiva.

### 9.3 Congelamento (RN-05)

- Limite padrão: **30 dias por ano**, parametrizável (0–180).
- A janela é **móvel (últimos 12 meses)**, não o ano civil. Com ano civil o
  aluno tranca 30 dias em dezembro e mais 30 em janeiro — dobra o limite sem
  quebrar regra nenhuma.
- O período **suspende a cobrança**, **prorroga o fim do contrato** pelos mesmos
  dias e **bloqueia o acesso**.

### 9.4 Régua de cobrança (RF-04 / `cobranca.marcos`)

- Marcos padrão: **3, 7, 15 e 30 dias** de atraso.
- Cada marco dispara **uma única vez por parcela** — garantido por
  `unique (tenant_id, parcela_id, marco)` no banco, não por verificação em PHP.
- O espaçamento crescente é proposital: nos primeiros dias costuma ser
  esquecimento, e insistir cedo demais irrita quem ia pagar sozinho.
- **Mudar a lista não reenvia o passado** — marcos novos só valem para quem
  cruzar o dia daqui em diante.
- Envio às **09:00**, horário comercial: aviso que chega às três da madrugada
  parece spam, e o aluno só resolve quando a academia abre.

### 9.5 Baixa, estorno e a projeção da parcela

`Parcela::recalcular()` é o **único escritor** de `recebido_centavos` e `status`:

```php
$recebido = $this->recebimentos()->validos()->sum('valor_centavos');

'status' => match (true) {
    $this->status === STATUS_CANCELADA => STATUS_CANCELADA,
    $recebido >= $this->valor_centavos => STATUS_PAGA,
    $recebido > 0                      => STATUS_PARCIAL,
    default                            => STATUS_ABERTA,
}
```

- `validos()` exclui os estornados — estorno **não apaga** o recebimento,
  carimba `estornado_em`, `estornado_por` e `motivo_estorno`.
- **Estorno é permissão de gerente.** Um estorno sem justificativa é
  indistinguível de desfalque na conferência de caixa.
- **Saldo, não valor cheio:** parcela parcialmente paga deve apenas o que falta.
  Todo cálculo de inadimplência usa `valor_centavos - recebido_centavos`.
- Há um teste que **recomputa a projeção a partir dos recebimentos** e compara —
  a defesa contra a projeção divergir da fonte.

### 9.6 Prescrição de treino e CREF (RF-11 / RN-13)

`FichaDeTreino::publicar(Colaborador $professor)` recusa em quatro situações:

| Impedimento | Mensagem |
|---|---|
| Colaborador sem CREF | "Este colaborador não tem CREF cadastrado." |
| Inativo ou desligado | "Colaborador inativo ou desligado." |
| CREF sem validade cadastrada | "O CREF está sem data de validade cadastrada." |
| CREF vencido | "CREF vencido em dd/mm/aaaa." |
| Ficha sem nenhum exercício | "A ficha não tem nenhum exercício." |

**Por que a ficha vazia entra na mesma lista:** ficha publicada sem exercício é
ficha que o aluno abre e não encontra nada — e a culpa cai no sistema, não em
quem esqueceu.

Ao publicar, a ficha grava **snapshot** de `professor_nome` e `professor_cref`.
Se o professor sair da academia amanhã, a ficha continua sabendo quem assinou —
que é o que a responsabilidade técnica exige.

**Validade da ficha:** padrão 60 dias. Ficha eterna é aluno repetindo o mesmo
treino por um ano — e é assim que ele para de ver resultado e cancela.

### 9.7 Reserva de aula (RN-16 / RN-17)

`AulaAgendada::impedimentoParaReservar(Aluno)` avalia, em ordem:

1. Aula cancelada
2. Aula já começou
3. **Qualquer motivo de bloqueio do aluno** (reaproveita 9.1 — quem não pode
   entrar não pode reservar)
4. **Modalidade não inclusa no plano** (RN-16)
5. **Suspensão por no-show** (RN-17)

> **Exceção importante em 4:** plano *sem nenhuma modalidade cadastrada* é
> tratado como **plano livre**. Academia que não usa a grade não deve ser
> bloqueada por ela.

**No-show e suspensão (RN-17), tudo parametrizável:**

| Parâmetro | Padrão | Razão |
|---|---|---|
| Antecedência para cancelar | 4 h | Abaixo disso a vaga não dá tempo de ser reaproveitada pela fila |
| Faltas até suspender | 3 | 0 desliga a penalidade |
| Janela de contagem | 30 dias | Falta antiga sai da conta — penalidade que nunca prescreve vira castigo permanente |
| Duração da suspensão | 7 dias | — |

A suspensão atinge **só a reserva antecipada**: o aluno continua entrando na
academia e pode pegar vaga que sobrar na hora.

**Fila de espera:** quando alguém cancela, `promoverDaEspera()` puxa o próximo.

**Grade deslizante:** 14 dias por padrão. Materializar o ano inteiro faria mudar
o horário da turma em março exigir corrigir 40 ocorrências de janeiro; e grade
aberta longe demais enche de reserva que ninguém lembra de ter feito — o no-show
sobe sem que o aluno tenha "furado" de verdade.

### 9.8 Caixa (RF-06 / RN-18)

- **No máximo um caixa aberto por conta** — índice parcial. Dois atendentes
  clicando "abrir caixa" ao mesmo tempo passariam os dois por uma verificação em
  PHP, e o dia inteiro de conferência ficaria dividido entre duas sessões sem
  ninguém entender por quê.
- Fechamento: `divergencia = informado − apurado`.
- **`RN-18`: divergência ≠ 0 sem justificativa → recusa fechar.**
- A divergência é **coluna gravada**, não cálculo. Recalcular a partir de
  movimentos que podem ter sido estornados depois daria outro número — e é a
  quebra por operador que o relatório agrega.
- Justificativa só é gravada quando há o que justificar.

### 9.9 Movimentos financeiros — o livro-razão

- **Sinal explícito** (`sentido = 'entrada' | 'saida'`) com valor **sempre
  positivo**, em vez de valor negativo. Soma errada de sinal é o bug de
  relatório financeiro mais difícil de achar, **porque o total parece
  plausível**.
- Espécies: `recebimento`, `sangria`, `suprimento`, `transferencia`,
  `pagamento`, `estorno`, `ajuste`.
- `caixa_id` nulo quando o dinheiro cai direto no banco (PIX fora do balcão,
  boleto).
- **Nunca se apaga nem se altera o original**: o contra-movimento aponta para o
  estornado via `estorna_id`.
- Classificação por `plano_de_contas` hierárquico (`pai_id`), com natureza
  receita/despesa.

### 9.10 Contas a pagar (RF-05)

- Geração das recorrentes roda **todo dia**, não só no dia 1º: uma despesa
  cadastrada no meio do mês precisa gerar a competência corrente na hora, e se a
  execução de um dia falhar a do dia seguinte corrige sozinha.
- Rodar todo dia só é seguro porque a geração é **idempotente** pelo par
  `(recorrente, competência)` — com unicidade no banco.
- Executa às **04:00**, antes da régua: a agenda de pagamentos do dia precisa
  estar pronta quando o financeiro abrir o sistema.
- `ContaAPagar::recalcular()` é o único escritor de `pago_centavos` e `status`,
  no mesmo padrão de `Parcela`.

### 9.11 Balcão / PDV (RF-08)

- Duas formas de quitação: **`avista`** (gera recebimento na hora) e
  **`na_conta`** (vira parcela do aluno — RF-08-03).
- Baixa de estoque via `movimentos_estoque`, append-only.
- Produto pode ter `controla_estoque = false` (serviço).
- **Inventário** é ação separada da movimentação comum e é **permissão de
  gerência** — é onde some mercadoria.
- Cancelamento de venda carimba `cancelada_em` + motivo; não apaga.

### 9.12 Pacotes e personal (RF-12)

- Pacote = N sessões contratadas, com `valido_ate`.
- Saldo = contratadas − consumidas, computado das `sessoes` — o mesmo padrão de
  livro-razão.
- `Pacote::impedimento()` recusa consumo quando vencido ou sem saldo.
- Remuneração do personal: **mensal fixa** ou **percentual por sessão**.

### 9.13 Fiscal — NFS-e (M07 / RN-19)

Máquina de estados:

```
pendente → processando → emitida
                      ↘ rejeitada      (exige correção)
                      ↘ contingencia   (serviço fora do ar; reenfileira)
         emitida      → cancelada
```

- **Regime de caixa (RN-19):** a nota é gerada **a cada recebimento**, não a
  cada competência.
- Uma nota por origem — `unique (tenant_id, origem_tipo, origem_id)`.
- Retentativa com `tentativas` + `proxima_tentativa_em` (backoff).
- ISS em **centésimos de ponto percentual** (200 = 2,00%), porque a alíquota é
  **municipal** e varia por item da LC 116/2003 — não pode ser constante no
  código.
- Interface `DriverFiscal` com contrato explícito: **não lança exceção nem para
  rejeição nem para indisponibilidade** — as duas são respostas normais do
  serviço e viram estado, não erro.
- **Hoje só existe `DriverDeSimulacao`.** Ver seção 14.

### 9.14 Retenção (RF-15-03) — a regra mais valiosa

- Alerta quando o aluno some por **10 dias** (padrão).
- O número é **menor que a tolerância de cobrança**, e isso é intencional: a
  queda de frequência antecede o cancelamento em **semanas**. Esperar um mês
  para ligar é ligar depois da decisão.
- Abre **tarefa de contato**, com o índice parcial garantindo **uma aberta por
  motivo** — o job roda todo dia e não pode empilhar dez tarefas para o mesmo
  aluno.
- Executa às **07:30**: a fila precisa estar pronta quando a recepção abrir —
  ligação de retenção se faz de manhã, não no fim do dia.

### 9.15 LGPD

| Exigência | Implementação |
|---|---|
| Consentimento por finalidade | `consentimentos` versionados, com IP, user-agent, quem colheu |
| Revogação | `revogado_em` — não se apaga o consentimento, registra-se a revogação |
| Dado sensível protegido | Cifrado em repouso (5.6) |
| Menor de idade | `alunos.responsavel_id` → `pessoas` |
| Direito ao esquecimento | `tenancy:expurgar`, lendo `tenants.expurgo_em` |
| Prova do expurgo | Registro **fora** do tenant que deixa de existir |
| Trilha inviolável | `REVOKE UPDATE, DELETE ON auditoria` |

### 9.16 Anamnese / PAR-Q

Anamnese é **versionada** (`unique (tenant_id, aluno_id, versao)`), nunca
sobrescrita: a resposta de dois anos atrás é o que justifica a prescrição
daquela época. O PAR-Q estruturado vai em `jsonb` (consultável); o texto livre
vai em coluna **cifrada**.

### 9.17 Frequência e catraca

- `resultado ∈ {liberado, negado, desconhecido}`.
- `ocorrido_em` (relógio do equipamento) é distinto de `recebido_em` (chegada no
  servidor) — a diferença é o atraso do lote, exposto em `atrasoEmMinutos()`.
- Reenvio idempotente (5.10).
- Distribuição por faixa horária dimensiona **escala de professor e grade de
  aulas**, não só o relatório.

---

## 10. Indicadores e suas fórmulas

`App\Support\Kpis` (seção 13 do LR-ACAD-001). Todos os indicadores sem base
devolvem `null`, e a interface escreve `—`.

| Indicador | Fórmula | Regra especial |
|---|---|---|
| **Alunos ativos** | contratos com status ativo | — |
| **MRR** | soma dos planos dos contratos ativos | — |
| **Ticket médio** | MRR ÷ alunos ativos | — |
| **Churn** | cancelamentos ÷ **ativos no início do mês** | `null` se base = 0 |
| **Renovação** | renovados ÷ vencidos no período | Renovado = novo contrato entre **−7 e +30 dias** do fim do anterior |
| **Inadimplência** | vencido ÷ **tudo em aberto** | Denominador inclui o não-vencido |
| **LTV** | ticket médio × permanência média | Só **contratos encerrados**; `null` se ninguém saiu |
| **Frequência média** | visitas ÷ alunos no mês | — |
| **Ausentes há N dias** | alunos sem acesso desde N | A ponte para a retenção |
| **Distribuição horária** | acessos agrupados por hora | RF-16-03 |
| **Entrou hoje** | recebimentos do dia | — |
| **Recebido no mês** | recebimentos do período | — |

### Três decisões de medição que mudam o número

**Renovação com janela de −7 a +30 dias.** Renovação raramente acontece no
mesmo dia; quem volta na semana seguinte renovou, e tratá-lo como perdido
subestima a retenção.

**LTV só com contratos encerrados.** Quem ainda está na academia **não terminou
de gerar valor**, e incluir o tempo parcial de quem fica puxa a média para
baixo — justamente ao contrário do que o indicador quer dizer. Sem histórico de
saída o LTV é `null`, porque um número chutado aqui vira teto de CAC errado.

**Inadimplência com denominador amplo.** É o que torna o número comparável
entre meses. Usar só o vencido daria **sempre 100%**.

### Indicadores deliberadamente ausentes

**CAC por canal** e **conversão de experimental** não aparecem porque dependem
de dados que o sistema ainda não coleta (investimento de marketing, registro de
aula experimental). Mostrar zero neles seria pior que omiti-los — **zero parece
medição**.

---

## 11. Rotinas automáticas

Nove comandos agendados (`routes/console.php`). A ordem dos horários é
deliberada e está comentada no código.

| Hora | Comando | O quê | Por que nesse horário |
|---|---|---|---|
| 02:30 | `backup:criar` | Dump diário (RF-17-04) | O dump abre transação longa e concorre por I/O |
| 03:10 | `uso:snapshot` | Medição de uso | Banco ocioso. **Medição não é retroativa** — dia não coletado é dia perdido para sempre |
| 03:40 | `tenancy:expurgar` | Expurgo LGPD | Depois da medição, para o último dia do tenant ainda ser contado |
| 04:00 | `despesas:gerar` | Contas recorrentes | Antes da régua; a agenda do dia pronta na abertura |
| 04:20 | `aulas:abrir-grade` | Janela deslizante de 14 dias | — |
| 07:30 | `retencao:alertar` | **Fila de contato** | Ligação de retenção se faz de manhã |
| 09:00 | `cobranca:enviar` | Régua de cobrança | Horário comercial: madrugada parece spam |
| 09:30 | `assinatura:verificar` | Trial/suspensão do SaaS | Quem for suspenso precisa falar com alguém no mesmo dia |
| de hora em hora | `fiscal:processar` | Fila de NFS-e | Retentativa com backoff |
| domingo | `backup:verificar` | **Restaura o dump** num banco temporário | RNF-07 pede trimestral; aqui é semanal — a diferença de custo é desprezível e a de risco não é |

Todos com `withoutOverlapping()` e `onOneServer()`.

> **"Backup nunca restaurado não é backup."** A hora de descobrir que o dump
> estava corrompido não pode ser a do incidente. Essa verificação é a razão de
> `app_migrator` ter `CREATEDB`.

### Contexto de tenant em fila

`App\Tenancy\Queue\AplicarContextoDeTenant` + trait `JobDoTenant` serializam o
tenant no job e o reaplicam no worker. Há um teste que sobe um **worker real em
processo separado** (`tenancy:verificar-worker`) — não um `Queue::fake()`.

---

## 12. Padrões de implementação

Cinco padrões se repetem em todo o sistema. Reconhecê-los é o atalho para ler
qualquer módulo novo.

### 12.1 Livro-razão (ledger) — usado três vezes

| Domínio | Tabela | Proteção |
|---|---|---|
| Dinheiro | `movimentos` | `REVOKE UPDATE, DELETE` |
| Estoque | `movimentos_estoque` | `REVOKE UPDATE, DELETE` |
| Sessões | `sessoes` consumidas de `pacotes` | Saldo computado |

Regra comum: **append-only**, erro se corrige com contra-lançamento, saldo é
sempre derivado.

### 12.2 Projeção com escritor único

Valor derivado que precisa ser rápido de consultar (`parcelas.recebido_centavos`,
`contas_a_pagar.pago_centavos`, `caixas.divergencia_centavos`) tem:

1. **exatamente um** método que o escreve (`recalcular()`);
2. um **teste que recomputa da fonte** e compara.

Sem o teste, a projeção diverge silenciosamente da verdade — e é o tipo de bug
que só aparece na conferência do contador.

### 12.3 Parâmetro em vez de constante

`App\Support\Parametros` lê de `parametros` com fallback para
`config/parametros.php`. Detalhes:

- O memo é **indexado por tenant** (`[$tenant_id][$chave]`) — cache global
  vazaria exatamente como o D-01.
- Salvar valor **igual ao padrão apaga a linha** — a academia volta a seguir o
  padrão do produto se ele mudar.
- Chave desconhecida → `InvalidArgumentException`. Não há parâmetro implícito.
- O catálogo é indexado **diretamente**, não por `config("parametros.{$chave}")`:
  a chave *contém* ponto, e o helper leria `acesso.dias_de_tolerancia` como dois
  níveis de array aninhado.
- A tela de configurações **se desenha a partir do catálogo** — adicionar um
  parâmetro é adicionar uma entrada, sem um segundo lugar para lembrar de mexer.

### 12.4 Snapshot de dado que muda

Quando o registro precisa sobreviver à mudança da fonte, o valor é **copiado**:

| Onde | O que é copiado | Por quê |
|---|---|---|
| `fichas_de_treino` | `professor_nome`, `professor_cref` | Responsabilidade técnica de quem assinou |
| `aulas_agendadas` | `capacidade`, `professor_id`, `sala_id` | Mudar a turma não pode reescrever o passado |
| `venda_itens` | `preco_unitario_centavos` | O preço de hoje não muda a venda de ontem |
| `avaliacoes_fisicas` | `avaliador_nome`, `avaliador_cref` | Idem |

### 12.5 Estado nunca só pela cor

Toda sinalização visual carrega **pelo menos duas pistas**: o item ativo do menu
tem fundo, barra lateral e peso da fonte; anéis e barras carregam **o número
escrito**; avisos têm barra colorida além do texto. A paleta categórica foi
validada por script contra a superfície escura (faixa de luminosidade OKLCH,
piso de croma, separação para daltonismo, contraste).

### 12.6 White-label que sobrevive ao tema escuro

A cor da marca é do cliente (M18) — e azul-marinho ou vinho **somem** sobre a
casca escura `#14071f`, deixando o item ativo do menu invisível.
`TemaDoTenant::realceSobreEscuro()` clareia a marca até **3:1**, preservando o
matiz (o azul continua azul). A tinta *sobre* a marca também é calculada, nunca
fixada: branco sobre amarelo dá 1,46:1.

---

## 13. Testes e integração contínua

### 13.1 Números

```
54 arquivos de teste · 572 testes · 567 passando · 5 pulados · 1.596 asserções
```

Os 5 pulados são a suíte `Pool`, que se auto-marca como *skipped* sem PgBouncer
(débito D-07) — no CI ela roda.

### 13.2 Suítes

| Suíte | O que prova |
|---|---|
| `Isolation` (T-ISO 01–06) | Tabela protegida · sem vazamento · contexto na conexão · arquivo fora do banco · falha fora de transação |
| `Pool` | O cenário do D-01: PgBouncer, pool de 1, 2 clientes concorrentes |
| `Feature` | Cada módulo, ponta a ponta |
| `Unit` | Cálculos puros (dinheiro, tema, pró-rata) |

**T-ISO-01 varre o catálogo do Postgres** e quebra o build se alguma tabela com
`tenant_id` escapar do `Rls::protect`. Não é uma lista mantida à mão.

**Teste de mutação manual:** sabotando `Rls::protect` de propósito, **9 de 35**
testes de isolamento falham. Suíte que continua verde com a proteção quebrada
não estava medindo nada.

### 13.3 Defeitos que os testes verdes não pegaram

Documentados porque são o argumento mais forte deste projeto:

| Defeito | Por que passou | Como foi pego |
|---|---|---|
| **Fuso horário**: app em UTC, sessão do Postgres em `America/Sao_Paulo` → todo `timestamptz` gravado **3 h no futuro** | Escrita e leitura se cancelavam. 440 testes verdes | Inspeção do banco |
| **Gate com `User` tipado** → aluno gerava **500 em vez de negação** | Nenhum teste autenticava aluno numa rota de equipe | Teste cruzado de guards |
| **Rotas com `auth` sem guard** → `actingAs($aluno,'aluno')` deixava o aluno entrar em `/painel` | Idem | Idem |
| **`TenantStorage::put()`** devolvia caminho já prefixado, e os demais métodos prefixavam de novo → download de atestado quebrado desde o M02 | O teste verificava a gravação, não o **round-trip** | T-ISO-05 reforçado |
| **PDV com `min:1` por linha** quebraria toda venda real (o formulário posta zero nos produtos não vendidos) | O teste montava o carrinho só com o item vendido | Uso real |
| **Ordem de middleware** — `Auth::user()` fora de contexto | `actingAs()` injeta o usuário **sem tocar o banco** | Sessão de verdade |
| **Anel em zero** desenhava um ponto (`stroke-linecap: round` num traço de comprimento 0) → 0% parecia 1% | Layout não tem teste que valha a pena escrever | Screenshot |

O padrão: **o teste conveniente não exercita o caminho real.**

### 13.4 CI (GitHub Actions)

Dois jobs, ambos com PHP 8.5 e PostgreSQL 18:

1. **Isolamento (Postgres direto)** — cria roles e privilégios a partir de
   `db/01-roles.sql` e `db/02-grants.sql`, roda a *prova de fogo* (`pg_roles`
   sem `BYPASSRLS` para o runtime), executa a suíte e o teste de worker real.
2. **Pool / PgBouncer (D-01)** — sobe `edoburu/pgbouncer`, **migra fora do
   pooler** (DDL não sobrevive a transaction mode), confirma que o PgBouncer
   responde e roda a suíte de concorrência.

---

## 14. Estado atual, débitos e pendências

### 14.1 O que está pronto

| Camada | Situação |
|---|---|
| Fundação multi-tenant | ✅ Aceite da Fase 0 fechado |
| Schema | ✅ 57 tabelas, todas sob RLS |
| Domínio | ✅ 50 models, regras de negócio implementadas e testadas |
| Interface | ✅ 135 rotas, 31 controllers, 62 views, tema escuro com white-label |
| Automação | ✅ 9 rotinas agendadas |
| Testes | ✅ 572, com mutação verificada |
| CI | ✅ 2 jobs, incluindo PgBouncer |

### 14.2 O buraco, e ele tem um nome só

> **O sistema está completo como sistema de registro. Toda fronteira com o
> mundo externo é simulação ou não existe.**

| Falta | Situação real no código | Travado por |
|---|---|---|
| **Gateway de pagamento** | `FORMA_PIX` e `FORMA_BOLETO` existem apenas como rótulo contábil de lançamento manual. **Não há emissão de PIX, geração de boleto nem webhook de conciliação — a cobrança é digitada por uma pessoa** | Escolha do gateway + credenciais |
| **NFS-e** | `DriverFiscal` + `DriverDeSimulacao`. Fila, reenvio e cancelamento funcionam; nada chega à prefeitura | Município, certificado A1, intermediário |
| **Catraca (D-03)** | Endpoint HTTP e model `Dispositivo` prontos e testados. **O driver do equipamento, não** | Payload/SDK da ZKTeco |
| **WhatsApp** | A régua envia **só e-mail** (`Mail::to`) | Provedor |
| **Exportar PDF/XLSX** (RF-16-06) | Não existe. Relatórios só em tela | *(trabalho interno)* |
| **Deploy Linux** | Nunca rodou fora do Laragon | Servidor |

### 14.3 Débitos técnicos

| # | Débito | Impacto | Bloqueia |
|---|---|---|---|
| ~~D-01~~ | ✅ **Fechado.** Vazamento entre clientes sob transaction pooling | — | — |
| **D-02** | `TenantContext` usa propriedade `static` | Sob Octane, estado compartilhado entre requisições no mesmo worker. Pior caso hoje é bem menor: o GUC nunca sobrevive à transação | Adoção de Octane |
| **D-03** | POC de catraca e de gateway não feitos | Equipamento sem SDK utilizável é dinheiro parado | Fase 2 / compra de hardware |
| ~~D-04~~ | ✅ Hierarquia `tenant → empresa → unidade` | — | — |
| ~~D-05~~ | ✅ `uso_diario` coletado desde já | — | — |
| ~~D-06~~ | ✅ CI com dois jobs | — | — |
| **D-07** | Sem PgBouncer local (Windows, sem WSL) | A suíte `Pool` só roda no CI; erro de pooler só aparece depois do push | Iteração rápida |
| **—** | **Chave de criptografia única** (`APP_KEY`) para todos os tenants | Vazamento da chave expõe todos os clientes de uma vez | Compliance mais estrita |
| **—** | Portal do aluno ainda no layout antigo | Cosmético | — |

### 14.4 Fora de código

Contrato de DPA/LGPD com os clientes.

---

## 15. Glossário

| Termo | Significado |
|---|---|
| **Tenant** | Uma academia cliente do SaaS. Resolvido pelo subdomínio |
| **RLS** | *Row Level Security* — filtro de linhas aplicado pelo próprio PostgreSQL |
| **FORCE RLS** | Faz a política valer inclusive para o dono da tabela |
| **GUC** | *Grand Unified Configuration* — variável de sessão/transação do Postgres. Aqui: `app.tenant_id` |
| **Transaction pooling** | Modo do PgBouncer em que a conexão de servidor é devolvida ao pool a cada transação |
| **Ledger** | Livro-razão append-only |
| **Projeção** | Valor derivado gravado por desempenho, com escritor único e teste de recomputação |
| **Marco (régua)** | Dia de atraso em que um aviso de cobrança é disparado |
| **No-show** | Falta em aula reservada |
| **Competência** | Mês de referência de uma despesa (`AAAA-MM`) |
| **PAR-Q** | Questionário de prontidão para atividade física |
| **CREF** | Registro profissional de educação física |
| **MRR** | *Monthly Recurring Revenue* |
| **LTV** | *Lifetime Value* |
| **CAC** | Custo de aquisição de cliente |
| **Pró-rata** | Cobrança proporcional aos dias do primeiro mês |
| **White-label** | Personalização visual por cliente (M18) |
| **Expurgo** | Exclusão definitiva dos dados de um tenant cancelado (LGPD) |

---

*Documento gerado por leitura direta do código-fonte. Onde há divergência entre
este texto e o repositório, o repositório está certo.*
