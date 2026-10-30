-- Domínio C — Assinatura SaaS: barbearia paga à Soluções AL (6 tabelas).

CREATE TABLE `saas_planos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(40) NOT NULL,
  `descricao` TEXT NULL,
  `preco` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `periodicidade` VARCHAR(12) NOT NULL DEFAULT 'mensal',
  `trial_dias` SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  `dias_graca_vencida` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `dias_graca_inadimplente` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saas_planos_slug` (`slug`),
  KEY `idx_saas_planos_ativo` (`ativo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saas_plano_limites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `chave` VARCHAR(40) NOT NULL,
  `tipo` VARCHAR(12) NOT NULL DEFAULT 'limite',
  `valor` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saas_limite_plano_chave` (`plano_id`, `chave`),
  CONSTRAINT `fk_saas_limite_plano` FOREIGN KEY (`plano_id`) REFERENCES `saas_planos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saas_assinaturas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'em_teste',
  `inicio` DATE NOT NULL,
  `fim_teste` DATE NULL,
  `proximo_vencimento` DATE NULL,
  `preco_snapshot` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `periodicidade_snapshot` VARCHAR(12) NOT NULL DEFAULT 'mensal',
  `limites_snapshot` JSON NULL,
  `renovacao_automatica` TINYINT(1) NOT NULL DEFAULT 1,
  `cancelada_em` DATETIME NULL,
  `motivo_cancelamento` VARCHAR(255) NULL,
  `ultima_avaliacao` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saas_assinaturas_empresa` (`empresa_id`, `status`),
  KEY `idx_saas_assinaturas_vencimento` (`status`, `proximo_vencimento`),
  CONSTRAINT `fk_saas_assinatura_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_saas_assinatura_plano` FOREIGN KEY (`plano_id`) REFERENCES `saas_planos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saas_assinatura_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `de` VARCHAR(24) NULL,
  `para` VARCHAR(24) NOT NULL,
  `motivo` VARCHAR(255) NULL,
  `ator_tipo` VARCHAR(16) NOT NULL DEFAULT 'sistema',
  `ator_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saas_eventos_empresa` (`empresa_id`, `criado_em`),
  KEY `idx_saas_eventos_assinatura` (`assinatura_id`, `criado_em`),
  CONSTRAINT `fk_saas_evento_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `saas_assinaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saas_evento_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saas_faturas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `competencia` CHAR(7) NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `vencimento` DATE NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'aberta',
  `paga_em` DATETIME NULL,
  `observacao` VARCHAR(255) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saas_fatura_competencia` (`assinatura_id`, `competencia`),
  KEY `idx_saas_faturas_empresa` (`empresa_id`, `status`, `vencimento`),
  CONSTRAINT `fk_saas_fatura_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `saas_assinaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saas_fatura_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saas_pagamentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fatura_id` BIGINT UNSIGNED NOT NULL,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `forma` VARCHAR(24) NOT NULL,
  `data_pagamento` DATE NOT NULL,
  `origem` VARCHAR(12) NOT NULL DEFAULT 'manual',
  `referencia_externa` VARCHAR(120) NULL,
  `comprovante_arquivo_id` BIGINT UNSIGNED NULL,
  `registrado_por` BIGINT UNSIGNED NULL,
  `observacao` VARCHAR(255) NULL,
  `estorno_de_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saas_pagamento_estorno` (`estorno_de_id`),
  KEY `idx_saas_pagamentos_empresa` (`empresa_id`, `data_pagamento`),
  KEY `idx_saas_pagamentos_fatura` (`fatura_id`),
  CONSTRAINT `fk_saas_pagamento_fatura` FOREIGN KEY (`fatura_id`) REFERENCES `saas_faturas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_saas_pagamento_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_saas_pagamento_arquivo` FOREIGN KEY (`comprovante_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_saas_pagamento_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_saas_pagamento_estorno` FOREIGN KEY (`estorno_de_id`) REFERENCES `saas_pagamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
