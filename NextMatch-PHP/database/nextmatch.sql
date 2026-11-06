-- =====================================================
-- NextMatch — Banco de dados (MySQL / MariaDB)
-- Baseado em database/modelo-banco.md
--
-- Como importar (XAMPP):
--   1. Ligue Apache e MySQL no painel do XAMPP
--   2. Abra http://localhost/phpmyadmin
--   3. Aba "Importar" > escolha este arquivo > "Importar"
--
-- Em hospedagem que já cria o banco para você, apague as duas
-- linhas CREATE DATABASE / USE abaixo, selecione o banco no
-- phpMyAdmin e importe.
--
-- Contas de demonstração (senha de todas: nextmatch123)
--   organizador@nextmatch.com  (Organizador)
--   professor@nextmatch.com    (Professor)
--   atleta@nextmatch.com       (Atleta)
-- =====================================================

CREATE DATABASE IF NOT EXISTS `nextmatch` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nextmatch`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `vw_desempenho`;
DROP TABLE IF EXISTS `contatos`, `notificacoes`, `noticias`, `resultados`, `partidas`,
  `inscricoes`, `categorias`, `campeonatos`, `equipe_atletas`, `equipes`, `modalidades`, `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- Usuários (atletas, professores e organizadores)
-- O perfil de cada conta é definido pela coluna `tipo`.
-- A senha é guardada criptografada (password_hash / bcrypt).
-- -----------------------------------------------------
CREATE TABLE `usuarios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(160) NOT NULL,
  `senha`      VARCHAR(255) NOT NULL,
  `tipo`       ENUM('atleta','professor','organizador') NOT NULL,
  `foto`       VARCHAR(255) DEFAULT NULL,
  `telefone`   VARCHAR(30)  DEFAULT NULL,
  `cidade`     VARCHAR(100) DEFAULT NULL,
  `posicao`    VARCHAR(60)  DEFAULT NULL COMMENT 'Posição/função do atleta',
  `criado_em`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Modalidades esportivas
-- -----------------------------------------------------
CREATE TABLE `modalidades` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`   VARCHAR(60) NOT NULL,
  `icone`  VARCHAR(30) NOT NULL DEFAULT 'futebol',
  `tipo_padrao` ENUM('equipe','individual') NOT NULL DEFAULT 'equipe',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modalidades_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Equipes (cada equipe tem um professor/treinador responsável)
-- -----------------------------------------------------
CREATE TABLE `equipes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(100) NOT NULL,
  `modalidade_id` INT UNSIGNED NOT NULL,
  `professor_id`  INT UNSIGNED NOT NULL,
  `cidade`        VARCHAR(100) DEFAULT NULL,
  `logo`          VARCHAR(255) DEFAULT NULL,
  `criado_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipes_modalidade` (`modalidade_id`),
  KEY `idx_equipes_professor` (`professor_id`),
  CONSTRAINT `fk_equipes_modalidade` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`),
  CONSTRAINT `fk_equipes_professor`  FOREIGN KEY (`professor_id`)  REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Atletas vinculados às equipes (e solicitações de entrada)
-- -----------------------------------------------------
CREATE TABLE `equipe_atletas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipe_id`  INT UNSIGNED NOT NULL,
  `atleta_id`  INT UNSIGNED NOT NULL,
  `status`     ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
  `criado_em`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_equipe_atleta` (`equipe_id`, `atleta_id`),
  KEY `idx_ea_atleta` (`atleta_id`),
  CONSTRAINT `fk_ea_equipe` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_atleta` FOREIGN KEY (`atleta_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Campeonatos
--   tipo_participante: quem se inscreve (equipes ou atletas)
--   formato: mata_mata | pontos_corridos | classificacao (provas
--            com tempo/marca, ex.: natação, atletismo)
-- -----------------------------------------------------
CREATE TABLE `campeonatos` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`              VARCHAR(150) NOT NULL,
  `modalidade_id`     INT UNSIGNED NOT NULL,
  `organizador_id`    INT UNSIGNED NOT NULL,
  `tipo_participante` ENUM('equipe','individual') NOT NULL DEFAULT 'equipe',
  `formato`           ENUM('mata_mata','pontos_corridos','classificacao') NOT NULL DEFAULT 'mata_mata',
  `status`            ENUM('em_breve','inscricoes_abertas','em_andamento','encerrado') NOT NULL DEFAULT 'em_breve',
  `local`             VARCHAR(150) DEFAULT NULL,
  `cidade`            VARCHAR(100) DEFAULT NULL,
  `data_inicio`       DATE NOT NULL,
  `data_fim`          DATE DEFAULT NULL,
  `vagas`             INT UNSIGNED NOT NULL DEFAULT 16,
  `premiacao`         VARCHAR(255) DEFAULT NULL,
  `regulamento`       TEXT,
  `imagem`            VARCHAR(255) DEFAULT NULL,
  `criado_em`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_camp_modalidade` (`modalidade_id`),
  KEY `idx_camp_organizador` (`organizador_id`),
  KEY `idx_camp_status` (`status`),
  CONSTRAINT `fk_camp_modalidade`  FOREIGN KEY (`modalidade_id`)  REFERENCES `modalidades` (`id`),
  CONSTRAINT `fk_camp_organizador` FOREIGN KEY (`organizador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Categorias de cada campeonato (ex.: Sub-20 Masculino)
-- -----------------------------------------------------
CREATE TABLE `categorias` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campeonato_id` INT UNSIGNED NOT NULL,
  `nome`          VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cat_campeonato` (`campeonato_id`),
  CONSTRAINT `fk_cat_campeonato` FOREIGN KEY (`campeonato_id`) REFERENCES `campeonatos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Inscrições (de uma equipe OU de um atleta, numa categoria)
-- -----------------------------------------------------
CREATE TABLE `inscricoes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campeonato_id` INT UNSIGNED NOT NULL,
  `categoria_id`  INT UNSIGNED NOT NULL,
  `equipe_id`     INT UNSIGNED DEFAULT NULL,
  `atleta_id`     INT UNSIGNED DEFAULT NULL,
  `inscrito_por`  INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
  `criado_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_insc_equipe` (`categoria_id`, `equipe_id`),
  UNIQUE KEY `uk_insc_atleta` (`categoria_id`, `atleta_id`),
  KEY `idx_insc_campeonato` (`campeonato_id`),
  CONSTRAINT `fk_insc_campeonato` FOREIGN KEY (`campeonato_id`) REFERENCES `campeonatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_categoria`  FOREIGN KEY (`categoria_id`)  REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_equipe`     FOREIGN KEY (`equipe_id`)     REFERENCES `equipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_atleta`     FOREIGN KEY (`atleta_id`)     REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_por`        FOREIGN KEY (`inscrito_por`)  REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Partidas (geradas pelo chaveamento automático)
--   mata-mata: o vencedor da (rodada r, posição p) avança para
--   (rodada r+1, posição ceil(p/2)).
-- -----------------------------------------------------
CREATE TABLE `partidas` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campeonato_id`  INT UNSIGNED NOT NULL,
  `categoria_id`   INT UNSIGNED NOT NULL,
  `rodada`         INT UNSIGNED NOT NULL,
  `posicao`        INT UNSIGNED NOT NULL,
  `inscricao_a_id` INT UNSIGNED DEFAULT NULL,
  `inscricao_b_id` INT UNSIGNED DEFAULT NULL,
  `placar_a`       INT DEFAULT NULL,
  `placar_b`       INT DEFAULT NULL,
  `vencedor_id`    INT UNSIGNED DEFAULT NULL COMMENT 'inscrição vencedora (NULL = empate)',
  `data_hora`      DATETIME DEFAULT NULL,
  `local`          VARCHAR(150) DEFAULT NULL,
  `status`         ENUM('agendada','finalizada') NOT NULL DEFAULT 'agendada',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partida_posicao` (`categoria_id`, `rodada`, `posicao`),
  KEY `idx_partidas_camp` (`campeonato_id`),
  KEY `idx_partidas_data` (`data_hora`),
  CONSTRAINT `fk_part_campeonato` FOREIGN KEY (`campeonato_id`)  REFERENCES `campeonatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_part_categoria`  FOREIGN KEY (`categoria_id`)   REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_part_insc_a`     FOREIGN KEY (`inscricao_a_id`) REFERENCES `inscricoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_part_insc_b`     FOREIGN KEY (`inscricao_b_id`) REFERENCES `inscricoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_part_vencedor`   FOREIGN KEY (`vencedor_id`)    REFERENCES `inscricoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Resultados de provas (formato "classificacao")
-- -----------------------------------------------------
CREATE TABLE `resultados` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inscricao_id`  INT UNSIGNED NOT NULL,
  `colocacao`     INT UNSIGNED NOT NULL,
  `marca`         VARCHAR(40) DEFAULT NULL COMMENT 'tempo, distância ou pontuação',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resultado_inscricao` (`inscricao_id`),
  CONSTRAINT `fk_res_inscricao` FOREIGN KEY (`inscricao_id`) REFERENCES `inscricoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Notícias
-- -----------------------------------------------------
CREATE TABLE `noticias` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`       VARCHAR(180) NOT NULL,
  `resumo`       VARCHAR(300) NOT NULL,
  `conteudo`     TEXT NOT NULL,
  `categoria`    VARCHAR(60) NOT NULL DEFAULT 'Plataforma',
  `imagem`       VARCHAR(255) DEFAULT NULL,
  `autor_id`     INT UNSIGNED DEFAULT NULL,
  `publicado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_noticias_data` (`publicado_em`),
  CONSTRAINT `fk_noticias_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Notificações
-- -----------------------------------------------------
CREATE TABLE `notificacoes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `titulo`      VARCHAR(150) NOT NULL,
  `mensagem`    VARCHAR(300) NOT NULL,
  `link`        VARCHAR(255) DEFAULT NULL,
  `lida`        TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_usuario` (`usuario_id`, `lida`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Mensagens do formulário de contato
-- -----------------------------------------------------
CREATE TABLE `contatos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(160) NOT NULL,
  `assunto`    VARCHAR(80)  NOT NULL,
  `mensagem`   TEXT NOT NULL,
  `criado_em`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- View usada no ranking: uma linha por participante por
-- partida finalizada, com o resultado (V, E ou D)
-- -----------------------------------------------------
CREATE VIEW `vw_desempenho` AS
  SELECT p.id AS partida_id, c.modalidade_id, p.inscricao_a_id AS inscricao_id,
         p.placar_a AS pro, p.placar_b AS contra,
         CASE WHEN p.vencedor_id = p.inscricao_a_id THEN 'V'
              WHEN p.vencedor_id IS NULL THEN 'E' ELSE 'D' END AS resultado
    FROM partidas p JOIN campeonatos c ON c.id = p.campeonato_id
   WHERE p.status = 'finalizada' AND p.inscricao_a_id IS NOT NULL AND p.inscricao_b_id IS NOT NULL
  UNION ALL
  SELECT p.id, c.modalidade_id, p.inscricao_b_id,
         p.placar_b, p.placar_a,
         CASE WHEN p.vencedor_id = p.inscricao_b_id THEN 'V'
              WHEN p.vencedor_id IS NULL THEN 'E' ELSE 'D' END
    FROM partidas p JOIN campeonatos c ON c.id = p.campeonato_id
   WHERE p.status = 'finalizada' AND p.inscricao_a_id IS NOT NULL AND p.inscricao_b_id IS NOT NULL;

-- =====================================================
-- DADOS DE DEMONSTRAÇÃO
-- =====================================================

INSERT INTO `modalidades` (`id`, `nome`, `icone`, `tipo_padrao`) VALUES
  (1, 'Futebol', 'futebol', 'equipe'),
  (2, 'Vôlei', 'volei', 'equipe'),
  (3, 'Basquete', 'basquete', 'equipe'),
  (4, 'Tênis', 'tenis', 'individual'),
  (5, 'Atletismo', 'atletismo', 'individual'),
  (6, 'Natação', 'natacao', 'individual'),
  (7, 'Lutas', 'lutas', 'individual'),
  (8, 'Capoeira', 'capoeira', 'individual');

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`, `cidade`, `telefone`, `posicao`) VALUES
  (1, 'Rodrigo Nunes', 'organizador@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'organizador', 'São Paulo/SP', '(11) 98888-1001', NULL),
  (2, 'Ricardo Alves', 'professor@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'São Paulo/SP', '(11) 98888-1002', NULL),
  (3, 'Camila Rocha', 'camila.rocha@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Campinas/SP', NULL, NULL),
  (4, 'Eduardo Lima', 'eduardo.lima@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Jundiaí/SP', NULL, NULL),
  (5, 'Juliana Prado', 'juliana.prado@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Sorocaba/SP', NULL, NULL),
  (6, 'André Costa', 'andre.costa@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Itu/SP', NULL, NULL),
  (7, 'Fernanda Lopes', 'fernanda.lopes@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Bragança Paulista/SP', NULL, NULL),
  (8, 'Bruno Castro', 'bruno.castro@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Itu/SP', NULL, NULL),
  (9, 'Marcelo Sousa', 'marcelo.sousa@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'professor', 'Bragança Paulista/SP', NULL, NULL),
  (10, 'Lucas Martins', 'atleta@nextmatch.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', 'São Paulo/SP', '(11) 98888-1010', 'Meio-campo'),
  (11, 'Rafael Souza', 'rafael.souza@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (12, 'Bianca Farias', 'bianca.farias@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ala'),
  (13, 'Diego Martins', 'diego.martins@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (14, 'Gabriel Santos', 'gabriel.santos@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (15, 'Mateus Oliveira', 'mateus.oliveira@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (16, 'João Pedro Lima', 'joao.pedro.lima@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (17, 'Larissa Mendes', 'larissa.mendes@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (18, 'Ana Clara Ribeiro', 'ana.clara.ribeiro@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (19, 'Beatriz Costa', 'beatriz.costa@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Líbero'),
  (20, 'Thiago Almeida', 'thiago.almeida@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ala'),
  (21, 'Pedro Henrique Dias', 'pedro.henrique.dias@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Pivô'),
  (22, 'Mariana Duarte', 'mariana.duarte@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Nadadora'),
  (23, 'Sofia Carvalho', 'sofia.carvalho@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Nadadora'),
  (24, 'Helena Barbosa', 'helena.barbosa@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Nadadora'),
  (25, 'Isabela Freitas', 'isabela.freitas@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Nadadora'),
  (26, 'Carlos Eduardo Pinto', 'carlos.eduardo.pinto@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Tenista'),
  (27, 'Felipe Araújo', 'felipe.araujo@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Tenista'),
  (28, 'Gustavo Rezende', 'gustavo.rezende@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Tenista'),
  (29, 'Vinícius Moraes', 'vinicius.moraes@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Corredor'),
  (30, 'Letícia Gomes', 'leticia.gomes@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Corredora'),
  (31, 'Júlia Teixeira', 'julia.teixeira@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Nadadora'),
  (32, 'Rafaela Antunes', 'rafaela.antunes@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Tenista'),
  (33, 'Enzo Ferreira', 'enzo.ferreira@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (34, 'Vitor Araújo', 'vitor.araujo34@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (35, 'Daniel Teixeira', 'daniel.teixeira35@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (36, 'Wesley Cardoso', 'wesley.cardoso36@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (37, 'Samuel Carvalho', 'samuel.carvalho37@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (38, 'Manuela Martins', 'manuela.martins38@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (39, 'Sabrina Silva', 'sabrina.silva39@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (40, 'Raquel Barros', 'raquel.barros40@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (41, 'Fernanda Teixeira', 'fernanda.teixeira41@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (42, 'Giovana Cardoso', 'giovana.cardoso42@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (43, 'Tainá Lima', 'taina.lima43@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (44, 'Carolina Pinto', 'carolina.pinto44@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (45, 'Nicolas Moreira', 'nicolas.moreira45@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (46, 'Guilherme Nascimento', 'guilherme.nascimento46@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (47, 'Nicolas Freitas', 'nicolas.freitas47@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (48, 'Paulo Correia', 'paulo.correia48@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (49, 'Tiago Almeida', 'tiago.almeida49@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (50, 'Igor Ribeiro', 'igor.ribeiro50@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (51, 'Caio Almeida', 'caio.almeida51@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (52, 'Samuel Cardoso', 'samuel.cardoso52@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (53, 'Guilherme Lima', 'guilherme.lima53@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (54, 'Caio Lima', 'caio.lima54@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (55, 'Tiago Teixeira', 'tiago.teixeira55@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (56, 'Otávio Nascimento', 'otavio.nascimento56@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (57, 'Wesley Almeida', 'wesley.almeida57@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (58, 'Fábio Cardoso', 'fabio.cardoso58@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (59, 'Tiago Fernandes', 'tiago.fernandes59@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (60, 'Renan Rocha', 'renan.rocha60@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (61, 'Murilo Correia', 'murilo.correia61@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (62, 'Vitor Martins', 'vitor.martins62@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (63, 'Caio Cardoso', 'caio.cardoso63@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Meio-campo'),
  (64, 'Daniel Moreira', 'daniel.moreira64@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (65, 'Tiago Pinto', 'tiago.pinto65@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (66, 'Caio Araújo', 'caio.araujo66@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (67, 'Otávio Freitas', 'otavio.freitas67@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (68, 'Arthur Correia', 'arthur.correia68@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (69, 'Kaique Araújo', 'kaique.araujo69@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (70, 'Wesley Moreira', 'wesley.moreira70@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (71, 'Eduardo Teixeira', 'eduardo.teixeira71@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (72, 'Eduardo Rodrigues', 'eduardo.rodrigues72@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (73, 'Caio Correia', 'caio.correia73@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Lateral'),
  (74, 'Guilherme Pereira', 'guilherme.pereira74@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (75, 'Vitor Cardoso', 'vitor.cardoso75@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Zagueiro'),
  (76, 'Wesley Pinto', 'wesley.pinto76@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (77, 'Tiago Barros', 'tiago.barros77@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Atacante'),
  (78, 'Paulo Martins', 'paulo.martins78@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (79, 'Arthur Lima', 'arthur.lima79@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Goleiro'),
  (80, 'Samuel Freitas', 'samuel.freitas80@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (81, 'Otávio Fernandes', 'otavio.fernandes81@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Volante'),
  (82, 'Isadora Lima', 'isadora.lima82@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (83, 'Giovana Lima', 'giovana.lima83@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (84, 'Bruna Pinto', 'bruna.pinto84@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (85, 'Valentina Nascimento', 'valentina.nascimento85@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (86, 'Giovana Nascimento', 'giovana.nascimento86@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (87, 'Giovana Moreira', 'giovana.moreira87@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (88, 'Paula Nascimento', 'paula.nascimento88@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (89, 'Jéssica Ribeiro', 'jessica.ribeiro89@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (90, 'Sabrina Martins', 'sabrina.martins90@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Central'),
  (91, 'Valentina Silva', 'valentina.silva91@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (92, 'Alice Rodrigues', 'alice.rodrigues92@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (93, 'Giovana Silva', 'giovana.silva93@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Central'),
  (94, 'Alice Lima', 'alice.lima94@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (95, 'Isadora Cardoso', 'isadora.cardoso95@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (96, 'Bruna Fernandes', 'bruna.fernandes96@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Oposta'),
  (97, 'Alice Teixeira', 'alice.teixeira97@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (98, 'Fernanda Ribeiro', 'fernanda.ribeiro98@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (99, 'Tainá Freitas', 'taina.freitas99@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Oposta'),
  (100, 'Fernanda Gomes', 'fernanda.gomes100@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (101, 'Alice Martins', 'alice.martins101@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ponteira'),
  (102, 'Natália Araújo', 'natalia.araujo102@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Oposta'),
  (103, 'Natália Carvalho', 'natalia.carvalho103@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Levantadora'),
  (104, 'Daniel Correia', 'daniel.correia104@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (105, 'Renan Correia', 'renan.correia105@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Pivô'),
  (106, 'Bernardo Martins', 'bernardo.martins106@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (107, 'Nicolas Almeida', 'nicolas.almeida107@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ala'),
  (108, 'Wesley Gomes', 'wesley.gomes108@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (109, 'Guilherme Almeida', 'guilherme.almeida109@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ala'),
  (110, 'Samuel Gomes', 'samuel.gomes110@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Ala'),
  (111, 'Otávio Cardoso', 'otavio.cardoso111@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (112, 'Guilherme Pinto', 'guilherme.pinto112@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Pivô'),
  (113, 'Fábio Freitas', 'fabio.freitas113@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (114, 'Samuel Lima', 'samuel.lima114@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Pivô'),
  (115, 'Eduardo Lima', 'eduardo.lima115@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (116, 'Daniel Martins', 'daniel.martins116@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Pivô'),
  (117, 'Eduardo Rocha', 'eduardo.rocha117@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador'),
  (118, 'Kaique Martins', 'kaique.martins118@exemplo.com', '$2y$10$JYboIqIoe2q8pC5c9Vzfz.lDoDIKuJI8xNkkFZdZdFcaRIVb7hPvC', 'atleta', NULL, NULL, 'Armador');

INSERT INTO `equipes` (`id`, `nome`, `modalidade_id`, `professor_id`, `cidade`) VALUES
  (1, 'Falcões FC', 1, 2, 'São Paulo/SP'),
  (2, 'Panteras Futsal', 1, 5, 'Sorocaba/SP'),
  (3, 'União Atlética', 1, 6, 'Itu/SP'),
  (4, 'Real Sorocaba', 1, 6, 'Sorocaba/SP'),
  (5, 'Estrela Azul FC', 1, 6, 'Campinas/SP'),
  (6, 'Vila Nova FC', 1, 7, 'Bragança Paulista/SP'),
  (7, 'Rangers SP', 1, 5, 'São Paulo/SP'),
  (8, 'Guarani Jr.', 1, 7, 'Campinas/SP'),
  (9, 'Vôlei Fênix', 2, 3, 'Campinas/SP'),
  (10, 'Águias do Saque', 2, 6, 'Itu/SP'),
  (11, 'Ases da Rede', 2, 7, 'Bragança Paulista/SP'),
  (12, 'Vôlei Vila Nova', 2, 7, 'Bragança Paulista/SP'),
  (13, 'Tigres Basquete', 3, 4, 'Jundiaí/SP'),
  (14, 'Furacão 3x3', 3, 4, 'Jundiaí/SP'),
  (15, 'Rinos BBall', 3, 5, 'Sorocaba/SP'),
  (16, 'Falcões Basquete', 3, 2, 'São Paulo/SP'),
  (17, 'Golfinhos Natação', 6, 8, 'Itu/SP'),
  (18, 'Águias do Tênis', 4, 9, 'Bragança Paulista/SP');

INSERT INTO `equipe_atletas` (`equipe_id`, `atleta_id`, `status`) VALUES
  (1, 10, 'aprovado'),
  (1, 13, 'aprovado'),
  (1, 14, 'aprovado'),
  (1, 15, 'aprovado'),
  (1, 16, 'aprovado'),
  (1, 11, 'pendente'),
  (2, 12, 'pendente'),
  (9, 17, 'aprovado'),
  (9, 18, 'aprovado'),
  (9, 19, 'aprovado'),
  (13, 20, 'aprovado'),
  (13, 21, 'aprovado'),
  (16, 33, 'pendente'),
  (17, 22, 'aprovado'),
  (17, 23, 'aprovado'),
  (17, 24, 'aprovado'),
  (17, 25, 'aprovado'),
  (17, 31, 'aprovado'),
  (18, 26, 'aprovado'),
  (18, 27, 'aprovado'),
  (18, 28, 'aprovado'),
  (18, 32, 'aprovado'),
  (1, 34, 'aprovado'),
  (1, 35, 'aprovado'),
  (1, 36, 'aprovado'),
  (1, 37, 'aprovado'),
  (2, 38, 'aprovado'),
  (2, 39, 'aprovado'),
  (2, 40, 'aprovado'),
  (2, 41, 'aprovado'),
  (2, 42, 'aprovado'),
  (2, 43, 'aprovado'),
  (2, 44, 'aprovado'),
  (3, 45, 'aprovado'),
  (3, 46, 'aprovado'),
  (3, 47, 'aprovado'),
  (3, 48, 'aprovado'),
  (3, 49, 'aprovado'),
  (3, 50, 'aprovado'),
  (3, 51, 'aprovado'),
  (4, 52, 'aprovado'),
  (4, 53, 'aprovado'),
  (4, 54, 'aprovado'),
  (4, 55, 'aprovado'),
  (4, 56, 'aprovado'),
  (4, 57, 'aprovado'),
  (5, 58, 'aprovado'),
  (5, 59, 'aprovado'),
  (5, 60, 'aprovado'),
  (5, 61, 'aprovado'),
  (5, 62, 'aprovado'),
  (5, 63, 'aprovado'),
  (6, 64, 'aprovado'),
  (6, 65, 'aprovado'),
  (6, 66, 'aprovado'),
  (6, 67, 'aprovado'),
  (6, 68, 'aprovado'),
  (6, 69, 'aprovado'),
  (7, 70, 'aprovado'),
  (7, 71, 'aprovado'),
  (7, 72, 'aprovado'),
  (7, 73, 'aprovado'),
  (7, 74, 'aprovado'),
  (7, 75, 'aprovado'),
  (8, 76, 'aprovado'),
  (8, 77, 'aprovado'),
  (8, 78, 'aprovado'),
  (8, 79, 'aprovado'),
  (8, 80, 'aprovado'),
  (8, 81, 'aprovado'),
  (9, 82, 'aprovado'),
  (9, 83, 'aprovado'),
  (9, 84, 'aprovado'),
  (9, 85, 'aprovado'),
  (10, 86, 'aprovado'),
  (10, 87, 'aprovado'),
  (10, 88, 'aprovado'),
  (10, 89, 'aprovado'),
  (10, 90, 'aprovado'),
  (10, 91, 'aprovado'),
  (11, 92, 'aprovado'),
  (11, 93, 'aprovado'),
  (11, 94, 'aprovado'),
  (11, 95, 'aprovado'),
  (11, 96, 'aprovado'),
  (11, 97, 'aprovado'),
  (12, 98, 'aprovado'),
  (12, 99, 'aprovado'),
  (12, 100, 'aprovado'),
  (12, 101, 'aprovado'),
  (12, 102, 'aprovado'),
  (12, 103, 'aprovado'),
  (13, 104, 'aprovado'),
  (13, 105, 'aprovado'),
  (13, 106, 'aprovado'),
  (14, 107, 'aprovado'),
  (14, 108, 'aprovado'),
  (14, 109, 'aprovado'),
  (14, 110, 'aprovado'),
  (15, 111, 'aprovado'),
  (15, 112, 'aprovado'),
  (15, 113, 'aprovado'),
  (15, 114, 'aprovado'),
  (16, 115, 'aprovado'),
  (16, 116, 'aprovado'),
  (16, 117, 'aprovado'),
  (16, 118, 'aprovado');

INSERT INTO `campeonatos` (`id`, `nome`, `modalidade_id`, `organizador_id`, `tipo_participante`, `formato`, `status`, `local`, `cidade`, `data_inicio`, `data_fim`, `vagas`, `premiacao`, `regulamento`) VALUES
  (1, 'Copa NextMatch de Futebol', 1, 1, 'equipe', 'mata_mata', 'em_andamento', 'Arena Central', 'São Paulo/SP', '2026-10-24', '2026-11-14', 8, 'R$ 5.000 + troféu', 'Mata-mata em jogo único, com chaveamento sorteado entre as 8 equipes inscritas.
Em caso de empate no tempo normal, a vaga é decidida nos pênaltis.
Cada equipe pode inscrever até 22 atletas. Cartões e suspensões seguem as regras da CBF adaptadas para a categoria.'),
  (2, 'Liga Municipal de Vôlei', 2, 1, 'equipe', 'pontos_corridos', 'em_andamento', 'Ginásio Poliesportivo', 'Campinas/SP', '2026-10-17', '2026-11-07', 8, 'Medalhas + troféu', 'Pontos corridos em turno único: todas as equipes se enfrentam uma vez.
Partidas em melhor de 5 sets, com sets até 25 pontos (15 no set decisivo).
Vitória vale 3 pontos. Desempate: número de vitórias e saldo de sets.'),
  (3, 'Torneio de Basquete 3x3', 3, 1, 'equipe', 'mata_mata', 'inscricoes_abertas', 'Quadra do Parque', 'Jundiaí/SP', '2026-11-28', NULL, 8, 'Kits esportivos + troféu', 'Partidas de 10 minutos corridos ou até 21 pontos, o que acontecer primeiro.
Chaveamento eliminatório simples, sorteado após o fim das inscrições.
Cada equipe inscreve 3 atletas titulares e 1 reserva.'),
  (4, 'Circuito de Tênis Amador', 4, 1, 'individual', 'mata_mata', 'inscricoes_abertas', 'Clube Vila Verde', 'Bragança Paulista/SP', '2026-12-05', '2026-12-06', 16, 'Troféu + pontos no ranking', 'Partidas em melhor de 3 sets, com tie-break no terceiro.
Chaveamento eliminatório por categoria, sorteado ao fim das inscrições.
Traje esportivo obrigatório; raquetes e bolas por conta dos atletas.'),
  (5, 'Meia Maratona NextMatch', 5, 1, 'individual', 'classificacao', 'inscricoes_abertas', 'Orla do Rio Sorocaba', 'Sorocaba/SP', '2026-11-22', NULL, 500, 'Medalha finisher + troféus para o top 3', 'Percurso de 21 km cronometrado por chip.
Largada às 6h30; tempo limite de 3h30.
Classificação geral por categoria pelo tempo líquido.'),
  (6, 'Campeonato de Natação Sub-18', 6, 1, 'individual', 'classificacao', 'encerrado', 'Centro Aquático', 'Itu/SP', '2026-06-18', '2026-06-18', 40, 'Medalhas por prova', 'Provas de 50m nos quatro estilos, seguindo as regras da CBDA.
Classificação pelo tempo final de cada atleta.'),
  (7, 'Copa Regional de Futebol', 1, 1, 'equipe', 'mata_mata', 'encerrado', 'Estádio Municipal', 'Sorocaba/SP', '2026-08-08', '2026-08-15', 4, 'Troféu + medalhas', 'Semifinais e final em jogo único. Empates decididos nos pênaltis.'),
  (8, 'Copa NextMatch de Jiu-Jitsu', 7, 1, 'individual', 'mata_mata', 'em_breve', 'Centro Esportivo Municipal', 'Campinas/SP', '2026-12-12', NULL, 32, 'Medalhas + certificado', 'Lutas de 5 minutos, regras da CBJJ. Pesagem no dia, uma hora antes da primeira luta da categoria.'),
  (9, 'Festival de Capoeira NextMatch', 8, 1, 'individual', 'classificacao', 'em_breve', 'Praça de Eventos', 'Jundiaí/SP', '2027-01-16', NULL, 60, 'Troféus e certificados', 'Apresentações individuais avaliadas por banca (jogo, musicalidade e tradição). Notas de 0 a 10.');

INSERT INTO `categorias` (`id`, `campeonato_id`, `nome`) VALUES
  (1, 1, 'Sub-20 Masculino'),
  (2, 2, 'Adulto Feminino'),
  (3, 3, 'Livre'),
  (4, 4, 'Masculino'),
  (5, 4, 'Feminino'),
  (6, 5, 'Geral Masculino'),
  (7, 5, 'Geral Feminino'),
  (8, 6, '50m Livre Feminino'),
  (9, 7, 'Adulto Masculino'),
  (10, 8, 'Faixa Branca Adulto'),
  (11, 8, 'Faixa Azul Adulto'),
  (12, 9, 'Infantojuvenil'),
  (13, 9, 'Adulto');

INSERT INTO `inscricoes` (`id`, `campeonato_id`, `categoria_id`, `equipe_id`, `atleta_id`, `inscrito_por`, `status`, `criado_em`) VALUES
  (1, 1, 1, 1, NULL, 2, 'aprovada', '2026-10-05 09:01:00'),
  (2, 1, 1, 2, NULL, 5, 'aprovada', '2026-10-05 09:02:00'),
  (3, 1, 1, 3, NULL, 6, 'aprovada', '2026-10-05 09:03:00'),
  (4, 1, 1, 4, NULL, 6, 'aprovada', '2026-10-05 09:04:00'),
  (5, 1, 1, 5, NULL, 6, 'aprovada', '2026-10-05 09:05:00'),
  (6, 1, 1, 6, NULL, 7, 'aprovada', '2026-10-05 09:06:00'),
  (7, 1, 1, 7, NULL, 5, 'aprovada', '2026-10-05 09:07:00'),
  (8, 1, 1, 8, NULL, 7, 'aprovada', '2026-10-05 09:08:00'),
  (9, 2, 2, 9, NULL, 3, 'aprovada', '2026-10-02 14:00:00'),
  (10, 2, 2, 10, NULL, 6, 'aprovada', '2026-10-02 14:00:00'),
  (11, 2, 2, 11, NULL, 7, 'aprovada', '2026-10-02 14:00:00'),
  (12, 2, 2, 12, NULL, 7, 'aprovada', '2026-10-02 14:00:00'),
  (13, 3, 3, 13, NULL, 4, 'aprovada', '2026-10-20 10:00:00'),
  (14, 3, 3, 14, NULL, 4, 'aprovada', '2026-10-21 11:00:00'),
  (15, 3, 3, 15, NULL, 5, 'pendente', '2026-10-30 18:20:00'),
  (16, 3, 3, 16, NULL, 2, 'pendente', '2026-11-01 20:05:00'),
  (17, 4, 4, NULL, 10, 10, 'aprovada', '2026-10-22 08:00:00'),
  (18, 4, 4, NULL, 26, 26, 'aprovada', '2026-10-01 10:00:00'),
  (19, 4, 4, NULL, 27, 27, 'aprovada', '2026-10-01 10:00:00'),
  (20, 4, 4, NULL, 28, 28, 'pendente', '2026-11-01 09:30:00'),
  (21, 4, 5, NULL, 30, 30, 'aprovada', '2026-10-01 10:00:00'),
  (22, 4, 5, NULL, 32, 32, 'aprovada', '2026-10-01 10:00:00'),
  (23, 5, 6, NULL, 10, 10, 'pendente', '2026-11-02 08:10:00'),
  (24, 5, 6, NULL, 29, 29, 'aprovada', '2026-10-01 10:00:00'),
  (25, 5, 7, NULL, 30, 30, 'aprovada', '2026-10-01 10:00:00'),
  (26, 6, 8, NULL, 22, 22, 'aprovada', '2026-05-20 10:00:00'),
  (27, 6, 8, NULL, 23, 23, 'aprovada', '2026-05-20 10:00:00'),
  (28, 6, 8, NULL, 24, 24, 'aprovada', '2026-05-20 10:00:00'),
  (29, 6, 8, NULL, 25, 25, 'aprovada', '2026-05-20 10:00:00'),
  (30, 6, 8, NULL, 31, 31, 'aprovada', '2026-05-20 10:00:00'),
  (31, 7, 9, 1, NULL, 2, 'aprovada', '2026-07-20 10:00:00'),
  (32, 7, 9, 2, NULL, 5, 'aprovada', '2026-07-20 10:00:00'),
  (33, 7, 9, 3, NULL, 6, 'aprovada', '2026-07-20 10:00:00'),
  (34, 7, 9, 4, NULL, 6, 'aprovada', '2026-07-20 10:00:00');

INSERT INTO `partidas` (`campeonato_id`, `categoria_id`, `rodada`, `posicao`, `inscricao_a_id`, `inscricao_b_id`, `placar_a`, `placar_b`, `vencedor_id`, `data_hora`, `local`, `status`) VALUES
  (1, 1, 1, 1, 1, 4, 3, 1, 1, '2026-10-24 14:00:00', 'Arena Central — Campo 1', 'finalizada'),
  (1, 1, 1, 2, 5, 2, 0, 2, 2, '2026-10-24 16:00:00', 'Arena Central — Campo 1', 'finalizada'),
  (1, 1, 1, 3, 3, 6, 2, 2, 3, '2026-10-25 14:00:00', 'Arena Central — Campo 2', 'finalizada'),
  (1, 1, 1, 4, 7, 8, 1, 3, 8, '2026-10-25 16:00:00', 'Arena Central — Campo 2', 'finalizada'),
  (1, 1, 2, 1, 1, 2, 2, 1, 1, '2026-10-31 16:00:00', 'Arena Central — Campo 1', 'finalizada'),
  (1, 1, 2, 2, 3, 8, NULL, NULL, NULL, '2026-11-07 15:00:00', 'Arena Central — Campo 1', 'agendada'),
  (1, 1, 3, 1, 1, NULL, NULL, NULL, NULL, '2026-11-14 16:00:00', 'Arena Central — Campo 1', 'agendada'),
  (2, 2, 1, 1, 9, 12, 3, 1, 9, '2026-10-17 19:00:00', 'Ginásio Poliesportivo', 'finalizada'),
  (2, 2, 1, 2, 10, 11, 3, 2, 10, '2026-10-17 20:30:00', 'Ginásio Poliesportivo', 'finalizada'),
  (2, 2, 2, 1, 9, 11, 3, 0, 9, '2026-10-24 19:00:00', 'Ginásio Poliesportivo', 'finalizada'),
  (2, 2, 2, 2, 12, 10, 1, 3, 10, '2026-10-24 20:30:00', 'Ginásio Poliesportivo', 'finalizada'),
  (2, 2, 3, 1, 9, 10, NULL, NULL, NULL, '2026-11-07 19:00:00', 'Ginásio Poliesportivo', 'agendada'),
  (2, 2, 3, 2, 11, 12, NULL, NULL, NULL, '2026-11-07 20:30:00', 'Ginásio Poliesportivo', 'agendada'),
  (7, 9, 1, 1, 31, 34, 2, 0, 31, '2026-08-08 15:00:00', 'Estádio Municipal', 'finalizada'),
  (7, 9, 1, 2, 33, 32, 1, 1, 32, '2026-08-08 17:00:00', 'Estádio Municipal', 'finalizada'),
  (7, 9, 2, 1, 31, 32, 1, 2, 32, '2026-08-15 16:00:00', 'Estádio Municipal', 'finalizada');

INSERT INTO `resultados` (`inscricao_id`, `colocacao`, `marca`) VALUES
  (26, 1, '00:28.91'),
  (27, 2, '00:29.40'),
  (30, 3, '00:29.87'),
  (28, 4, '00:30.52'),
  (29, 5, '00:31.08');

INSERT INTO `noticias` (`titulo`, `categoria`, `publicado_em`, `resumo`, `conteudo`, `autor_id`) VALUES
  ('Falcões FC vence o Panteras e está na final da Copa NextMatch', 'Futebol', '2026-10-31 19:30:00', 'Com gol nos minutos finais, o Falcões FC venceu o Panteras Futsal por 2 a 1 e garantiu vaga na decisão do Sub-20.', 'O Falcões FC está na final da Copa NextMatch de Futebol Sub-20. Na tarde deste sábado, na Arena Central, a equipe do professor Ricardo Alves venceu o Panteras Futsal por 2 a 1, de virada, com gol já nos acréscimos.

O adversário na final sai do confronto entre União Atlética e Guarani Jr., marcado para o próximo sábado (7). A decisão acontece no dia 14 de novembro, às 16h, também na Arena Central.

O chaveamento completo e os resultados de cada fase podem ser acompanhados na página da competição.', 1),
  ('Inscrições online e chaveamento automático já estão no ar', 'Plataforma', '2026-10-30 10:00:00', 'Organizadores agora aprovam inscrições e geram o chaveamento com um clique, direto pelo painel.', 'A NextMatch liberou duas das funções mais pedidas por organizadores: a inscrição online de equipes e atletas e o chaveamento automático.

Professores inscrevem suas equipes pela página do campeonato; atletas fazem a própria inscrição nas modalidades individuais. O organizador aprova ou recusa cada pedido no painel, e todos recebem uma notificação.

Com as inscrições fechadas, basta um clique para sortear o mata-mata ou gerar a tabela de pontos corridos. Os resultados lançados atualizam a chave, a classificação e o ranking na hora.', 1),
  ('Vôlei Fênix segue invicto na Liga Municipal', 'Vôlei', '2026-10-25 09:00:00', 'Equipe campineira venceu o Ases da Rede por 3 sets a 0 e divide a liderança com o Águias do Saque.', 'O Vôlei Fênix manteve os 100% de aproveitamento na Liga Municipal de Vôlei. Na segunda rodada, a equipe da professora Camila Rocha não deu chances ao Ases da Rede e fechou o jogo em 3 sets a 0.

O Águias do Saque também venceu e chega à rodada final empatado em pontos com o Fênix. O confronto direto entre as duas equipes, no dia 7 de novembro, vai decidir o título.', 1),
  ('Inscrições abertas para a Meia Maratona NextMatch', 'Atletismo', '2026-10-20 08:00:00', 'A prova acontece no dia 22 de novembro na orla do Rio Sorocaba, com percurso de 21 km e cronometragem por chip.', 'Estão abertas as inscrições para a Meia Maratona NextMatch, marcada para 22 de novembro, em Sorocaba. O percurso de 21 km acompanha a orla do rio e tem largada às 6h30.

Todos os concluintes recebem medalha finisher, e os três primeiros de cada categoria levam troféu. As inscrições são feitas pela própria plataforma e confirmadas pela organização.', 1),
  ('Torneio 3x3 de Jundiaí abre vagas para a categoria livre', 'Basquete', '2026-10-15 12:00:00', 'São 8 vagas para equipes de três atletas mais um reserva. O chaveamento será sorteado ao fim das inscrições.', 'O Torneio de Basquete 3x3 da Quadra do Parque, em Jundiaí, está com inscrições abertas para a categoria livre. São 8 vagas, e cada equipe inscreve três titulares e um reserva.

As partidas têm 10 minutos corridos ou terminam quando uma equipe chega a 21 pontos. O sorteio do chaveamento acontece pela plataforma assim que as inscrições forem encerradas.', 1),
  ('NextMatch chega a 18 equipes cadastradas', 'Plataforma', '2026-10-10 10:00:00', 'Futebol, vôlei, basquete, tênis e natação já têm equipes ativas na plataforma.', 'A NextMatch alcançou 18 equipes cadastradas em cinco modalidades. O crescimento vem principalmente de professores que trocaram planilhas e grupos de mensagem pela gestão de elenco e inscrições dentro da plataforma.

A próxima etapa é ampliar o calendário de competições na região de Campinas, Jundiaí e Sorocaba.', 1),
  ('Capoeira e jiu-jitsu chegam à NextMatch', 'Lutas', '2026-10-05 15:00:00', 'Novas modalidades estreiam com a Copa de Jiu-Jitsu, em dezembro, e o Festival de Capoeira, em janeiro.', 'Capoeira e lutas agora fazem parte das modalidades da NextMatch. A estreia será com a Copa NextMatch de Jiu-Jitsu, em 12 de dezembro, em Campinas, e com o Festival de Capoeira, em 16 de janeiro, em Jundiaí.

As inscrições abrem nas próximas semanas — acompanhe pela página de competições.', 1),
  ('Mariana Duarte é ouro nos 50m livre do Sub-18', 'Natação', '2026-06-19 09:00:00', 'Atleta dos Golfinhos Natação venceu com 28s91 no Centro Aquático de Itu.', 'Mariana Duarte, dos Golfinhos Natação, conquistou a medalha de ouro nos 50 metros livre do Campeonato de Natação Sub-18, com o tempo de 28s91. Sofia Carvalho (29s40) e Júlia Teixeira (29s87) completaram o pódio.

A classificação completa está disponível na página da competição.', 1);

INSERT INTO `notificacoes` (`usuario_id`, `titulo`, `mensagem`, `link`, `lida`, `criado_em`) VALUES
  (10, 'Inscrição aprovada', 'Sua inscrição no Circuito de Tênis Amador (Masculino) foi aprovada.', 'detalhes-competicao.php?id=4', 0, '2026-10-23 09:00:00'),
  (10, 'Falcões FC na final!', 'Sua equipe venceu a semifinal da Copa NextMatch de Futebol.', 'detalhes-competicao.php?id=1', 0, '2026-10-31 18:05:00'),
  (10, 'Chaveamento publicado', 'O chaveamento da Copa NextMatch de Futebol foi gerado.', 'detalhes-competicao.php?id=1', 1, '2026-10-12 10:00:00'),
  (2, 'Nova solicitação', 'Rafael Souza quer entrar na equipe Falcões FC.', 'dashboard-professor.php', 0, '2026-11-01 21:10:00'),
  (2, 'Nova solicitação', 'Enzo Ferreira quer entrar na equipe Falcões Basquete.', 'dashboard-professor.php', 0, '2026-11-02 07:45:00'),
  (2, 'Inscrição enviada', 'Falcões Basquete foi inscrita no Torneio de Basquete 3x3 e aguarda aprovação.', 'detalhes-competicao.php?id=3', 1, '2026-11-01 20:05:00'),
  (1, 'Nova inscrição', 'Rinos BBall pediu inscrição no Torneio de Basquete 3x3.', 'gerenciar-campeonato.php?id=3', 0, '2026-10-30 18:20:00'),
  (1, 'Nova inscrição', 'Falcões Basquete pediu inscrição no Torneio de Basquete 3x3.', 'gerenciar-campeonato.php?id=3', 0, '2026-11-01 20:05:00'),
  (1, 'Nova inscrição', 'Gustavo Rezende pediu inscrição no Circuito de Tênis Amador.', 'gerenciar-campeonato.php?id=4', 0, '2026-11-01 09:30:00'),
  (1, 'Nova inscrição', 'Lucas Martins pediu inscrição na Meia Maratona NextMatch.', 'gerenciar-campeonato.php?id=5', 0, '2026-11-02 08:10:00');

