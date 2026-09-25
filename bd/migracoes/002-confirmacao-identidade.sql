-- Executar no banco da aplicação. Não altera contas, senhas ou logs existentes.
CREATE TABLE IF NOT EXISTS confirmacao_identidade (
    usuario_id INT NOT NULL PRIMARY KEY,
    falhas TEXT NOT NULL,
    bloqueado_ate BIGINT UNSIGNED NOT NULL DEFAULT 0,
    revisao INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_confirmacao_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
