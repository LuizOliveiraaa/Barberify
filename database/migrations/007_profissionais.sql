-- Domínio F — Profissionais (6 tabelas).

CREATE TABLE `profissionais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NULL,
  `nome` VARCHAR(120) NOT NULL,
  `nome_publico` VARCHAR(120) NULL,
  `slug` VARCHAR(60) NOT NULL,
  `bio` TEXT NULL,
  `foto_arquivo_id` BIGINT UNSIGNED NULL,
  `especialidades` VARCHAR(255) NULL,
  `unidade_principal_id` BIGINT UNSIGNED NULL,
  `comissao_tipo` VARCHAR(12) NOT NULL DEFAULT 'percentual',
  `comissao_percentual` DECIMAL(5,2) NULL,
  `comissao_fixo` DECIMAL(12,2) NULL,
  `comissao_base_assinatura` VARCHAR(12) NOT NULL DEFAULT 'valor_tabela',
  `perfil_publico` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profissionais_slug` (`empresa_id`, `slug`),
  KEY `idx_profissionais_status` (`empresa_id`, `status`),
  KEY `idx_profissionais_usuario` (`usuario_id`),
  KEY `idx_profissionais_unidade` (`unidade_principal_id`),
  CONSTRAINT `fk_profissionais_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profissionais_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profissionais_foto` FOREIGN KEY (`foto_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profissionais_unidade` FOREIGN KEY (`unidade_principal_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profissional_unidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profissional_unidade` (`profissional_id`, `unidade_id`),
  KEY `idx_profissional_unidades_empresa` (`empresa_id`),
  CONSTRAINT `fk_prof_unid_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_unid_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_unid_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profissional_horarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `dia_semana` TINYINT NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `intervalo_inicio` TIME NULL,
  `intervalo_fim` TIME NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_prof_horarios` (`profissional_id`, `unidade_id`, `dia_semana`),
  KEY `idx_prof_horarios_empresa` (`empresa_id`),
  CONSTRAINT `fk_prof_horario_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_horario_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_horario_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profissional_ausencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'folga',
  `inicio` DATETIME NOT NULL,
  `fim` DATETIME NOT NULL,
  `motivo` VARCHAR(160) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prof_ausencias_periodo` (`profissional_id`, `inicio`, `fim`),
  KEY `idx_prof_ausencias_empresa` (`empresa_id`),
  CONSTRAINT `fk_prof_ausencia_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_ausencia_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profissional_metas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `competencia` CHAR(7) NOT NULL,
  `meta_faturamento` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `meta_atendimentos` INT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prof_meta_competencia` (`profissional_id`, `competencia`),
  KEY `idx_prof_metas_empresa` (`empresa_id`),
  CONSTRAINT `fk_prof_meta_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_meta_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profissional_servicos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `servico_id` BIGINT UNSIGNED NOT NULL,
  `preco_especifico` DECIMAL(12,2) NULL,
  `duracao_especifica` SMALLINT UNSIGNED NULL,
  `comissao_percentual_especifica` DECIMAL(5,2) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profissional_servico` (`profissional_id`, `servico_id`),
  KEY `idx_prof_servicos_empresa` (`empresa_id`),
  CONSTRAINT `fk_prof_servico_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_servico_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_servico_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
