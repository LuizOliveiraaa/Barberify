-- Domínio B — Barberify Control: identidade global da Soluções AL (16 tabelas).
-- Nenhuma tabela deste domínio possui empresa_id como coluna de tenant.

CREATE TABLE `control_usuarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `senha_hash` VARCHAR(255) NOT NULL,
  `is_proprietario` TINYINT(1) NULL DEFAULT NULL,
  `protegido` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `cargo` VARCHAR(40) NULL,
  `totp_segredo` VARCHAR(255) NULL,
  `totp_ativo` TINYINT(1) NOT NULL DEFAULT 0,
  `ultimo_acesso` DATETIME NULL,
  `trocar_senha` TINYINT(1) NOT NULL DEFAULT 0,
  `tentativas_falhas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_usuarios_email` (`email`),
  UNIQUE KEY `uq_control_proprietario` (`is_proprietario`),
  KEY `idx_control_usuarios_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_papeis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(255) NULL,
  `sistema` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_papeis_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_permissoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(80) NOT NULL,
  `modulo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(160) NOT NULL,
  `critica` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_permissoes_chave` (`chave`),
  KEY `idx_control_permissoes_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_papel_permissoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `papel_id` BIGINT UNSIGNED NOT NULL,
  `permissao_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_papel_permissao` (`papel_id`, `permissao_id`),
  KEY `idx_control_pp_permissao` (`permissao_id`),
  CONSTRAINT `fk_control_pp_papel` FOREIGN KEY (`papel_id`) REFERENCES `control_papeis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_pp_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `control_permissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_usuario_papeis` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `papel_id` BIGINT UNSIGNED NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_usuario_papel` (`usuario_id`, `papel_id`),
  KEY `idx_control_up_papel` (`papel_id`),
  CONSTRAINT `fk_control_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_up_papel` FOREIGN KEY (`papel_id`) REFERENCES `control_papeis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_sessoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `estado` VARCHAR(16) NOT NULL DEFAULT 'ativa',
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expira_em` DATETIME NOT NULL,
  `ultimo_uso` DATETIME NOT NULL,
  `revogada_em` DATETIME NULL,
  `motivo_revogacao` VARCHAR(60) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_sessoes_token` (`token_hash`),
  KEY `idx_control_sessoes_usuario` (`usuario_id`, `revogada_em`),
  CONSTRAINT `fk_control_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_recuperacao_senha` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira_em` DATETIME NOT NULL,
  `usado_em` DATETIME NULL,
  `ip` VARCHAR(45) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_recuperacao_token` (`token_hash`),
  KEY `idx_control_recuperacao_usuario` (`usuario_id`),
  CONSTRAINT `fk_control_recuperacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_codigos_recuperacao` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `codigo_hash` CHAR(64) NOT NULL,
  `usado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_codigo` (`usuario_id`, `codigo_hash`),
  CONSTRAINT `fk_control_codigo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_log_acessos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NULL,
  `email_tentado` VARCHAR(160) NULL,
  `resultado` VARCHAR(24) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_control_log_usuario` (`usuario_id`, `criado_em`),
  KEY `idx_control_log_resultado` (`resultado`, `criado_em`),
  CONSTRAINT `fk_control_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_configuracoes` (
  `chave` VARCHAR(60) NOT NULL,
  `valor` TEXT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'string',
  `critica` TINYINT(1) NOT NULL DEFAULT 0,
  `atualizado_por` BIGINT UNSIGNED NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chave`),
  CONSTRAINT `fk_control_config_usuario` FOREIGN KEY (`atualizado_por`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_observacoes_empresa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NULL,
  `texto` TEXT NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_control_obs_empresa` (`empresa_id`, `criado_em`),
  CONSTRAINT `fk_control_obs_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_obs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `protocolo` VARCHAR(20) NOT NULL,
  `assunto` VARCHAR(160) NOT NULL,
  `categoria` VARCHAR(40) NOT NULL DEFAULT 'geral',
  `prioridade` VARCHAR(12) NOT NULL DEFAULT 'normal',
  `status` VARCHAR(24) NOT NULL DEFAULT 'aberto',
  `responsavel_id` BIGINT UNSIGNED NULL,
  `aberto_por_tipo` VARCHAR(16) NOT NULL DEFAULT 'empresa',
  `aberto_por_id` BIGINT UNSIGNED NULL,
  `resolvido_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_tickets_protocolo` (`protocolo`),
  KEY `idx_control_tickets_empresa` (`empresa_id`, `status`),
  KEY `idx_control_tickets_fila` (`status`, `prioridade`),
  CONSTRAINT `fk_control_tickets_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_tickets_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_ticket_mensagens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `autor_tipo` VARCHAR(16) NOT NULL,
  `autor_id` BIGINT UNSIGNED NULL,
  `mensagem` TEXT NOT NULL,
  `interno` TINYINT(1) NOT NULL DEFAULT 0,
  `arquivo_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_control_ticket_msg` (`ticket_id`, `criado_em`),
  CONSTRAINT `fk_control_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `control_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_msg_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_acessos_suporte` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `ticket_id` BIGINT UNSIGNED NULL,
  `solicitante_id` BIGINT UNSIGNED NOT NULL,
  `aprovador_id` BIGINT UNSIGNED NULL,
  `justificativa` TEXT NOT NULL,
  `escopo` VARCHAR(255) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'solicitado',
  `inicio` DATETIME NULL,
  `expira_em` DATETIME NULL,
  `encerrado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_control_acesso_empresa` (`empresa_id`, `status`),
  CONSTRAINT `fk_control_acesso_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_acesso_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `control_tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_control_acesso_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `control_usuarios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_control_acesso_aprovador` FOREIGN KEY (`aprovador_id`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_comunicados` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(160) NOT NULL,
  `corpo` TEXT NOT NULL,
  `tipo` VARCHAR(24) NOT NULL DEFAULT 'info',
  `segmento` VARCHAR(24) NOT NULL DEFAULT 'todas',
  `plano_id` BIGINT UNSIGNED NULL,
  `empresa_id` BIGINT UNSIGNED NULL,
  `publicado_em` DATETIME NULL,
  `expira_em` DATETIME NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_control_comunicados_pub` (`publicado_em`),
  CONSTRAINT `fk_control_comunicado_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_comunicado_usuario` FOREIGN KEY (`criado_por`) REFERENCES `control_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `control_comunicado_destinatarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comunicado_id` BIGINT UNSIGNED NOT NULL,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `lido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_control_comunicado_destino` (`comunicado_id`, `empresa_id`),
  KEY `idx_control_comunicado_empresa` (`empresa_id`, `lido_em`),
  CONSTRAINT `fk_control_dest_comunicado` FOREIGN KEY (`comunicado_id`) REFERENCES `control_comunicados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_control_dest_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
