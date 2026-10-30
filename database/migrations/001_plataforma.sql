-- Domínio A — Plataforma, empresas e sistema (12 tabelas).
-- A tabela `migracoes` é criada pelo Migrator antes desta migration.

CREATE TABLE `empresas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(60) NOT NULL,
  `nome_fantasia` VARCHAR(140) NOT NULL,
  `razao_social` VARCHAR(160) NULL,
  `cpf_cnpj` VARCHAR(18) NULL,
  `email` VARCHAR(160) NULL,
  `telefone` VARCHAR(20) NULL,
  `whatsapp` VARCHAR(20) NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'ativa',
  `trial_concedido_em` DATETIME NULL,
  `suspensa_em` DATETIME NULL,
  `cancelada_em` DATETIME NULL,
  `motivo_status` VARCHAR(255) NULL,
  `origem_cadastro` VARCHAR(24) NOT NULL DEFAULT 'control',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empresas_slug` (`slug`),
  UNIQUE KEY `uq_empresas_documento` (`cpf_cnpj`),
  KEY `idx_empresas_status` (`status`),
  KEY `idx_empresas_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empresa_configuracoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `chave` VARCHAR(60) NOT NULL,
  `valor` TEXT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'string',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_empresa_chave` (`empresa_id`, `chave`),
  CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `telefone` VARCHAR(20) NULL,
  `whatsapp` VARCHAR(20) NULL,
  `email` VARCHAR(160) NULL,
  `cep` VARCHAR(9) NULL,
  `logradouro` VARCHAR(160) NULL,
  `numero` VARCHAR(20) NULL,
  `complemento` VARCHAR(80) NULL,
  `bairro` VARCHAR(80) NULL,
  `cidade` VARCHAR(80) NULL,
  `uf` CHAR(2) NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `fuso` VARCHAR(40) NOT NULL DEFAULT 'America/Sao_Paulo',
  `aceita_agendamento_online` TINYINT(1) NOT NULL DEFAULT 1,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativa',
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unidades_empresa_slug` (`empresa_id`, `slug`),
  KEY `idx_unidades_empresa_status` (`empresa_id`, `status`),
  KEY `idx_unidades_cidade_uf` (`cidade`, `uf`),
  KEY `idx_unidades_coordenadas` (`latitude`, `longitude`),
  CONSTRAINT `fk_unidades_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unidade_horarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `dia_semana` TINYINT NOT NULL,
  `aberto` TINYINT(1) NOT NULL DEFAULT 1,
  `hora_abertura` TIME NULL,
  `hora_fechamento` TIME NULL,
  `intervalo_inicio` TIME NULL,
  `intervalo_fim` TIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_horarios_unidade_dia` (`unidade_id`, `dia_semana`),
  KEY `idx_horarios_empresa` (`empresa_id`),
  CONSTRAINT `fk_horarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_horarios_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unidade_bloqueios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'fechado',
  `data_inicio` DATETIME NOT NULL,
  `data_fim` DATETIME NOT NULL,
  `motivo` VARCHAR(160) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bloqueios_unidade_periodo` (`unidade_id`, `data_inicio`, `data_fim`),
  KEY `idx_bloqueios_empresa` (`empresa_id`),
  CONSTRAINT `fk_bloqueios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bloqueios_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `arquivos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NULL,
  `tipo` VARCHAR(24) NOT NULL,
  `caminho` VARCHAR(255) NOT NULL,
  `nome_original` VARCHAR(180) NULL,
  `mime` VARCHAR(80) NOT NULL,
  `tamanho` INT UNSIGNED NOT NULL DEFAULT 0,
  `hash_sha256` CHAR(64) NOT NULL,
  `privado` TINYINT(1) NOT NULL DEFAULT 0,
  `enviado_por_tipo` VARCHAR(16) NULL,
  `enviado_por_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arquivos_empresa_tipo` (`empresa_id`, `tipo`),
  KEY `idx_arquivos_hash` (`hash_sha256`),
  CONSTRAINT `fk_arquivos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auditoria` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NULL,
  `ator_tipo` VARCHAR(16) NOT NULL DEFAULT 'sistema',
  `ator_id` BIGINT UNSIGNED NULL,
  `acao` VARCHAR(80) NOT NULL,
  `modulo` VARCHAR(40) NOT NULL,
  `entidade` VARCHAR(60) NOT NULL,
  `entidade_id` BIGINT UNSIGNED NULL,
  `dados_antes` JSON NULL,
  `dados_depois` JSON NULL,
  `resultado` VARCHAR(16) NOT NULL DEFAULT 'sucesso',
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `request_id` CHAR(26) NULL,
  `contexto` JSON NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_empresa_data` (`empresa_id`, `criado_em`),
  KEY `idx_auditoria_ator` (`ator_tipo`, `ator_id`, `criado_em`),
  KEY `idx_auditoria_acao` (`acao`, `criado_em`),
  KEY `idx_auditoria_entidade` (`entidade`, `entidade_id`),
  CONSTRAINT `fk_auditoria_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sistema_tarefas` (
  `chave` VARCHAR(60) NOT NULL,
  `intervalo_min` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `ultima_execucao` DATETIME NULL,
  `bloqueado_ate` DATETIME NULL,
  `duracao_ms` INT UNSIGNED NULL,
  `ultimo_resultado` VARCHAR(255) NULL,
  `falhas_consecutivas` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idempotencia` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NULL,
  `escopo` VARCHAR(60) NOT NULL,
  `chave` VARCHAR(80) NOT NULL,
  `resposta_json` JSON NULL,
  `expira_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idempotencia_escopo_chave` (`escopo`, `chave`),
  KEY `idx_idempotencia_expira` (`expira_em`),
  CONSTRAINT `fk_idempotencia_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `logs_aplicacao` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nivel` VARCHAR(10) NOT NULL,
  `mensagem` VARCHAR(500) NOT NULL,
  `contexto` JSON NULL,
  `impressao` CHAR(32) NULL,
  `request_id` CHAR(26) NULL,
  `empresa_id` BIGINT UNSIGNED NULL,
  `arquivo` VARCHAR(160) NULL,
  `linha` INT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_nivel_data` (`nivel`, `criado_em`),
  KEY `idx_logs_impressao` (`impressao`, `criado_em`),
  CONSTRAINT `fk_logs_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_fila` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NULL,
  `para` VARCHAR(160) NOT NULL,
  `assunto` VARCHAR(180) NOT NULL,
  `corpo_html` TEXT NULL,
  `corpo_texto` TEXT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pendente',
  `tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_erro` VARCHAR(255) NULL,
  `enviado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_status` (`status`, `criado_em`),
  CONSTRAINT `fk_email_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notificacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `destinatario_tipo` VARCHAR(16) NOT NULL,
  `destinatario_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(40) NOT NULL,
  `titulo` VARCHAR(140) NOT NULL,
  `mensagem` VARCHAR(500) NOT NULL,
  `link` VARCHAR(255) NULL,
  `lida_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notificacoes_destino` (`destinatario_tipo`, `destinatario_id`, `lida_em`),
  CONSTRAINT `fk_notificacoes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
