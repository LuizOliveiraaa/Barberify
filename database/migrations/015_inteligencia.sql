-- Domínio O — Inteligência e indicadores (2 tabelas).

CREATE TABLE `inteligencia_oportunidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(40) NOT NULL,
  `titulo` VARCHAR(160) NOT NULL,
  `descricao` TEXT NULL,
  `por_que_importa` VARCHAR(400) NULL,
  `acao_sugerida` VARCHAR(255) NULL,
  `valor_estimado` DECIMAL(12,2) NULL,
  `referencia_tipo` VARCHAR(24) NULL,
  `referencia_id` BIGINT UNSIGNED NULL,
  `prioridade` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `status` VARCHAR(16) NOT NULL DEFAULT 'aberta',
  `gerada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valida_ate` DATE NULL,
  `dispensada_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inteligencia_empresa` (`empresa_id`, `status`, `prioridade`),
  KEY `idx_inteligencia_tipo` (`empresa_id`, `tipo`, `gerada_em`),
  CONSTRAINT `fk_inteligencia_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inteligencia_usuario` FOREIGN KEY (`dispensada_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `indicadores_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NULL,
  `data` DATE NOT NULL,
  `atendimentos` INT UNSIGNED NOT NULL DEFAULT 0,
  `faturamento` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ticket_medio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `clientes_novos` INT UNSIGNED NOT NULL DEFAULT 0,
  `clientes_recorrentes` INT UNSIGNED NOT NULL DEFAULT 0,
  `cancelamentos` INT UNSIGNED NOT NULL DEFAULT 0,
  `nao_compareceram` INT UNSIGNED NOT NULL DEFAULT 0,
  `ocupacao_percentual` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `assinaturas_ativas` INT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicadores_dia` (`empresa_id`, `unidade_id`, `data`),
  KEY `idx_indicadores_data` (`empresa_id`, `data`),
  CONSTRAINT `fk_indicadores_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicadores_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
