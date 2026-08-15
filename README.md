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

| Camada | Tecnologia |
|---|---|
| Aplicação | PHP · Laravel |
| Banco | PostgreSQL |
| Interface | Web responsiva, mobile-first (a equipe monitora pelo celular) |
| Ambiente local | Laragon — `C:\laragon\www\pulso` |

## Estrutura

```
docs/marca/           Guia de marca, tokens de design e assets
  README.md           Nome, slogans, logo, cores, temas, acessibilidade e LGPD
  tokens.css          Fonte da verdade das cores (temas claro e escuro)
  tokens.json         A mesma paleta em formato legível por ferramenta
  quadro-de-marca.html  Quadro visual da identidade — abra no navegador
  assets/             Logos, ícones e estados da catraca em SVG
```

## Começando

O repositório ainda não tem a aplicação — só as bases de marca. Para criar o projeto Laravel dentro dele:

```bash
# em C:\laragon\www\pulso
composer create-project laravel/laravel .
```

Configuração obrigatória do projeto:

```php
// config/app.php
'timezone'     => 'America/Fortaleza',   // UTC-3, sem horário de verão
'locale'       => 'pt_BR',
'faker_locale' => 'pt_BR',
```

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pulso
```

Grave `timestamptz` no PostgreSQL (UTC no banco) e converta na aplicação. **Vencimento de mensalidade é `date`, não instante** — senão o dia vira por causa de fuso.

## Convenções

- **Idioma**: interface, mensagens de erro, e-mails e commits em português do Brasil.
- **Cores**: componente nenhum usa hex literal — só os tokens de `docs/marca/tokens.css`.
- **Estado nunca é só cor**: ícone + texto + cor, sempre os três.
- **Biometria é dado sensível** (LGPD, art. 11): consentimento separado do contrato, alternativa obrigatória por cartão ou PIN, exclusão ao cancelar a matrícula. Guardar template, nunca a imagem do rosto.
- **Não expor o aluno inadimplente** (CDC, art. 42): a catraca mostra "Procure a recepção", nunca o motivo na fila.

Detalhamento em [`docs/marca/README.md`](docs/marca/README.md).
