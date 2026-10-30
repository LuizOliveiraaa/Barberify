-- Domínio G — Catálogo centralizado de serviços (4 tabelas).

CREATE TABLE `servico_categorias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(40) NOT NULL,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servico_categorias_slug` (`empresa_id`, `slug`),
  CONSTRAINT `fk_servico_categoria_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servicos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `categoria_id` BIGINT UNSIGNED NULL,
  `nome` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `descricao` TEXT NULL,
  `duracao_min` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `preco` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `preco_promocional` DECIMAL(12,2) NULL,
  `promocao_inicio` DATETIME NULL,
  `promocao_fim` DATETIME NULL,
  `imagem_arquivo_id` BIGINT UNSIGNED NULL,
  `visivel_marketplace` TINYINT(1) NOT NULL DEFAULT 1,
  `permite_agendamento_online` TINYINT(1) NOT NULL DEFAULT 1,
  `comissao_tipo` VARCHAR(12) NULL,
  `comissao_valor` DECIMAL(12,2) NULL,
  `requer_avaliacao` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servicos_slug` (`empresa_id`, `slug`),
  KEY `idx_servicos_ativo` (`empresa_id`, `ativo`, `visivel_marketplace`),
  KEY `idx_servicos_categoria` (`categoria_id`),
  CONSTRAINT `fk_servicos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servicos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `servico_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_servicos_imagem` FOREIGN KEY (`imagem_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servico_unidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `servico_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `preco` DECIMAL(12,2) NULL,
  `duracao_min` SMALLINT UNSIGNED NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servico_unidade` (`servico_id`, `unidade_id`),
  KEY `idx_servico_unidades_empresa` (`empresa_id`),
  CONSTRAINT `fk_servico_unid_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servico_unid_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servico_unid_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servico_imagens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `servico_id` BIGINT UNSIGNED NOT NULL,
  `arquivo_id` BIGINT UNSIGNED NOT NULL,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_servico_imagens_servico` (`servico_id`),
  CONSTRAINT `fk_servico_img_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servico_img_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servico_img_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
