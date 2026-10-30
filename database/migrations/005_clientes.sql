-- Domínio E — Clientes, CRM e conta global do consumidor (7 tabelas).

CREATE TABLE `contas_cliente` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(140) NOT NULL,
  `email` VARCHAR(160) NULL,
  `telefone` VARCHAR(20) NULL,
  `senha_hash` VARCHAR(255) NULL,
  `email_verificado_em` DATETIME NULL,
  `telefone_verificado_em` DATETIME NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `ultimo_acesso` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contas_cliente_email` (`email`),
  KEY `idx_contas_cliente_telefone` (`telefone`),
  KEY `idx_contas_cliente_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clientes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `conta_cliente_id` BIGINT UNSIGNED NULL,
  `nome` VARCHAR(140) NOT NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `telefone_normalizado` VARCHAR(20) NOT NULL,
  `email` VARCHAR(160) NULL,
  `nascimento` DATE NULL,
  `genero` VARCHAR(16) NULL,
  `observacoes` TEXT NULL,
  `preferencias` TEXT NULL,
  `profissional_preferido_id` BIGINT UNSIGNED NULL,
  `origem` VARCHAR(24) NOT NULL DEFAULT 'painel',
  `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `consent_em` DATETIME NULL,
  `ultima_visita` DATETIME NULL,
  `total_atendimentos` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_gasto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clientes_telefone` (`empresa_id`, `telefone_normalizado`),
  KEY `idx_clientes_status` (`empresa_id`, `status`),
  KEY `idx_clientes_ultima_visita` (`empresa_id`, `ultima_visita`),
  KEY `idx_clientes_nascimento` (`empresa_id`, `nascimento`),
  KEY `idx_clientes_conta` (`conta_cliente_id`),
  KEY `idx_clientes_profissional` (`profissional_preferido_id`),
  CONSTRAINT `fk_clientes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clientes_conta` FOREIGN KEY (`conta_cliente_id`) REFERENCES `contas_cliente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_enderecos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `cep` VARCHAR(9) NULL,
  `logradouro` VARCHAR(160) NULL,
  `numero` VARCHAR(20) NULL,
  `complemento` VARCHAR(80) NULL,
  `bairro` VARCHAR(80) NULL,
  `cidade` VARCHAR(80) NULL,
  `uf` CHAR(2) NULL,
  `principal` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_enderecos_cliente` (`cliente_id`),
  CONSTRAINT `fk_cliente_end_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cliente_end_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_notas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NULL,
  `texto` TEXT NOT NULL,
  `arquivada_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_notas_cliente` (`cliente_id`, `criado_em`),
  CONSTRAINT `fk_cliente_nota_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cliente_nota_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cliente_nota_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_consentimentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(24) NOT NULL,
  `concedido` TINYINT(1) NOT NULL DEFAULT 1,
  `origem` VARCHAR(24) NOT NULL DEFAULT 'painel',
  `ip` VARCHAR(45) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_consent_cliente` (`cliente_id`, `tipo`, `criado_em`),
  CONSTRAINT `fk_cliente_consent_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cliente_consent_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conta_cliente_sessoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_cliente_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expira_em` DATETIME NOT NULL,
  `ultimo_uso` DATETIME NULL,
  `revogada_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conta_cliente_sessao_token` (`token_hash`),
  KEY `idx_conta_cliente_sessao_conta` (`conta_cliente_id`, `revogada_em`),
  CONSTRAINT `fk_conta_sessao_conta` FOREIGN KEY (`conta_cliente_id`) REFERENCES `contas_cliente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conta_cliente_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_cliente_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira_em` DATETIME NOT NULL,
  `usado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conta_cliente_token` (`token_hash`),
  KEY `idx_conta_cliente_tokens_conta` (`conta_cliente_id`, `tipo`),
  CONSTRAINT `fk_conta_token_conta` FOREIGN KEY (`conta_cliente_id`) REFERENCES `contas_cliente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
