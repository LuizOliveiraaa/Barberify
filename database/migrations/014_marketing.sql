-- Domínio N — Marketing, Vitrine e Indicações (4 tabelas).

CREATE TABLE `campanhas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'whatsapp',
  `segmento` VARCHAR(40) NOT NULL DEFAULT 'customizado',
  `segmento_filtros` JSON NULL,
  `mensagem` TEXT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'rascunho',
  `enviada_em` DATETIME NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campanhas_empresa` (`empresa_id`, `status`),
  CONSTRAINT `fk_campanhas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campanhas_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `campanha_destinatarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `campanha_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pendente',
  `enviado_em` DATETIME NULL,
  `convertido_em` DATETIME NULL,
  `agendamento_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campanha_destinatario` (`campanha_id`, `cliente_id`),
  KEY `idx_campanha_dest_empresa` (`empresa_id`),
  CONSTRAINT `fk_campanha_dest_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campanha_dest_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `campanhas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campanha_dest_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campanha_dest_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vitrine_ofertas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `titulo` VARCHAR(140) NOT NULL,
  `descricao` TEXT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'percentual',
  `valor` DECIMAL(12,2) NULL,
  `servicos` JSON NULL,
  `inicio` DATE NOT NULL,
  `fim` DATE NOT NULL,
  `canais` VARCHAR(60) NOT NULL DEFAULT 'marketplace',
  `limite_usos` INT UNSIGNED NULL,
  `usos` INT UNSIGNED NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vitrine_empresa` (`empresa_id`, `ativo`, `inicio`, `fim`),
  CONSTRAINT `fk_vitrine_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `indicacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `indicador_cliente_id` BIGINT UNSIGNED NOT NULL,
  `codigo` VARCHAR(16) NOT NULL,
  `indicado_cliente_id` BIGINT UNSIGNED NULL,
  `indicado_nome` VARCHAR(140) NULL,
  `indicado_telefone` VARCHAR(20) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pendente',
  `recompensa_descricao` VARCHAR(160) NULL,
  `recompensa_tipo` VARCHAR(16) NULL,
  `recompensa_valor` DECIMAL(12,2) NULL,
  `liberada_em` DATETIME NULL,
  `primeiro_agendamento_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicacoes_codigo` (`empresa_id`, `codigo`),
  KEY `idx_indicacoes_indicador` (`empresa_id`, `indicador_cliente_id`),
  CONSTRAINT `fk_indicacoes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicacoes_indicador` FOREIGN KEY (`indicador_cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_indicacoes_indicado` FOREIGN KEY (`indicado_cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_indicacoes_agendamento` FOREIGN KEY (`primeiro_agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
