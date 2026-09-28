-- O executor verifica a coluna antes de adicionar. Registros antigos permanecem NULL.
ALTER TABLE movimentacao ADD COLUMN cadastrado_em DATETIME NULL DEFAULT NULL;
-- Somente novos INSERTs recebem o horário do cadastro automaticamente.
ALTER TABLE movimentacao ALTER COLUMN cadastrado_em SET DEFAULT CURRENT_TIMESTAMP;
