# Pulso — modelo de domínio

Documento de trabalho da etapa 2. **Nada aqui virou código ainda** — é para ser lido, riscado e corrigido antes das migrations, porque errar aqui custa uma frase e errar depois custa dias.

Base: [`../../README.md`](../../README.md) e [`../marca/README.md`](../marca/README.md).
Versão 1 — agosto/2026.

---

## 1. Decisões já tomadas

| Assunto | Decisão |
|---|---|
| Isolamento entre academias | Estrutura única, `academia_id` em toda tabela, filtro na aplicação **e** Row Level Security no banco |
| Identificação da academia | Domínio único, resolvida pelo usuário no login (sem subdomínio) |
| Filiais | **Sim** — uma academia pode ter várias unidades |
| Vencimento da mensalidade | **Dia fixo escolhido pelo aluno** na matrícula |
| Escopo desta versão | Cadastros, financeiro, controle de acesso e Radar. Sem aulas/turmas, sem treino/avaliação física, sem caixa/contas a pagar |
| Identificação na catraca | Reconhecimento facial, digital e cartão/chaveiro |

**Fora do escopo, por decisão:** aulas coletivas e turmas, ficha de treino, avaliação física, caixa diário, contas a pagar, venda de produtos, comissão de professor. Não é "esquecido" — é adiado, e o modelo abaixo não impede acrescentar depois.

---

## 2. Glossário

Estes são os nomes que aparecem na tela e no código. O guia de marca manda nomear pelo que a pessoa reconhece.

| Termo | O que é |
|---|---|
| **Academia** | O cliente do Pulso. Pode ser uma loja só ou uma rede. |
| **Unidade** | Onde o aluno treina. Toda academia tem pelo menos uma. |
| **Aluno** | Quem treina. Cadastrado uma vez por academia, mesmo numa rede. |
| **Profissional** | Professor, instrutor, personal. Pode ou não ter acesso ao sistema. |
| **Plano** | O que a academia vende: nome, valor, duração, se dá acesso a todas as unidades. |
| **Matrícula** | O vínculo entre um aluno e um plano, numa unidade, com início e fim. |
| **Mensalidade** | A cobrança de um mês de uma matrícula. Tem competência e vencimento. |
| **Pagamento** | O dinheiro que entrou. Uma mensalidade pode receber mais de um. |
| **Cobrança** | O Pix ou o link de cartão emitido no provedor, esperando pagamento. |
| **Credencial** | O jeito do aluno se identificar na catraca: rosto, digital ou cartão. |
| **Acesso** | Cada passagem (ou tentativa) na catraca. |
| **Radar** | A tela de alertas. Não é tabela — é consulta. |

---

## 3. Como o isolamento funciona na prática

Toda tabela de domínio tem `academia_id`. A política de RLS compara essa coluna com uma variável de sessão que a aplicação define logo após o login:

```sql
-- na tabela
ALTER TABLE alunos ENABLE ROW LEVEL SECURITY;
ALTER TABLE alunos FORCE  ROW LEVEL SECURITY;   -- vale até para o dono da tabela

CREATE POLICY isolamento_academia ON alunos
    USING      (academia_id = current_setting('pulso.academia_id', true)::bigint)
    WITH CHECK (academia_id = current_setting('pulso.academia_id', true)::bigint);
```

```php
// middleware, a cada request autenticado
DB::statement("SELECT set_config('pulso.academia_id', ?, false)", [$user->academia_id]);
```

Três detalhes que decidem se isso funciona ou vira enfeite:

1. **`FORCE ROW LEVEL SECURITY`** — sem isso, o dono da tabela ignora a política. Como as migrations rodam por `pgsql_admin`, o dono seria justamente quem escapa.
2. **`pulso_app` sem `SUPERUSER` e sem `BYPASSRLS`** — já garantido em [`../../database/postgres/01-bancos-e-papeis.sql`](../../database/postgres/01-bancos-e-papeis.sql) e verificado.
3. **`current_setting(..., true)`** — o segundo argumento evita erro quando a variável não foi definida (comandos de console, filas). Nesse caso a comparação dá nulo e **nenhuma linha aparece** — falha fechando, que é o comportamento certo.

**Unidade não entra no RLS.** O isolamento obrigatório é entre academias; entre unidades da mesma academia é questão de permissão, não de segurança de dados — o dono da rede precisa ver tudo.

---

## 4. As entidades

Convenções aplicadas a todas: chave `bigint` gerada por identidade, `created_at`/`updated_at` em `timestamptz`, dinheiro em `numeric(10,2)`, e datas que representam dia (vencimento, nascimento) em `date` — nunca `timestamp`, senão o fuso vira o dia.

### 4.1 Estrutura da academia

**`academias`** — o cliente do Pulso.

| Campo | Tipo | Observação |
|---|---|---|
| `nome` | text | Nome fantasia |
| `razao_social` | text, nulo | |
| `cnpj` | text, nulo, único | Nulo permite cadastrar antes de ter CNPJ |
| `email` | text | Contato administrativo |
| `telefone` | text | |
| `ativa` | boolean | Academia inadimplente com o Pulso é desativada, não apagada |
| `criada_em` | timestamptz | |

**`unidades`** — filiais.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint | |
| `nome` | text | "Centro", "Matriz", "Loja 2" |
| `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf` | text | |
| `telefone` | text, nulo | |
| `ativa` | boolean | |

> Toda academia nasce com uma unidade. Cliente de uma loja só nunca vê a palavra "unidade" na tela.

### 4.2 Quem usa o sistema

**`users`** — quem faz login. Já existe; ganha colunas.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint, nulo | Nulo = equipe do Pulso (suporte), que não pertence a academia nenhuma |
| `name`, `email`, `password` | | Do Laravel |
| `tema` | text | `claro`, `escuro` ou `sistema` — guardado no perfil, não só no navegador |
| `ativo` | boolean | Demitiu, desativa. Nunca apaga, senão o histórico perde o autor |
| `ultimo_acesso_em` | timestamptz, nulo | |

**`unidade_user`** — a quais unidades cada usuário tem acesso. Sem linha aqui, o usuário enxerga todas as unidades da academia (caso do dono).

**Papéis** (via `spatie/laravel-permission`, com times = academia):

| Papel | O que faz |
|---|---|
| `dono` | Tudo, em todas as unidades. Vê o consolidado da rede. |
| `gerente` | Tudo nas unidades a que tem acesso. Não mexe em usuários nem em planos. |
| `recepcao` | Cadastra aluno, matricula, registra pagamento, emite Pix. Não vê relatório financeiro consolidado. |
| `professor` | Vê os alunos da unidade e a frequência. Não vê nada de dinheiro. |

**`profissionais`** — professores e instrutores.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint | |
| `user_id` | bigint, nulo | Nulo = professor sem login no sistema |
| `nome` | text | |
| `cpf` | text, nulo | |
| `cref` | text, nulo | Registro no Conselho de Educação Física |
| `telefone`, `email` | text, nulo | |
| `ativo` | boolean | |

### 4.3 Aluno

**`alunos`** — cadastrado **por academia**, não por unidade: numa rede, o mesmo CPF não pode virar dois cadastros.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint | |
| `nome` | text | |
| `cpf` | text, nulo | Único por academia quando preenchido. Ver pendência §7.1 |
| `data_nascimento` | date | Define se é menor de idade |
| `sexo` | text, nulo | |
| `email` | text, nulo | |
| `telefone` | text, nulo | |
| `whatsapp` | text, nulo | Separado do telefone: nem todo telefone recebe WhatsApp |
| `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf` | text, nulo | |
| `foto_path` | text, nulo | Foto de identificação da recepção — **não** é template biométrico |
| `observacoes` | text, nulo | |
| `responsavel_nome`, `responsavel_cpf`, `responsavel_telefone`, `responsavel_parentesco` | text, nulo | Obrigatórios quando menor de 18 |
| `deleted_at` | timestamptz, nulo | Exclusão lógica: o histórico financeiro precisa sobreviver |

> **Exclusão lógica aqui, exclusão real na biometria.** O aluno some das listas, mas a mensalidade paga em março continua existindo. Já o template biométrico é apagado de verdade (§4.6).

### 4.4 Plano e matrícula

**`planos`** — o que a academia vende.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint | |
| `nome` | text | "Mensal musculação", "Anual completo" |
| `descricao` | text, nulo | |
| `valor_mensal` | numeric(10,2) | O que entra na mensalidade |
| `duracao_meses` | smallint | 1 = mensal; 3, 6, 12 = com prazo |
| `taxa_matricula` | numeric(10,2) | Zero quando não há |
| `acesso_todas_unidades` | boolean | Falso = só a unidade da matrícula |
| `dias_experiencia` | smallint | Quantos dias de teste esse plano concede |
| `ativo` | boolean | Plano descontinuado não some: matrículas antigas apontam para ele |

**`matriculas`** — o vínculo aluno ↔ plano ↔ unidade.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `unidade_id`, `aluno_id`, `plano_id` | bigint | |
| `tipo` | text | `experiencia` ou `regular` |
| `situacao` | text | Ver máquina de estados em §5.1 |
| `inicio_em` | date | |
| `fim_previsto_em` | date, nulo | `inicio + duracao_meses`; nulo em plano sem prazo |
| `encerrada_em` | date, nulo | |
| `motivo_encerramento` | text, nulo | |
| `dia_vencimento` | smallint | **1 a 28** — ver §5.2 |
| `valor_mensal` | numeric(10,2) | Copiado do plano na contratação. Reajuste no plano **não** altera matrícula existente |
| `observacoes` | text, nulo | |

> **Por que copiar o valor:** se a mensalidade lesse o preço do plano, aumentar o plano em janeiro mudaria retroativamente o que o aluno devia em novembro. O valor vive na matrícula.

Uma restrição no banco impede o erro mais comum: **o mesmo aluno não pode ter duas matrículas ativas com período sobreposto** na mesma unidade. É a constraint `EXCLUDE` com `btree_gist` — no MySQL isso viraria trigger ou torcida.

### 4.5 Financeiro

**`mensalidades`** — o que o aluno deve.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `unidade_id`, `matricula_id`, `aluno_id` | bigint | `aluno_id` repetido de propósito: o Radar consulta por aluno e evitar o `join` vale a redundância |
| `competencia` | date | Primeiro dia do mês de referência (2026-08-01) |
| `vencimento` | date | **`date`, nunca instante** |
| `valor` | numeric(10,2) | |
| `desconto` | numeric(10,2) | |
| `situacao` | text | `aberta`, `paga`, `cancelada` — ver §5.3 |
| `paga_em` | date, nulo | |
| `observacoes` | text, nulo | |

**"Vencida" não é coluna.** É `situacao = 'aberta' AND vencimento < hoje`. Guardar como estado exigiria um processo diário para virar a chave, e no dia em que ele falhasse o Radar mentiria. O custo de desempenho se resolve com índice parcial:

```sql
CREATE INDEX mensalidades_em_aberto
    ON mensalidades (academia_id, vencimento)
    WHERE situacao = 'aberta';
```

**`pagamentos`** — o dinheiro que entrou.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `mensalidade_id` | bigint | |
| `valor` | numeric(10,2) | |
| `forma` | text | `dinheiro`, `pix`, `cartao_credito`, `cartao_debito`, `transferencia` |
| `recebido_em` | date | |
| `registrado_por` | bigint, nulo | Qual usuário deu baixa. Nulo = baixa automática por webhook |
| `cobranca_id` | bigint, nulo | Quando veio de cobrança online |
| `estornado_em` | timestamptz, nulo | Estorno não apaga o pagamento |

> Separado da mensalidade porque **um pode receber vários**: aluno paga metade em dinheiro e metade no Pix.

**`cobrancas`** — o Pix ou link de cartão emitido no provedor.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `mensalidade_id` | bigint | |
| `provedor` | text | Ver pendência §7.4 |
| `id_externo` | text | Identificador no provedor |
| `tipo` | text | `pix`, `cartao` |
| `valor` | numeric(10,2) | |
| `situacao` | text | `emitida`, `paga`, `expirada`, `cancelada` |
| `pix_copia_cola` | text, nulo | |
| `url` | text, nulo | Link de pagamento |
| `expira_em` | timestamptz, nulo | |
| `payload` | jsonb | Retorno cru do provedor e dos webhooks — `JSONB` com índice GIN |

### 4.6 Controle de acesso e LGPD

**`credenciais_acesso`** — como o aluno se identifica.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `aluno_id` | bigint | |
| `tipo` | text | `facial`, `digital`, `cartao` |
| `template` | bytea, nulo | **Cifrado.** Só para `facial` e `digital`. Nunca a imagem do rosto |
| `identificador_cartao` | text, nulo | Só para `cartao` |
| `consentimento_id` | bigint, nulo | Obrigatório quando o tipo é biométrico |
| `ativa` | boolean | |
| `cadastrada_por` | bigint | Auditoria: quem coletou |
| `cadastrada_em` | timestamptz | |
| `excluida_em` | timestamptz, nulo | **Exclusão real do template**, mantendo o registro de que houve |

**`consentimentos_lgpd`** — o aceite, separado do contrato.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `aluno_id` | bigint | |
| `finalidade` | text | "Controle de acesso e frequência" — escrito, não implícito |
| `versao_texto` | text | Qual redação a pessoa leu |
| `aceito_em` | timestamptz | |
| `revogado_em` | timestamptz, nulo | Revogou, apaga o template e a credencial vira cartão |
| `origem` | text | `recepcao`, `app` |

**`dispositivos_acesso`** — as catracas.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `unidade_id` | bigint | |
| `nome` | text | "Catraca da entrada" |
| `fabricante` | text | Control iD, Topdata, Henry, Intelbras |
| `modelo`, `numero_serie` | text, nulo | |
| `endereco_ip` | inet, nulo | |
| `sentido` | text | `entrada`, `saida`, `ambos` |
| `ativo` | boolean | |
| `ultima_sincronizacao_em` | timestamptz, nulo | |

**`acessos`** — cada passagem. É a tabela que mais cresce.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `unidade_id`, `dispositivo_id` | bigint | |
| `aluno_id` | bigint, nulo | Nulo quando não reconheceu ninguém |
| `ocorreu_em` | timestamptz | |
| `resultado` | text | `liberado`, `bloqueado` |
| `motivo` | text, nulo | `inadimplente`, `matricula_encerrada`, `nao_reconhecido`, `fora_do_horario` |
| `tipo_credencial` | text | `facial`, `digital`, `cartao` |

> **O motivo fica no banco, nunca na tela da catraca.** Bloqueio por inadimplência mostra "Procure a recepção" (CDC art. 42). O `motivo` existe para a recepção entender, não para a fila ler.

### 4.7 Notificações

**`notificacoes`** — o que foi enviado ao aluno.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `aluno_id`, `mensalidade_id` | bigint | |
| `canal` | text | `whatsapp` (e-mail depois) |
| `modelo` | text | `tres_dias_antes`, `no_dia`, `vencida` |
| `destino` | text | Número usado no envio |
| `enviada_em` | timestamptz, nulo | |
| `situacao` | text | `na_fila`, `enviada`, `entregue`, `lida`, `falhou` |
| `erro` | text, nulo | |

> **Uma linha por envio**, para que a regra "cobrança vencida manda **um** lembrete, não uma sequência" seja verificável no banco em vez de confiada à memória do código.

---

## 5. Regras de negócio

### 5.1 Ciclo de vida da matrícula

```
                  ┌──────────────┐
   nova ─────────▶│ experiencia  │──── virou aluno ────┐
   (com teste)    └──────┬───────┘                     │
                         │ acabou o prazo              ▼
                         │                       ┌───────────┐
                         └──── não converteu ───▶│   ativa   │
                                    │            └─────┬─────┘
   nova (sem teste) ────────────────┼──────────────────┘  │
                                    │           trancou   │  destrancou
                                    ▼                     ▼      ▲
                              ┌───────────┐         ┌───────────┐│
                              │ encerrada │◀────────│ suspensa  ├┘
                              └───────────┘         └───────────┘
```

| Situação | O que significa | Catraca |
|---|---|---|
| `experiencia` | Testando, dentro do prazo do plano | Libera |
| `ativa` | Matrícula normal | Libera se estiver em dia |
| `suspensa` | Trancou (viagem, lesão) | Bloqueia. Não gera mensalidade |
| `encerrada` | Acabou o prazo ou o aluno saiu | Bloqueia |
| `cancelada` | Desfeita por erro de cadastro | Bloqueia. Não gera mensalidade |

### 5.2 Geração da mensalidade

1. O aluno escolhe o **dia de vencimento na matrícula**, entre **1 e 28**.
   *Por quê 28:* dia 31 não existe em fevereiro. Aceitar 29–31 obrigaria a uma regra de ajuste ("cai no último dia do mês") que a recepção teria de explicar toda vez. Limitar a 28 elimina o caso.
2. Uma rotina diária gera a mensalidade do mês seguinte para toda matrícula `ativa`, com antecedência configurável.
3. `competencia` é o primeiro dia do mês de referência; `vencimento` é `competencia` + o dia escolhido.
4. Matrícula `suspensa` ou `encerrada` **não gera** mensalidade.
5. A rotina é **idempotente**: rodar duas vezes no mesmo dia não duplica. Garantido por índice único em (`matricula_id`, `competencia`).

### 5.3 Estados da mensalidade

| Estado | Como se chega | Pílula na tela |
|---|---|---|
| `aberta`, vencimento no futuro | Gerada pela rotina | ⏱ A vencer |
| `aberta`, vencimento no passado | Passou a data sem pagamento | ! Vencida |
| `paga` | Soma dos pagamentos ≥ valor − desconto | ✓ Em dia |
| `cancelada` | Estorno, erro, isenção | — |

### 5.4 Liberação na catraca

Ordem de avaliação. O primeiro "não" bloqueia:

1. A credencial existe, está ativa e pertence a um aluno da unidade?
2. A matrícula está `ativa` ou em `experiencia`?
3. O plano dá acesso a **esta** unidade? (`acesso_todas_unidades`)
4. Existe mensalidade vencida além da tolerância? *(dias de tolerância — pendência §7.3)*

Bloqueou, a catraca mostra **"Procure a recepção"**. O motivo real vai para `acessos.motivo`.

### 5.5 O que o Radar mostra

Nenhuma tabela nova — são quatro consultas:

| Alerta | Consulta | Cor |
|---|---|---|
| Total vencido | `mensalidades` abertas com vencimento passado | vermelho |
| Vence hoje | `mensalidades` abertas com vencimento = hoje | âmbar |
| Baixa frequência | alunos ativos sem `acessos` nos últimos N dias | **roxo** |
| Risco de evasão | baixa frequência **e** mensalidade vencida | roxo |

> Baixa frequência é roxo de propósito: é risco de perder o aluno, não problema de caixa. Misturar com o vermelho faria a gestão tratar as duas coisas do mesmo jeito.

---

## 6. Contagem

| Grupo | Tabelas |
|---|---|
| Estrutura | `academias`, `unidades`, `unidade_user` | 3 |
| Pessoas | `users` (alterada), `profissionais`, `alunos` | 3 |
| Contrato | `planos`, `matriculas` | 2 |
| Financeiro | `mensalidades`, `pagamentos`, `cobrancas` | 3 |
| Acesso | `credenciais_acesso`, `consentimentos_lgpd`, `dispositivos_acesso`, `acessos` | 4 |
| Notificação | `notificacoes` | 1 |
| Permissões (spatie) | 5 tabelas prontas | 5 |

**≈ 21 tabelas.** Enxuto para o que o sistema faz.

---

## 7. Pendências — preciso da sua decisão

### 7.1 CPF é obrigatório no cadastro do aluno?

*Proposta:* **opcional, mas único quando preenchido.** A recepção precisa matricular alguém que esqueceu o documento em casa, e travar o cadastro por isso gera a gambiarra de digitar CPF falso. Se a cobrança online exigir CPF, ele passa a ser obrigatório só na hora de emitir a cobrança.

### 7.2 Primeiro mês é proporcional?

Matriculou dia 22, escolheu vencer todo dia 10.

- **(a) Proporcional:** cobra de 22 a 10 pelos dias corridos. Justo, mas a recepção precisa explicar um valor quebrado.
- **(b) Mês cheio:** cobra o valor integral no primeiro vencimento. Simples, mas o aluno reclama.
- **(c) Junto no seguinte:** o primeiro pedaço entra somado à segunda mensalidade.

*Proposta:* **(a) proporcional**, com o cálculo visível na tela para a recepção conseguir explicar.

### 7.3 Quantos dias de tolerância antes da catraca bloquear?

*Proposta:* **configurável por academia, padrão 5 dias.** Bloquear no dia seguinte ao vencimento gera briga no balcão; nunca bloquear torna a catraca inútil como instrumento de cobrança.

### 7.4 Qual provedor de cobrança Pix?

Muda os campos de `cobrancas`, o formato dos webhooks e o custo por transação. As opções realistas para academia pequena no Brasil são Asaas, Mercado Pago, Efí e integração direta com o banco. **Precisa da sua escolha** — ou, se ainda não decidiu, eu modelo `cobrancas` de forma neutra e a integração fica para uma etapa própria.

### 7.5 Plano com prazo tem multa por cancelamento?

Se o plano anual for pago mensalmente e o aluno sair no quinto mês, há multa? *Proposta:* campo `multa_cancelamento` no plano, com zero como padrão — assim quem não usa não vê.

### 7.6 O aluno terá acesso próprio ("Meu Pulso")?

O guia de marca prevê a área do aluno. Se entrar nesta versão, `alunos` precisa de credencial de login. *Proposta:* **não nesta versão**, mas já deixar `alunos.email` único por academia para não precisar migrar depois.

---

## 8. O que acontece depois deste documento

1. Você lê, risca e corrige — principalmente a §7.
2. Fecho as pendências e ajusto o modelo.
3. Só então: migrations, models, RLS, testes de isolamento e seeders com dados em pt-BR.
