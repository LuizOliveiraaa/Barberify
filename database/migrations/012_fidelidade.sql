-- Domínio L — Fidelidade e assinaturas dos consumidores (15 tabelas).
-- Independente do domínio SaaS: nenhuma FK ou JOIN entre saas_* e fidelidade_*.

CREATE TABLE `fidelidade_planos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `descricao` TEXT NULL,
  `preco` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `periodicidade` VARCHAR(12) NOT NULL DEFAULT 'mensal',
  `creditos_por_ciclo` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `limite_semanal` SMALLINT UNSIGNED NULL,
  `limite_diario` SMALLINT UNSIGNED NULL,
  `permite_dependentes` TINYINT(1) NOT NULL DEFAULT 0,
  `max_dependentes` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `compartilha_creditos` TINYINT(1) NOT NULL DEFAULT 1,
  `renovacao_automatica` TINYINT(1) NOT NULL DEFAULT 1,
  `dias_validade_ciclo` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `visivel_marketplace` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fid_planos_empresa` (`empresa_id`, `ativo`),
  CONSTRAINT `fk_fid_planos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_plano_servicos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `servico_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_plano_servico` (`plano_id`, `servico_id`),
  KEY `idx_fid_plano_servicos_empresa` (`empresa_id`),
  CONSTRAINT `fk_fid_plano_serv_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_serv_plano` FOREIGN KEY (`plano_id`) REFERENCES `fidelidade_planos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_serv_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_planos_unidades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_plano_unidade` (`plano_id`, `unidade_id`),
  KEY `idx_fid_plano_unidades_empresa` (`empresa_id`),
  CONSTRAINT `fk_fid_plano_unid_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_unid_plano` FOREIGN KEY (`plano_id`) REFERENCES `fidelidade_planos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_unid_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_planos_profissionais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_plano_profissional` (`plano_id`, `profissional_id`),
  KEY `idx_fid_plano_prof_empresa` (`empresa_id`),
  CONSTRAINT `fk_fid_plano_prof_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_prof_plano` FOREIGN KEY (`plano_id`) REFERENCES `fidelidade_planos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_plano_prof_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_assinaturas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `plano_id` BIGINT UNSIGNED NOT NULL,
  `cliente_titular_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'pagamento_pendente',
  `inicio` DATE NOT NULL,
  `fim` DATE NULL,
  `preco_snapshot` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `regras_snapshot` JSON NULL,
  `renovacao_automatica` TINYINT(1) NOT NULL DEFAULT 1,
  `cancelada_em` DATETIME NULL,
  `motivo_cancelamento` VARCHAR(255) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fid_assinaturas_empresa` (`empresa_id`, `status`),
  KEY `idx_fid_assinaturas_titular` (`cliente_titular_id`),
  CONSTRAINT `fk_fid_assinatura_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_assinatura_plano` FOREIGN KEY (`plano_id`) REFERENCES `fidelidade_planos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_assinatura_titular` FOREIGN KEY (`cliente_titular_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_assinatura_membros` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `papel` VARCHAR(12) NOT NULL DEFAULT 'titular',
  `limite_individual` SMALLINT UNSIGNED NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `entrou_em` DATE NOT NULL,
  `removido_em` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_membro` (`assinatura_id`, `cliente_id`),
  KEY `idx_fid_membros_cliente` (`empresa_id`, `cliente_id`),
  CONSTRAINT `fk_fid_membro_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_membro_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `fidelidade_assinaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_membro_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_ciclos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `inicio` DATE NOT NULL,
  `fim` DATE NOT NULL,
  `creditos_concedidos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `creditos_utilizados` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(12) NOT NULL DEFAULT 'ativo',
  `encerrado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_ciclo_numero` (`assinatura_id`, `numero`),
  KEY `idx_fid_ciclos_empresa` (`empresa_id`, `status`, `fim`),
  CONSTRAINT `fk_fid_ciclo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_ciclo_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `fidelidade_assinaturas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_utilizacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `ciclo_id` BIGINT UNSIGNED NOT NULL,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `membro_id` BIGINT UNSIGNED NOT NULL,
  `agendamento_id` BIGINT UNSIGNED NOT NULL,
  `comanda_item_id` BIGINT UNSIGNED NULL,
  `servico_id` BIGINT UNSIGNED NOT NULL,
  `profissional_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `data_uso` DATE NOT NULL,
  `creditos` SMALLINT NOT NULL DEFAULT 1,
  `estornada_em` DATETIME NULL,
  `motivo_estorno` VARCHAR(255) NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_utilizacao_agendamento` (`agendamento_id`),
  KEY `idx_fid_utilizacoes_ciclo` (`empresa_id`, `ciclo_id`, `data_uso`),
  KEY `idx_fid_utilizacoes_membro` (`membro_id`, `data_uso`),
  CONSTRAINT `fk_fid_util_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_util_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `fidelidade_ciclos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `fidelidade_assinaturas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_membro` FOREIGN KEY (`membro_id`) REFERENCES `fidelidade_assinatura_membros` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_util_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_movimentacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `ciclo_id` BIGINT UNSIGNED NOT NULL,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `utilizacao_id` BIGINT UNSIGNED NULL,
  `tipo` VARCHAR(16) NOT NULL,
  `creditos` SMALLINT NOT NULL,
  `saldo_apos` SMALLINT NOT NULL,
  `descricao` VARCHAR(180) NULL,
  `estorno_de_id` BIGINT UNSIGNED NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_mov_utilizacao` (`utilizacao_id`),
  UNIQUE KEY `uq_fid_mov_estorno` (`estorno_de_id`),
  KEY `idx_fid_mov_ciclo` (`empresa_id`, `ciclo_id`, `criado_em`),
  CONSTRAINT `fk_fid_mov_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_mov_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `fidelidade_ciclos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_mov_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `fidelidade_assinaturas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_mov_utilizacao` FOREIGN KEY (`utilizacao_id`) REFERENCES `fidelidade_utilizacoes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_mov_estorno` FOREIGN KEY (`estorno_de_id`) REFERENCES `fidelidade_movimentacoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_pagamentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `assinatura_id` BIGINT UNSIGNED NOT NULL,
  `ciclo_id` BIGINT UNSIGNED NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `forma_pagamento_id` BIGINT UNSIGNED NULL,
  `data_pagamento` DATE NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'pago',
  `lancamento_id` BIGINT UNSIGNED NULL,
  `comprovante_arquivo_id` BIGINT UNSIGNED NULL,
  `registrado_por` BIGINT UNSIGNED NULL,
  `estorno_de_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_pagamento_estorno` (`estorno_de_id`),
  KEY `idx_fid_pagamentos_assinatura` (`empresa_id`, `assinatura_id`, `data_pagamento`),
  CONSTRAINT `fk_fid_pag_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_pag_assinatura` FOREIGN KEY (`assinatura_id`) REFERENCES `fidelidade_assinaturas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_pag_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `fidelidade_ciclos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_pag_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_pag_lancamento` FOREIGN KEY (`lancamento_id`) REFERENCES `financeiro_lancamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_pag_arquivo` FOREIGN KEY (`comprovante_arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_pag_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_pag_estorno` FOREIGN KEY (`estorno_de_id`) REFERENCES `fidelidade_pagamentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_programas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(80) NOT NULL,
  `tipo` VARCHAR(12) NOT NULL DEFAULT 'pontos',
  `regra` VARCHAR(24) NOT NULL DEFAULT 'por_valor',
  `pontos_por_real` DECIMAL(8,3) NULL,
  `validade_dias` SMALLINT UNSIGNED NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fid_programas_empresa` (`empresa_id`, `ativo`),
  CONSTRAINT `fk_fid_programa_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_programa_recompensas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `programa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `descricao` VARCHAR(255) NULL,
  `custo_pontos` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL DEFAULT 'servico',
  `referencia_id` BIGINT UNSIGNED NULL,
  `valor_desconto` DECIMAL(12,2) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fid_recompensas_programa` (`programa_id`, `ativo`),
  CONSTRAINT `fk_fid_recompensa_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_recompensa_programa` FOREIGN KEY (`programa_id`) REFERENCES `fidelidade_programas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_saldos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `programa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `saldo` INT NOT NULL DEFAULT 0,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_saldo_programa_cliente` (`programa_id`, `cliente_id`),
  KEY `idx_fid_saldos_empresa` (`empresa_id`, `cliente_id`),
  CONSTRAINT `fk_fid_saldo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_saldo_programa` FOREIGN KEY (`programa_id`) REFERENCES `fidelidade_programas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_saldo_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_movimentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `programa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL,
  `pontos` INT NOT NULL,
  `saldo_apos` INT NOT NULL,
  `origem_tipo` VARCHAR(24) NULL,
  `origem_id` BIGINT UNSIGNED NULL,
  `chave_idempotencia` VARCHAR(80) NULL,
  `estorno_de_id` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fid_movimento_idempotencia` (`chave_idempotencia`),
  UNIQUE KEY `uq_fid_movimento_estorno` (`estorno_de_id`),
  KEY `idx_fid_movimentos_cliente` (`empresa_id`, `cliente_id`, `criado_em`),
  CONSTRAINT `fk_fid_mov_cliente_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_mov_programa` FOREIGN KEY (`programa_id`) REFERENCES `fidelidade_programas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_mov_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_mov_estorno2` FOREIGN KEY (`estorno_de_id`) REFERENCES `fidelidade_movimentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fidelidade_resgates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `recompensa_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `pontos` INT UNSIGNED NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'solicitado',
  `comanda_id` BIGINT UNSIGNED NULL,
  `aprovado_por` BIGINT UNSIGNED NULL,
  `entregue_em` DATETIME NULL,
  `cancelado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fid_resgates_cliente` (`empresa_id`, `cliente_id`, `status`),
  CONSTRAINT `fk_fid_resgate_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_resgate_recompensa` FOREIGN KEY (`recompensa_id`) REFERENCES `fidelidade_programa_recompensas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_resgate_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fid_resgate_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fid_resgate_usuario` FOREIGN KEY (`aprovado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
