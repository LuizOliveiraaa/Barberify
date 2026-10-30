-- Domínio M — Marketplace público (5 tabelas).

CREATE TABLE `marketplace_perfis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `descricao` TEXT NULL,
  `logo_arquivo_id` BIGINT UNSIGNED NULL,
  `capa_arquivo_id` BIGINT UNSIGNED NULL,
  `site` VARCHAR(180) NULL,
  `instagram` VARCHAR(80) NULL,
  `facebook` VARCHAR(80) NULL,
  `tiktok` VARCHAR(80) NULL,
  `visivel` TINYINT(1) NOT NULL DEFAULT 1,
  `destaque_ate` DATE NULL,
  `aceita_agendamento` TINYINT(1) NOT NULL DEFAULT 1,
  `politica_cancelamento` TEXT NULL,
  `nota_media` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `total_avaliacoes` INT UNSIGNED NOT NULL DEFAULT 0,
  `ordem_busca` INT NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_perfil_empresa` (`empresa_id`),
  KEY `idx_marketplace_visivel` (`visivel`, `nota_media`),
  CONSTRAINT `fk_marketplace_perfil_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_perfil_logo` FOREIGN KEY (`logo_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_marketplace_perfil_capa` FOREIGN KEY (`capa_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketplace_midias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'galeria',
  `arquivo_id` BIGINT UNSIGNED NOT NULL,
  `legenda` VARCHAR(160) NULL,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_midias_empresa` (`empresa_id`, `tipo`),
  CONSTRAINT `fk_marketplace_midia_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_midia_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketplace_avaliacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NULL,
  `agendamento_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NULL,
  `nota` TINYINT UNSIGNED NOT NULL,
  `comentario` VARCHAR(1000) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'publicada',
  `resposta` VARCHAR(1000) NULL,
  `respondido_em` DATETIME NULL,
  `respondido_por` BIGINT UNSIGNED NULL,
  `publicada_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_avaliacao_agendamento` (`agendamento_id`),
  KEY `idx_marketplace_avaliacoes_empresa` (`empresa_id`, `status`, `publicada_em`),
  KEY `idx_marketplace_avaliacoes_profissional` (`profissional_id`),
  CONSTRAINT `fk_marketplace_aval_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_aval_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_marketplace_aval_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_aval_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_aval_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_marketplace_aval_usuario` FOREIGN KEY (`respondido_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketplace_avaliacao_denuncias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `avaliacao_id` BIGINT UNSIGNED NOT NULL,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `motivo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(500) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'aberta',
  `resolvido_por` BIGINT UNSIGNED NULL,
  `resolvido_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_denuncias_avaliacao` (`avaliacao_id`),
  KEY `idx_marketplace_denuncias_status` (`status`),
  CONSTRAINT `fk_marketplace_den_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_den_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `marketplace_avaliacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_den_usuario` FOREIGN KEY (`resolvido_por`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketplace_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NULL,
  `tipo` VARCHAR(24) NOT NULL,
  `origem` VARCHAR(24) NULL,
  `termo_busca` VARCHAR(120) NULL,
  `cidade` VARCHAR(80) NULL,
  `sessao_hash` CHAR(32) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_eventos` (`empresa_id`, `tipo`, `criado_em`),
  CONSTRAINT `fk_marketplace_evento_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marketplace_evento_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
