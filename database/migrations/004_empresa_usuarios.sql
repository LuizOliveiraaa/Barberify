-- Domínio D — Usuários das barbearias, papéis e sessões (10 tabelas).

CREATE TABLE `usuarios_empresa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `telefone` VARCHAR(20) NULL,
  `senha_hash` VARCHAR(255) NOT NULL,
  `is_proprietario` TINYINT(1) NULL DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `cargo` VARCHAR(40) NULL,
  `totp_segredo` VARCHAR(255) NULL,
  `totp_ativo` TINYINT(1) NOT NULL DEFAULT 0,
  `trocar_senha` TINYINT(1) NOT NULL DEFAULT 0,
  `tentativas_falhas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` DATETIME NULL,
  `ultimo_acesso` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_empresa_email` (`empresa_id`, `email`),
  UNIQUE KEY `uq_usuarios_empresa_proprietario` (`empresa_id`, `is_proprietario`),
  KEY `idx_usuarios_empresa_status` (`empresa_id`, `status`),
  KEY `idx_usuarios_empresa_email_busca` (`email`),
  CONSTRAINT `fk_usuarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_empresa_unidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_unidade` (`usuario_id`, `unidade_id`),
  KEY `idx_usuario_unidade_empresa` (`empresa_id`),
  CONSTRAINT `fk_usuario_unidade_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_unidade_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_unidade_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `papeis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(255) NULL,
  `sistema` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_papeis_empresa_slug` (`empresa_id`, `slug`),
  CONSTRAINT `fk_papeis_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(80) NOT NULL,
  `modulo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(160) NOT NULL,
  `critica` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissoes_chave` (`chave`),
  KEY `idx_permissoes_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `papel_permissoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `papel_id` BIGINT UNSIGNED NOT NULL,
  `permissao_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_papel_permissao` (`papel_id`, `permissao_id`),
  KEY `idx_papel_permissoes_empresa` (`empresa_id`),
  KEY `idx_papel_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_papel_perm_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_papel_perm_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_papel_perm_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_papeis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `papel_id` BIGINT UNSIGNED NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_papel` (`usuario_id`, `papel_id`),
  KEY `idx_usuario_papeis_empresa` (`empresa_id`),
  CONSTRAINT `fk_usuario_papel_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_papel_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_papel_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_sessoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expira_em` DATETIME NOT NULL,
  `ultimo_uso` DATETIME NOT NULL,
  `revogada_em` DATETIME NULL,
  `unidade_ativa_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_sessoes_token` (`token_hash`),
  KEY `idx_usuario_sessoes_usuario` (`empresa_id`, `usuario_id`, `revogada_em`),
  CONSTRAINT `fk_usuario_sessao_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_sessao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_sessao_unidade` FOREIGN KEY (`unidade_ativa_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_recuperacao_senha` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira_em` DATETIME NOT NULL,
  `usado_em` DATETIME NULL,
  `ip` VARCHAR(45) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_recuperacao_token` (`token_hash`),
  KEY `idx_usuario_recuperacao_usuario` (`usuario_id`),
  CONSTRAINT `fk_usuario_recuperacao_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_recuperacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_codigos_recuperacao` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `codigo_hash` CHAR(64) NOT NULL,
  `usado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_codigo` (`usuario_id`, `codigo_hash`),
  CONSTRAINT `fk_usuario_codigo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_codigo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tentativas_login` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escopo` VARCHAR(12) NOT NULL,
  `identificador` VARCHAR(160) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `sucesso` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentativas_identificador` (`escopo`, `identificador`, `criado_em`),
  KEY `idx_tentativas_ip` (`ip`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
