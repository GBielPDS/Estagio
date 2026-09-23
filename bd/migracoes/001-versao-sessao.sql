-- Execute uma vez em instalações existentes.
ALTER TABLE usuario ADD COLUMN versao_sessao INT UNSIGNED NOT NULL DEFAULT 1;
