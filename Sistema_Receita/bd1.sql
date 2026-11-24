CREATE DATABASE IF NOT EXISTS livro_receitas;
USE livro_receitas;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    senha VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS chefs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    genero VARCHAR(50),
    pais VARCHAR(50),
    imagem VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chefs_id INT,
    nome VARCHAR(100) NOT NULL,
    pais VARCHAR(100),
    categoria VARCHAR(50),
    youtube_link VARCHAR(255),
    receita TEXT,
    imagem_capa VARCHAR(255),
    FOREIGN KEY (chefs_id) REFERENCES chefs(id)
);

INSERT INTO usuarios (usuario, senha) VALUES ('admin', '$2y$12$5A005Tn5nKkuhtFb6ktAQuDiS8P4JRHyiB75GJPkQ2JMweXe8T/se');