# Pulso

> O pulso da sua academia.

Sistema web de gestão de academias. Alvo inicial: academias do Nordeste, tudo em português do Brasil, fuso `America/Fortaleza`.

## O que o sistema faz

- **Cadastros** — alunos, profissionais, planos, matrículas e o tempo de experiência a que o aluno tem direito.
- **Financeiro** — mensalidades pagas, a vencer e vencidas, com integração de cobrança (Pix, cartão).
- **Controle de acesso** — reconhecimento facial e biometria integrados à catraca, liberando ou bloqueando a passagem.
- **Radar** — área da equipe administrativa com inadimplência, baixa frequência e risco de evasão.
- **Notificações** — aviso ao aluno de mensalidade próxima do vencimento (WhatsApp).

## Stack

| Camada | Tecnologia | Versão |
|---|---|---|
| Linguagem | PHP | 8.3+ |
| Framework | Laravel | 13 |
| Interface | Livewire + Alpine, Blade | 4 |
| CSS | Tailwind | 4 |
| Banco | PostgreSQL | 16+ |
| Autenticação | Laravel Fortify (headless) | 1 |
| Permissões | spatie/laravel-permission | 8 |
| Build | Vite | 8 |
| Ambiente local | Laragon — `C:\laragon\www\pulso` | |

**Por que PostgreSQL e não MySQL:** o sistema é multi-academia e guarda dado biométrico. O Postgres oferece *Row Level Security* (o banco recusa ler dados de outra academia mesmo se um filtro faltar no código), `timestamptz` sem o teto de 2038, índices parciais para as consultas do Radar, constraints `EXCLUDE` para impedir matrículas sobrepostas, `JSONB` com índice para eventos de catraca e webhooks, e `pg_trgm` para buscar aluno por nome com erro de digitação.

## Como o isolamento entre academias funciona

Estrutura única, coluna `academia_id` em cada tabela, filtro automático na aplicação **e** política de Row Level Security no banco. São duas barreiras de propósito: a da aplicação é a que se usa no dia a dia; a do banco é a que segura quando alguém esquece um filtro.

Por isso existem duas conexões em `config/database.php`:

| Conexão | Usuário | Para quê |
|---|---|---|
| `pgsql` | `pulso_app` | A aplicação. **Não pode** ser superusuário nem dono das tabelas — o Postgres isenta esses papéis do RLS. |
| `pgsql_admin` | `postgres` | Só migrations e comandos de console. |

## Estrutura

```
app/
  Actions/Fortify/     Ações de autenticação (criar usuário, trocar senha)
  Casts/               Normalização ao gravar (caixa de título, só dígitos)
  Enums/               Situações e tipos do domínio
  Livewire/            Telas com estado: lista, formulário, detalhes
  Policies/            Quem pode o quê, conforme a matriz de papéis
  Rules/               CPF válido, data brasileira
  Services/Enderecos/  ViaCEP e IBGE, com cache e sem travar o cadastro
  Services/Radar/      Os números do Radar, separados da tela
  Services/Catraca/    Protocolo do leitor biométrico e a dedução de entrada/saída
  Http/Middleware/     DefinirAcademiaAtual — alimenta o RLS
  Models/              16 models; Concerns/PertenceAAcademia aplica o filtro
  Providers/           AppServiceProvider, FortifyServiceProvider
  Support/             Formatação pt-BR, validação de CPF/CNPJ, contexto
config/
  pulso.php            Nome, slogans e contatos — fonte única
database/
  postgres/            Criação de bancos, papéis e privilégios (SQL)
  migrations/          Migrations do Laravel
docs/
  dominio/             Modelo de dados, regras de negócio e permissões
  interface/           Máscaras, padrão de CRUD, layout e componentes
  comparativo-palaistra.md  Avaliação de outro sistema de academia: o que
                       aproveitar, o que descartar, e três achados que valem
                       decisão antes do deploy
docs/marca/            Guia de marca, tokens de design e assets
  README.md            Nome, slogans, logo, cores, temas, acessibilidade e LGPD
  tokens.css           Fonte da verdade das cores (temas claro e escuro)
  tokens.json          A mesma paleta em formato legível por ferramenta
  quadro-de-marca.html Quadro visual da identidade — abra no navegador
  assets/              Logos, ícones e estados da catraca em SVG
lang/pt_BR/            Mensagens de validação, autenticação e senha
resources/
  css/app.css          Ponte tokens -> Tailwind (@theme inline)
  js/
    app.js             Bundle público — leve, sem Alpine
    painel.js          Bundle do painel — Livewire + Alpine + máscaras
    tema.js            Troca de tema: claro / escuro / sistema
    mascaras.js        CPF, CNPJ, telefone, CEP, data, dinheiro
    notificacoes.js    Avisos rápidos ("Pagamento registrado")
    modais.js          Abertura dos <dialog>
  views/
    components/        Componentes Blade
      layout/          base, publico, acesso, painel
      marca/           logo
      painel/          item-navegacao
      ui/              27 componentes — ver docs/interface/
    site/inicio        Página inicial
    acesso/            Login, esqueci a senha, redefinir senha
    catalogo/          Catálogo do design system (fora de produção)
    painel/            Destino pós-login (provisório)
tests/
  Unit/                Sem banco
  Feature/             Com aplicação de pé
```

## Começando

```bash
composer install
npm install
cp .env.example .env      # no Windows: copy .env.example .env
php artisan key:generate
npm run build
```

### Endereço no ambiente local

O Laragon cria o host automaticamente a partir do nome da pasta:

**<http://pulso.test>**

Não é `http://localhost/pulso/public/`. O `DocumentRoot` do vhost **precisa** apontar para `C:/laragon/www/pulso/public` — o Laragon define isso na criação do host e não recorrige depois, então confira em `C:\laragon\etc\apache2\sites-enabled\auto.pulso.test.conf`. Apontar para a raiz do projeto expõe `.env`, `.git/`, `vendor/` e `storage/` pela web.

O [`.htaccess`](.htaccess) da raiz é uma rede de proteção contra esse erro: bloqueia arquivos ocultos, desliga a listagem de diretório e redireciona para `public/`. Ele **não** substitui configurar o vhost direito.

Sem Laragon, `php artisan serve` também funciona.

### Banco de dados

Dois papéis, por causa do Row Level Security (ver acima):

```bash
psql -U postgres -f database/postgres/01-bancos-e-papeis.sql
psql -U postgres -d pulso       -f database/postgres/02-privilegios.sql
psql -U postgres -d pulso_teste -f database/postgres/02-privilegios.sql
```

Depois preencha `DB_PASSWORD` e `DB_ADMIN_PASSWORD` no `.env` e rode:

```bash
composer db:migrar                          # migra o banco pulso
composer db:teste                           # recria o banco pulso_teste do zero
php artisan db:seed --database=pgsql_admin  # duas academias de demonstração
```

Migrations e seeders **não** rodam pela conexão padrão: `pulso_app` não tem permissão de criar tabela e está sujeito ao RLS — e é exatamente isso que faz o isolamento valer.

Acessos de demonstração, todos com a senha `pulso1234`:

| Quem | E-mail | Cai em |
|---|---|---|
| Dono da academia | `dono@alpha-fit.com.br` | `/painel` (Radar) |
| Recepção | `recepcao@alpha-fit.com.br` | `/painel` (Radar) |
| Equipe do Pulso | `suporte@usepulso.com.br` | `/administracao/academias` |

O último não pertence a academia nenhuma — é o super administrador. O destino de cada um é decidido pelo middleware, não pela tela de login.

> **No Laragon**, o `pg_hba.conf` vem com método `trust` — qualquer conexão local é aceita sem senha. É aceitável numa máquina de desenvolvimento. **Na VPS, use `scram-sha-256`** e senha forte, e não exponha a porta 5432 para fora.

## Rotinas agendadas

Na VPS, um cron chama o agendador do Laravel a cada minuto:

```cron
* * * * * cd /caminho/do/pulso && php artisan schedule:run >> /dev/null 2>&1
```

| Rotina | Quando | O que faz |
|---|---|---|
| `pulso:gerar-mensalidades` | 03:10, diário | Gera as mensalidades do mês para as matrículas ativas |
| `pulso:fechar-acessos` | 03:40, diário | Encerra as entradas na catraca que ficaram abertas |

A geração é **idempotente**: o índice único `(matricula_id, competencia)` impede duplicata, então rodar de novo é seguro. Roda pela conexão da aplicação — percorre as academias definindo o contexto de cada uma, e o RLS a autoriza uma a uma, sem precisar de um papel que atravessa o isolamento.

## Segurança do acesso

| Trava | Como funciona |
|---|---|
| Tentativas de login | 5 erros por e-mail ou 20 por IP em 15 minutos fecham a porta. Janela deslizante, sem rotina de desbloqueio. |
| Inatividade | Sessão parada encerra. Prazo por usuário (`minutos_inatividade`), 30 min de padrão; zero desliga. |
| Sessão única | Entrar num aparelho derruba os outros. Ligada por padrão. **Exige `SESSION_DRIVER=database`.** |
| Senha temporária | Usuário novo troca a senha no primeiro acesso, antes de chegar a qualquer tela. |

A mensagem de bloqueio é **idêntica** para conta existente e inexistente, e a contagem é por texto digitado — senão a tela de login vira um confirmador de contas.

## As duas áreas do sistema

| Área | Quem entra | Endereço |
|---|---|---|
| Painel da academia | quem tem `academia_id` | `/painel` |
| Administração do SaaS | quem tem `academia_id` **nulo** | `/administracao` |

**"Em aviso" não mostra mensagem nenhuma sozinho.** É só a marca comercial da pendência, do lado do Pulso. O texto que a academia lê vem de um **aviso publicado**, que tem vida própria — data de início e de fim. A ficha da academia mostra quais estão no ar para não confundir os dois.

O Pulso fala com o cliente por **avisos** (`/administracao/avisos`), que aparecem no topo de toda tela da academia. O aviso pode ser marcado como **não dispensável** — é o caso do alerta de bloqueio, que não pode sumir com um clique e deixar o dono descobrir na segunda-feira.

O super administrador é a equipe do Pulso. Ele **não pertence a academia nenhuma**, e é isso — não um papel atribuído por tela — que define quem ele é. Como as políticas de Row Level Security comparam `academia_id` com o contexto da requisição, e o dele é vazio, **nenhuma linha de aluno, mensalidade ou biometria aparece para ele**. Não há exceção no banco.

O que ele alcança é o plano de controle: `academias`, `unidades`, `avisos_academia` e `users` — esta última porque a autenticação precisa acontecer antes de existir "academia atual".

**O custo disso é o suporte:** não dá para ver a tela do cliente. A troca vale porque uma conta da equipe do Pulso comprometida não abre a base de nenhum cliente. Se o suporte se mostrar inviável, a saída é impersonação auditada — não abrir o RLS.

## Catraca e leitor biométrico

Equipamento adotado: **ZKTeco SenseFace 2A** (protocolo PUSH/ADMS), acionando uma catraca genérica por contato seco.

**O aparelho é o cliente, não o servidor.** Ele faz polling em `/iclock/*` a cada poucos segundos; o Pulso nunca abre conexão com ele. Cadastrar um aluno no leitor é enfileirar um comando e esperar a próxima pergunta.

Como a catraca não informa a direção do giro, **entrada e saída são deduzidas** pela alternância com a passagem anterior, com tolerância de 4 horas para presumir que o aluno saiu sem registrar. Saída presumida fica marcada como tal e não gera tempo de permanência.

Sem o equipamento em mãos, `/acesso/simulador` (fora do ar em produção) monta a mesma linha do protocolo e a entrega nos mesmos endpoints, passando pelo mesmo middleware.

Detalhes e decisões em [`docs/dominio/README.md` §5.4](docs/dominio/README.md). A especificação do protocolo, com tráfego real capturado, está em [`docs/zkteco/`](docs/zkteco/) — **é a fonte da verdade do formato**.

## Qualidade

```bash
composer estilo         # Pint: formata
composer estilo:conferir # Pint: só confere
composer analise        # PHPStan (Larastan) nível 6
composer test           # PHPUnit
composer qualidade      # os três acima
```

Os testes rodam sobre **PostgreSQL**, não SQLite: RLS, `timestamptz`, índices parciais e `EXCLUDE` não existem no SQLite, e um verde obtido lá não valeria nada.

## Convenções

- **Idioma**: interface, mensagens de erro, e-mails, comentários e commits em português do Brasil.
- **Fuso**: `America/Fortaleza`. Grave `timestamptz` (UTC no banco) e converta na aplicação. **Vencimento de mensalidade é `date`, não instante** — senão o dia vira por causa de fuso.
- **Cores**: componente nenhum usa hex literal — só os tokens de [`docs/marca/tokens.css`](docs/marca/tokens.css). Como o tema vive inteiro nos tokens, o projeto **não usa a variante `dark:`** do Tailwind.
- **Estado nunca é só cor**: ícone + texto + cor, sempre os três (`<x-ui.pilula>`).
- **Toque mínimo de 44 px**: a equipe usa o sistema em pé, no celular.
- **Números** (dinheiro, data, contagem) sempre com a classe `numeros` — `tabular-nums`, para alinhar em coluna.
- **Biometria é dado sensível** (LGPD, art. 11): consentimento separado do contrato, alternativa obrigatória por cartão ou PIN, exclusão ao cancelar a matrícula. Guardar template, nunca a imagem do rosto.
- **Não expor o aluno inadimplente** (CDC, art. 42): a catraca mostra "Procure a recepção", nunca o motivo na fila.
- **Sem auto-cadastro**: `Features::registration()` do Fortify fica desligado. Conta de academia é criada pela equipe do Pulso; usuário dentro da academia, pelo gestor dela.

Detalhamento da identidade em [`docs/marca/README.md`](docs/marca/README.md).

## Situação do projeto

| Etapa | Situação |
|---|---|
| 1. Fundação, design system, página inicial e acesso | ✅ concluída |
| 2a. Modelo de domínio e convenções de interface, em texto | ✅ concluída |
| 2b. Design system — 27 componentes e o catálogo em `/catalogo` | ✅ concluída |
| 2c. Banco: 26 migrations, 16 models e Row Level Security | ✅ concluída |
| 3a. CRUD de alunos (Livewire) — lista, ficha e formulário | ✅ concluída |
| 3b. CRUD de planos | ✅ concluída |
| 3c. Matrículas — criação, experiência, conversão, trancamento e encerramento | ✅ concluída |
| 4a. Mensalidades: geração automática, recebimento e estorno | ✅ concluída |
| 4b. Cobrança online (Pix) — provedor a definir | ⬜ |
| 5. Radar — inadimplência, baixa frequência, risco de evasão e aniversariantes | ✅ concluída |
| 6a. Catraca: protocolo do leitor, entrada/saída, tela e simulador | ✅ concluída |
| 6b. Biometria: cadastro facial e digital pelo Pulso | ⬜ |
| 7a. Área do super administrador — academias, bloqueio e cobrança | ✅ concluída |
| 7b. Cadastro da equipe pela própria academia | ✅ concluída |
| 7c. Configurações da academia e segurança do acesso | ✅ concluída |
| 7d. Avisos do Pulso, redefinição de senha e confirmações | ✅ concluída |
| 8. Notificações por WhatsApp | ⬜ próxima |
| 9. Deploy na VPS | ⬜ |
