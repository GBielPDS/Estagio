-- Executar no banco da aplicação. Não altera contas, senhas ou logs existentes.
CREATE TABLE IF NOT EXISTS tentativa_login (
    id_tentativa INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(255) NOT NULL,
    tipo ENUM('conta', 'ip') NOT NULL,
    tentativas INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultima_tentativa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_identificador_tipo (identificador, tipo)
) ENGINE=InnoDB;