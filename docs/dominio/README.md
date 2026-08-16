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
| CPF do aluno | **Obrigatório**, com validação matemática dos dígitos |
| Data de nascimento | **Obrigatória**, validada por faixa de idade |
| WhatsApp do aluno | **Obrigatório** — é o canal de cobrança e a base da lista de aniversariantes |
| Primeiro mês | **Não é proporcional.** Existe período de experiência antes; a matrícula só começa com contrato assinado |
| Tolerância na catraca | 5 dias após o vencimento, configurável por academia |
| Multa por cancelamento | Campo existe, padrão zero |
| Área "Meu Pulso" | Fora desta versão, mas o cadastro já nasce preparado |
| Sessão do usuário | Única por padrão; login novo derruba a sessão anterior |
| Super administrador | Só o plano de controle. **Não** enxerga aluno, mensalidade nem biometria de academia alguma |
| Idade do aluno | Mínima **12 anos**, máxima 99. Abaixo de 18, responsável obrigatório |
| Validação de CPF | Só matemática (dígitos verificadores). Sem consulta a serviço pago |
| Ordem de construção | Design system completo **antes** das telas de CRUD |

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

**A ordem do middleware importa.** `DefinirAcademiaAtual` precisa rodar **antes** do `SubstituteBindings` do Laravel: é o `SubstituteBindings` que transforma `/alunos/1` no model, e essa busca já passa pelo RLS. Com a academia ainda indefinida, o banco devolve zero linhas e a tela vira 404 — para um aluno que existe e pertence a quem está logado. Garantido em `bootstrap/app.php` e coberto por teste.

Três detalhes que decidem se isso funciona ou vira enfeite:

1. **`FORCE ROW LEVEL SECURITY`** — sem isso, o dono da tabela ignora a política. Como as migrations rodam por `pgsql_admin`, o dono seria justamente quem escapa.
2. **`pulso_app` sem `SUPERUSER` e sem `BYPASSRLS`** — já garantido em [`../../database/postgres/01-bancos-e-papeis.sql`](../../database/postgres/01-bancos-e-papeis.sql) e verificado.
3. **`current_setting(..., true)`** — o segundo argumento evita erro quando a variável não foi definida (comandos de console, filas). Nesse caso a comparação dá nulo e **nenhuma linha aparece** — falha fechando, que é o comportamento certo.

**Unidade não entra no RLS.** O isolamento obrigatório é entre academias; entre unidades da mesma academia é questão de permissão, não de segurança de dados — o dono da rede precisa ver tudo.

---

## 4. As entidades

Convenções aplicadas a todas: chave `bigint` gerada por identidade, `created_at`/`updated_at` em `timestamptz`, dinheiro em `numeric(10,2)`, e datas que representam dia (vencimento, nascimento) em `date` — nunca `timestamp`, senão o fuso vira o dia.

### 4.1 Estrutura da academia

**`academias`** — o cliente do Pulso. É também a tela de configurações que a academia edita, e a fonte dos dados que saem nos PDFs (contrato, recibo, relatório).

| Campo | Tipo | Observação |
|---|---|---|
| `nome` | text | Nome fantasia |
| `razao_social` | text, nulo | Sai no contrato |
| `cnpj` | text, nulo, único | Nulo permite cadastrar antes de ter CNPJ |
| `email`, `telefone`, `whatsapp` | text | Contato administrativo |
| `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf` | text, nulo | Cabeçalho dos PDFs |
| `logo_path` | text, nulo | Logo da academia nos PDFs — **não** substitui a marca Pulso na interface |
| `dias_tolerancia_bloqueio` | smallint | Padrão 5. Depois do vencimento, quantos dias a catraca ainda libera |
| `dias_baixa_frequencia` | smallint | Padrão 15. A partir de quantos dias sem passar na catraca o aluno entra no Radar |
| `idade_minima` | smallint | Padrão **12**. Academia com atividade infantil pode baixar |
| **Controle do SaaS** — só o super administrador altera | | |
| `situacao` | text | `ativa`, `em_aviso`, `bloqueada`, `cancelada` — ver §5.6 |
| `assinatura_vence_em` | date, nulo | |
| `bloqueada_em` | timestamptz, nulo | |
| `motivo_bloqueio` | text, nulo | Visível ao super administrador, não à academia |
| `criada_em` | timestamptz | |

> **Duas metades na mesma tabela.** A primeira a academia edita nas configurações; a segunda só o super administrador toca. A separação é de permissão, não de tabela — dividir em duas obrigaria a um `join` em toda tela.

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
| `academia_id` | bigint, nulo | **Nulo = super administrador** (equipe do Pulso), que não pertence a academia nenhuma |
| `name`, `email`, `password` | | Do Laravel |
| `preferencias` | jsonb | Tema, estado da barra lateral, itens por página, colunas visíveis. Ver §5.7 |
| `sessao_unica` | boolean | **Padrão verdadeiro.** Login novo derruba a sessão anterior |
| `minutos_inatividade` | smallint, nulo | Nulo usa o padrão do sistema |
| `ativo` | boolean | Demitiu, desativa. Nunca apaga, senão o histórico perde o autor |
| `bloqueado_ate` | timestamptz, nulo | Preenchido pelo bloqueio por tentativas — ver §5.8 |
| `ultimo_acesso_em` | timestamptz, nulo | |

**`unidade_user`** — as unidades vinculadas explicitamente ao usuário.

> **Vínculo vazio não significa "todas".** Quem enxerga a rede inteira tem `acessa_todas_unidades` marcado. A regra anterior — sem vínculo, vê tudo — falhava **abrindo**: uma recepcionista cadastrada às pressas ganhava a academia inteira, em silêncio. Contradizia o princípio usado no resto do sistema, onde sem academia definida o banco devolve zero linhas.

Como um usuário novo nasce, conforme o papel (`App\Support\Academia\PadroesDeAcesso`):

| Papel | Acessa todas | Pode alternar |
|---|:--:|:--:|
| `dono` | ✅ | ✅ |
| `gerente` | — (escolhe quais) | ✅ |
| `recepcao` | — (escolhe quais) | — travado |
| `professor` | — (escolhe quais) | — travado |

Quem não pode alternar vê **texto simples** no cabeçalho, não uma lista desabilitada: controle desligado na tela anuncia algo que a pessoa não pode fazer, e isso só gera pergunta para o gerente.

**`tentativas_login`** — trilha de auditoria das tentativas.

| Campo | Tipo | Observação |
|---|---|---|
| `email` | text | O que foi digitado, mesmo se não existir |
| `ip` | inet | |
| `agente` | text, nulo | Navegador |
| `sucesso` | boolean | |
| `ocorreu_em` | timestamptz | |

> O bloqueio em si acontece no limitador de taxa (rápido, em cache). Esta tabela existe para responder depois *"quem tentou entrar na conta da recepção às 3 da manhã?"* — pergunta que o cache não responde.

**`sessions`** — a tabela padrão do Laravel, necessária para a sessão única e para o timeout. Exige `SESSION_DRIVER=database` (hoje está em `file`) ou Redis com rastreamento.

### 4.2.1 Papéis e o que cada um enxerga

Via `spatie/laravel-permission`, com times = academia.

| Tela / ação | Super admin | Dono | Gerente | Recepção | Professor |
|---|:--:|:--:|:--:|:--:|:--:|
| Academias (criar, bloquear, avisar) | ✅ | — | — | — | — |
| Configurações da academia | ✅ | ✅ | 👁 | — | — |
| Unidades | ✅ | ✅ | 👁 | — | — |
| Usuários e papéis | ✅ | ✅ | — | — | — |
| Planos | ✅ | ✅ | 👁 | 👁 | — |
| Alunos — listar e ver | ✅ | ✅ | ✅ | ✅ | ✅ |
| Alunos — criar e editar | — | ✅ | ✅ | ✅ | — |
| Alunos — excluir | — | ✅ | ✅ | — | — |
| Matrículas | — | ✅ | ✅ | ✅ | 👁 |
| Mensalidades — ver | — | ✅ | ✅ | ✅ | — |
| Mensalidades — receber, estornar | — | ✅ | ✅ | ✅ | — |
| Biometria — cadastrar | — | ✅ | ✅ | ✅ | — |
| Catracas e dispositivos | — | ✅ | ✅ | — | — |
| Radar | — | ✅ | ✅ | 👁 parcial | — |
| Relatório financeiro | — | ✅ | ✅ | — | — |
| Frequência dos alunos | — | ✅ | ✅ | ✅ | ✅ |
| Consolidado da rede | — | ✅ | — | — | — |

✅ pode agir · 👁 só visualiza · — não vê a tela

Três regras que sustentam a tabela:

1. **Professor não vê dinheiro.** Nem valor de plano, nem mensalidade, nem quem está devendo. Vê aluno e frequência.
2. **Recepção vê a inadimplência do aluno na ficha, mas não o relatório financeiro consolidado** — precisa saber para atender, não para conhecer o faturamento.
3. **Gerente atua só nas unidades a que está vinculado**; dono enxerga a rede inteira.

### 4.2.2 Super administrador

O super administrador (`users.academia_id` nulo) **não enxerga dados de aluno, mensalidade ou biometria de academia nenhuma** — nem por engano, porque as políticas de RLS não têm exceção para ele.

Ele opera apenas sobre o que chamamos de **plano de controle**: `academias`, `unidades`, `avisos_academia` e os usuários. Essas tabelas não têm política de isolamento por academia.

**`avisos_academia`** — o recado que aparece na tela da academia.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id` | bigint, nulo | Nulo = aviso para todas |
| `tipo` | text | `informativo`, `atencao`, `bloqueio_iminente` |
| `titulo`, `mensagem` | text | |
| `exibir_de`, `exibir_ate` | date | |
| `dispensavel` | boolean | Se o usuário pode fechar o aviso |
| `criado_por` | bigint | |

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
| `nome` | text | Gravado em caixa de título — ver §5.9 |
| `cpf` | text | **Obrigatório**, 11 dígitos, único por academia, dígitos verificadores conferidos |
| `data_nascimento` | date | **Obrigatória**, validada por faixa — ver §5.10 |
| `sexo` | text, nulo | |
| `email` | text, nulo | Único por academia quando preenchido, para servir de login no "Meu Pulso" depois |
| `telefone` | text, nulo | |
| `whatsapp` | text | **Obrigatório** — canal de cobrança e base da lista de aniversariantes |
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
| `multa_cancelamento` | numeric(10,2) | Padrão zero. Quem não usa, não vê o campo |
| `dias_experiencia` | smallint | Teto de 30. Zero = plano sem experiência |
| `sessoes_experiencia` | smallint | Quantas passagens na catraca o teste permite. Zero = sem limite de sessões |
| `ativo` | boolean | Plano descontinuado não some: matrículas antigas apontam para ele |

> **A experiência acaba pelo que vier primeiro:** os dias ou as sessões. Uma academia usa "7 dias", outra usa "3 aulas experimentais", e uma terceira usa os dois. Os dois campos existirem custa duas colunas; faltar um obriga a refazer a regra depois.

**`matriculas`** — o vínculo aluno ↔ plano ↔ unidade.

| Campo | Tipo | Observação |
|---|---|---|
| `academia_id`, `unidade_id`, `aluno_id`, `plano_id` | bigint | |
| `tipo` | text | `experiencia` ou `regular` |
| `situacao` | text | Ver máquina de estados em §5.1 |
| `contrato_assinado_em` | date, nulo | **Matrícula regular não existe sem isto** — ver §5.2 |
| `sessoes_usadas` | smallint | Só na experiência: quantas passagens já foram consumidas |
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

### 5.2 Da experiência à primeira mensalidade

**Não existe cobrança proporcional.** A sequência é:

1. **Experiência** — matrícula `tipo = experiencia`, sem mensalidade nenhuma. Acaba pelo que vier primeiro: os `dias_experiencia` ou as `sessoes_experiencia` do plano. Teto absoluto de 30 dias.
2. **Conversão** — a recepção registra o contrato assinado (`contrato_assinado_em`). Sem essa data, a matrícula regular não pode ser criada. A regra vive no banco, não na tela.
3. **Primeiro vencimento** — a recepção escolhe entre:
   - **30 dias após o início** (ex.: começou 22/03, vence 21/04, depois todo dia 21); ou
   - **dia fixo do mês** (ex.: dia 5), com a primeira mensalidade caindo no próximo dia 5.

O `dia_vencimento` da matrícula fica entre **1 e 28**.
*Por quê 28:* dia 31 não existe em fevereiro. Aceitar 29–31 obrigaria a uma regra de ajuste ("cai no último dia do mês") que a recepção teria de explicar toda vez. Limitar a 28 elimina o caso.

**Geração automática:**

4. Uma rotina diária gera a mensalidade do mês seguinte para toda matrícula `ativa`.
5. `competencia` é o primeiro dia do mês de referência; `vencimento` é `competencia` + o dia escolhido.
6. Matrícula `experiencia`, `suspensa`, `encerrada` ou `cancelada` **não gera** mensalidade.
7. A rotina é **idempotente**: rodar duas vezes no mesmo dia não duplica. Garantido por índice único em (`matricula_id`, `competencia`).

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

Some junto, sem tabela nova: **aniversariantes do dia** — `alunos` com `data_nascimento` no dia e matrícula ativa. É por isso que a data de nascimento é obrigatória.

### 5.6 Situação da academia perante o Pulso

| Situação | O que acontece |
|---|---|
| `ativa` | Uso normal |
| `em_aviso` | Funciona igual, mas aparece o aviso do super administrador no topo de toda tela |
| `bloqueada` | Ninguém da academia entra. A catraca **continua liberando quem está em dia** — deixar aluno na porta por briga comercial entre a academia e o Pulso seria punir quem não tem nada com isso |
| `cancelada` | Encerrada. Dados preservados pelo prazo legal, acesso encerrado |

O super administrador nunca apaga uma academia: muda a situação.

### 5.7 Preferências do usuário

Guardadas em `users.preferencias` (JSONB), aplicadas no login em qualquer aparelho:

```json
{
  "tema": "escuro",
  "sidebar_recolhida": true,
  "itens_por_pagina": 25,
  "unidade_padrao": 3
}
```

> **JSONB e não colunas** porque essa lista vai crescer (colunas visíveis por tabela, ordenação preferida, filtros salvos), e cada preferência nova viraria uma migration. Preferência não é consultada em `WHERE` — só é lida junto com o usuário.

O tema já é guardado no navegador para não piscar antes da primeira pintura; o valor do perfil é o que manda quando o usuário entra em outro aparelho.

### 5.8 Segurança de acesso

**Bloqueio por tentativas** — dois limitadores independentes, porque atacam de dois jeitos:

| Limitador | Regra | Por quê |
|---|---|---|
| Por e-mail + IP | 5 tentativas por minuto | Padrão do Fortify: alguém errando a própria senha |
| Por e-mail | 10 tentativas em 15 min, depois `bloqueado_ate` por 30 min | Ataque distribuído contra **uma** conta, vindo de vários IPs |
| Por IP | 30 tentativas em 15 min | Varredura testando **vários** e-mails do mesmo lugar |

Toda tentativa vai para `tentativas_login`, com sucesso ou não. A mensagem ao usuário **nunca** diz se o e-mail existe.

**Timeout de inatividade** — sessão encerrada após N minutos sem requisição (padrão do sistema, ajustável por usuário em `minutos_inatividade`). A tela avisa antes de derrubar, para ninguém perder um cadastro pela metade.

**Sessão única** — `users.sessao_unica`, verdadeiro por padrão. Ao entrar, as demais sessões daquele usuário são invalidadas e a outra tela cai no próximo clique, com o aviso *"Sua conta foi acessada em outro aparelho."*

> Depende de `SESSION_DRIVER=database` (ou Redis com rastreamento). Hoje o ambiente local está em `file`, que não permite listar nem invalidar sessão alheia. **Mudança necessária antes de implementar.**

### 5.9 Como os nomes são gravados

Nome de pessoa, de unidade e de plano é gravado em **caixa de título brasileira**, independentemente de como foi digitado:

| Digitado | Gravado |
|---|---|
| `JOSE MARIA DA SILVA` | `Jose Maria da Silva` |
| `joSE MAria dA siLVA` | `Jose Maria da Silva` |
| `maria dos santos e souza` | `Maria dos Santos e Souza` |
| `luiz d'avila` | `Luiz D'Avila` |

Regras: primeira letra de cada palavra em maiúscula, **exceto** as conectivas `da, das, de, do, dos, e, di, du, van, von, y` — que ficam minúsculas **salvo quando são a primeira palavra**. Acentuação digitada é preservada.

A normalização acontece **ao gravar** (mutator no model), não na exibição: assim a busca, a ordenação e o PDF veem sempre o mesmo texto. Se ficasse só na exibição, a lista ordenada misturaria `SILVA` e `Silva`.

### 5.10 Validações obrigatórias

| Campo | Regra |
|---|---|
| CPF | 11 dígitos, dígitos verificadores conferidos, rejeita sequências (`111.111.111-11`), único por academia |
| Data de nascimento | Não pode ser futura; idade entre **12 e 99 anos** (mínima ajustável por academia) |
| WhatsApp | 10 ou 11 dígitos com DDD válido |
| CEP | 8 dígitos; preenche endereço pelo ViaCEP |
| UF e cidade | Da API do IBGE, não digitação livre |
| Menor de idade | Abaixo de 18, os campos de responsável passam a ser obrigatórios |

---

## 6. Contagem

| Grupo | Tabelas | Qtd |
|---|---|:--:|
| Plano de controle (super admin) | `academias`, `unidades`, `avisos_academia` | 3 |
| Pessoas | `users` (alterada), `unidade_user`, `profissionais`, `alunos` | 4 |
| Contrato | `planos`, `matriculas` | 2 |
| Financeiro | `mensalidades`, `pagamentos`, `cobrancas` | 3 |
| Acesso à academia | `credenciais_acesso`, `consentimentos_lgpd`, `dispositivos_acesso`, `acessos` | 4 |
| Notificação | `notificacoes` | 1 |
| Segurança | `tentativas_login`, `sessions` | 2 |
| Permissões (spatie) | 5 tabelas prontas | 5 |

**≈ 24 tabelas.** Enxuto para o que o sistema faz.

---

## 7. Pendências

### 7.1 Provedor de Pix — a decidir juntos

Combinado pesquisar e decidir mais adiante. Até lá, `cobrancas` fica **neutra**: `provedor`, `id_externo`, `payload` (JSONB) e situação. Qualquer candidato encaixa nesse formato sem migration.

Quando for a hora, comparo Asaas, Mercado Pago, Efí e integração direta com o banco por: custo do Pix, custo do cartão, prazo de repasse, qualidade do webhook, exigência de CNPJ e split para rede com filiais.

### 7.2 API de WhatsApp

Você informará qual usaremos. O modelo já prevê `notificacoes` com `canal`, `modelo`, `destino` e `situacao` — serve tanto para a API oficial da Meta quanto para intermediador, sem mudança de tabela.

---

## 8. Decisões fechadas nesta rodada

| Assunto | Decisão | Consequência no modelo |
|---|---|---|
| Alcance do super administrador | Só o plano de controle | Nenhuma exceção nas políticas de RLS. `academias`, `unidades` e `avisos_academia` ficam fora do isolamento por academia; todo o resto fica dentro, sem porta dos fundos |
| Idade mínima do aluno | 12 anos, ajustável por academia | `academias.idade_minima` com padrão 12; validação por faixa em `alunos.data_nascimento` |
| Verificação de CPF | Só matemática | Nenhuma dependência externa, nenhum custo por consulta, nada de dado pessoal saindo do sistema |
| Ordem de construção | Design system antes do CRUD | Etapa 2b passa a ser os componentes; migrations viram 2c |

**Sobre o super administrador — o que isso custa e por quê vale:** atender suporte fica mais difícil, porque você não enxerga a tela do cliente e depende do que ele descreve. Em troca, uma conta de super administrador comprometida **não** dá acesso a aluno, mensalidade ou biometria de academia nenhuma. Num sistema que guarda dado biométrico, essa é a troca certa. Se o suporte mostrar-se inviável na prática, a saída é impersonação auditada — não abrir o RLS.

---

## 9. Situação da implementação

| Etapa | Situação |
|---|---|
| Design system — 27 componentes, catálogo em `/catalogo` | ✅ |
| Migrations (26), models (16), enums, casts e RLS | ✅ |
| Testes de isolamento e de regras do banco | ✅ 87 casos |
| Telas de CRUD | ⬜ próxima |

### 9.1 Onde cada decisão virou código

| Decisão deste documento | Onde vive |
|---|---|
| Isolamento por RLS | `2026_08_16_001900_ativa_row_level_security` |
| Filtro na aplicação e preenchimento do `academia_id` | `App\Models\Concerns\PertenceAAcademia` |
| Academia da requisição vem do usuário | `App\Http\Middleware\DefinirAcademiaAtual` |
| "Vencida" não é coluna | `Mensalidade::scopeVencidas()` + índice parcial |
| Valor copiado do plano | `matriculas.valor_mensal` |
| Dia de vencimento entre 1 e 28 | `CHECK matriculas_dia_vencimento_valido` |
| Matrícula regular exige contrato | `CHECK matriculas_regular_exige_contrato` |
| Sem matrículas sobrepostas | `EXCLUDE matriculas_sem_sobreposicao` (btree_gist) |
| Uma mensalidade por competência | `UNIQUE mensalidades_uma_por_competencia` |
| Biometria exige consentimento | `CHECK credenciais_biometria_exige_consentimento` |
| Um lembrete por mensalidade | `UNIQUE notificacoes_um_lembrete_por_mensalidade` |
| Motivo do bloqueio nunca na catraca | `App\Enums\MotivoBloqueioAcesso` |
| Caixa de título ao gravar | `App\Casts\CaixaDeTitulo` |
| Só dígitos em documento e telefone | `App\Casts\ApenasDigitos` |
| Papéis por academia | `PapeisSeeder` + `teams` do spatie |

### 9.2 O que o teste de isolamento prova

Em `tests/Feature/Isolamento/`:

- a conexão da aplicação **não** é superusuária nem tem `BYPASSRLS` — sem isso, os demais casos passariam sem provar nada;
- dado de uma academia não aparece na outra, nem por Eloquent, nem por consulta crua, nem com os filtros da aplicação desligados;
- não se consegue **gravar**, **atualizar** nem **excluir** linha de academia alheia;
- sem academia definida, nada é visível — falha fechando;
- o super administrador não enxerga aluno, mensalidade nem biometria;
- toda tabela de domínio tem RLS **ativo e forçado**, com política. Criar tabela nova e esquecer a política quebra o teste.
