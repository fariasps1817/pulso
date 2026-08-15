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
  Providers/           AppServiceProvider, FortifyServiceProvider
  Support/             Formatação pt-BR (dinheiro, telefone)
config/
  pulso.php            Nome, slogans e contatos — fonte única
docs/marca/            Guia de marca, tokens de design e assets
  README.md            Nome, slogans, logo, cores, temas, acessibilidade e LGPD
  tokens.css           Fonte da verdade das cores (temas claro e escuro)
  tokens.json          A mesma paleta em formato legível por ferramenta
  quadro-de-marca.html Quadro visual da identidade — abra no navegador
  assets/              Logos, ícones e estados da catraca em SVG
lang/pt_BR/            Mensagens de validação, autenticação e senha
resources/
  css/app.css          Ponte tokens -> Tailwind (@theme inline)
  js/tema.js           Troca de tema: claro / escuro / sistema
  views/
    components/        Componentes Blade
      layout/          base, publico, acesso
      marca/           logo
      ui/              botao, campo, pilula, cartao, alternador-tema
    site/inicio        Página inicial
    acesso/            Login, esqueci a senha, redefinir senha
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
php artisan serve
```

O site institucional e a tela de login sobem **sem banco de dados** — sessão e cache usam arquivo no ambiente local.

### Banco de dados

```bash
# como superusuário do Postgres
createdb -U postgres pulso
createdb -U postgres pulso_teste

# usuário da aplicação: sem BYPASSRLS, sem ser dono das tabelas
psql -U postgres -d pulso -c "CREATE ROLE pulso_app LOGIN PASSWORD 'troque-esta-senha';"
```

Depois preencha `DB_PASSWORD` e `DB_ADMIN_PASSWORD` no `.env` e rode `php artisan migrate`.

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
| 2. Multi-academia: tabelas, papéis e Row Level Security | ⬜ próxima |
| 3. Cadastros: alunos, planos, matrículas | ⬜ |
| 4. Financeiro: mensalidades, pagamentos, Pix | ⬜ |
| 5. Controle de acesso: catraca e biometria | ⬜ |
| 6. Radar | ⬜ |
| 7. Notificações por WhatsApp | ⬜ |
| 8. Deploy na VPS | ⬜ |
