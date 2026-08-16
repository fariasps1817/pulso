-- =============================================================
-- Pulso — privilégios de "pulso_app" dentro de um banco
-- -------------------------------------------------------------
-- Rode uma vez por banco, como superusuário:
--
--     psql -U postgres -d pulso        -f database/postgres/02-privilegios.sql
--     psql -U postgres -d pulso_teste  -f database/postgres/02-privilegios.sql
--
-- -------------------------------------------------------------
-- A ideia: "pulso_app" lê e escreve, mas não cria nem altera nada.
-- Quem cria tabela é a conexão de migrations (dona do schema), e é
-- justamente por não ser dona que a aplicação fica sujeita ao Row
-- Level Security.
-- =============================================================

-- Conectar ao banco e enxergar o schema. O nome do banco não pode ser
-- uma expressão no GRANT, daí o EXECUTE — assim o script serve tanto
-- para "pulso" quanto para "pulso_teste" sem edição.
DO $$
BEGIN
    EXECUTE format('GRANT CONNECT ON DATABASE %I TO pulso_app', current_database());
END
$$;

GRANT USAGE ON SCHEMA public TO pulso_app;

-- Ninguém além do dono cria objetos em "public". Sem esta linha, o
-- PostgreSQL 15+ ainda concede CREATE ao dono do banco, e versões
-- anteriores concediam a todos.
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

-- Tabelas e sequências que já existem.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO pulso_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO pulso_app;

-- E as que as próximas migrations criarem. Sem este bloco, toda
-- migration nova exigiria um GRANT manual — e a primeira vez que
-- alguém esquecesse, a aplicação quebraria em produção.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO pulso_app;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO pulso_app;

-- Extensões usadas pelo sistema:
--   pg_trgm    busca de aluno por nome com erro de digitação
--   btree_gist constraint EXCLUDE contra matrículas sobrepostas
--   unaccent   busca ignorando acento ("jose" acha "José")
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS unaccent;
