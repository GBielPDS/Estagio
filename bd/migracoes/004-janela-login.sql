-- Aplicada pelo administrar.php com verificação das colunas antes de cada ALTER.
-- Contadores legados são preservados, mas não utilizados pela nova janela.
ALTER TABLE tentativa_login ADD COLUMN falhas TEXT NULL;
ALTER TABLE tentativa_login ADD COLUMN bloqueio_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE tentativa_login ADD COLUMN atividade_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0;
