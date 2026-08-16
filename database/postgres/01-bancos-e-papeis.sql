-- =============================================================
-- Pulso — bancos e papéis do PostgreSQL
-- -------------------------------------------------------------
-- Rode UMA VEZ, conectado como superusuário:
--
--     psql -U postgres -f database/postgres/01-bancos-e-papeis.sql
--
-- Depois, dentro de cada banco:
--
--     psql -U postgres -d pulso        -f database/postgres/02-privilegios.sql
--     psql -U postgres -d pulso_teste  -f database/postgres/02-privilegios.sql
--
-- -------------------------------------------------------------
-- POR QUE DOIS PAPÉIS
--
-- O isolamento entre academias se apoia em Row Level Security. O
-- PostgreSQL isenta das políticas de RLS:
--   1. superusuários;
--   2. papéis com BYPASSRLS;
--   3. o dono da tabela (a menos que se use FORCE ROW LEVEL SECURITY).
--
-- Se a aplicação conectasse como "postgres", o RLS seria decorativo.
-- Por isso a aplicação usa "pulso_app": sem SUPERUSER, sem BYPASSRLS,
-- sem CREATEDB e sem ser dona de nada. Migrations rodam por outra
-- conexão (pgsql_admin).
-- =============================================================

-- -------------------------------------------------------------
-- Papel da aplicação
-- -------------------------------------------------------------
-- NOSUPERUSER / NOBYPASSRLS / NOCREATEDB / NOCREATEROLE são
-- explícitos de propósito: é a linha que sustenta o isolamento.
-- Troque a senha antes de usar em produção.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pulso_app') THEN
        CREATE ROLE pulso_app
            LOGIN
            PASSWORD 'pulso_local'
            NOSUPERUSER
            NOBYPASSRLS
            NOCREATEDB
            NOCREATEROLE
            NOINHERIT;
    END IF;
END
$$;

-- -------------------------------------------------------------
-- Bancos
-- -------------------------------------------------------------
-- Collation pt-BR para que ORDER BY respeite acentuação: sem isso,
-- "Álvaro" cai depois de "Zuleica" numa lista de alunos.
--
-- CREATE DATABASE não roda dentro de bloco DO/transação, então os
-- comandos abaixo são diretos. Se o banco já existir, o psql avisa e
-- segue — é seguro rodar de novo.
CREATE DATABASE pulso
    ENCODING 'UTF8'
    LC_COLLATE 'pt-BR'
    LC_CTYPE 'pt-BR'
    TEMPLATE template0;

CREATE DATABASE pulso_teste
    ENCODING 'UTF8'
    LC_COLLATE 'pt-BR'
    LC_CTYPE 'pt-BR'
    TEMPLATE template0;
