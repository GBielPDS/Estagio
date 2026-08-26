CREATE DATABASE almoxarifado;
USE almoxarifado;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('Administrador', 'Suporte', 'Usuario') NOT NULL
);

CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE produto (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    unidade VARCHAR(20) NOT NULL,
    estoque INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0,
    categoria_id INT NOT NULL,

    CONSTRAINT chk_estoque
        CHECK (estoque >= 0),

    CONSTRAINT chk_estoque_minimo
        CHECK (estoque_minimo >= 0),

    CONSTRAINT fk_produto_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categoria(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE unidade_saude (
    id_unidade INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    endereco VARCHAR(255),
    telefone VARCHAR(20),
    ativo BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE movimentacao (
    id_movimentacao INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Entrada', 'Saida') NOT NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unidade_destino_id INT,
    observacao TEXT,
    usuario_id INT NOT NULL,

    CONSTRAINT fk_movimentacao_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuario(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimentacao_unidade
        FOREIGN KEY (unidade_destino_id)
        REFERENCES unidade_saude(id_unidade)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE item_lancamento (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    movimentacao_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,

    CONSTRAINT chk_item_quantidade
        CHECK (quantidade > 0),

    CONSTRAINT fk_item_lancamento
        FOREIGN KEY (movimentacao_id)
        REFERENCES movimentacao(id_movimentacao)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_item_produto
        FOREIGN KEY (produto_id)
        REFERENCES produto(id_produto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE log (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    usuario_id INT NOT NULL,

    CONSTRAINT fk_log_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuario(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);