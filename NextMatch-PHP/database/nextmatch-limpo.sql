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
-- Versão LIMPA: sem dados de demonstração. Só a estrutura e as
-- modalidades. Crie as contas pela tela "Cadastrar".
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
-- DADOS INICIAIS (modalidades)
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


SET FOREIGN_KEY_CHECKS = 1;
