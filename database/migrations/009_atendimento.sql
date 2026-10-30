-- Domínio I — Atendimento e vendas (3 tabelas).
-- comandas.caixa_id e comanda_pagamentos.caixa_movimento_id ficam sem FK por
-- dependência circular com o domínio financeiro; a validação é feita no serviço.

CREATE TABLE `comandas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NULL,
  `agendamento_id` BIGINT UNSIGNED NULL,
  `caixa_id` BIGINT UNSIGNED NULL,
  `profissional_id` BIGINT UNSIGNED NULL,
  `numero` INT UNSIGNED NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'aberta',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `desconto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `acrescimo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fechada_em` DATETIME NULL,
  `fechada_por` BIGINT UNSIGNED NULL,
  `cancelada_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comandas_numero` (`empresa_id`, `numero`),
  KEY `idx_comandas_status` (`empresa_id`, `status`, `criado_em`),
  KEY `idx_comandas_agendamento` (`agendamento_id`),
  CONSTRAINT `fk_comandas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comandas_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_comandas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comandas_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comandas_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comandas_fechada_por` FOREIGN KEY (`fechada_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comanda_itens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `comanda_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(12) NOT NULL,
  `referencia_id` BIGINT UNSIGNED NULL,
  `descricao_snapshot` VARCHAR(160) NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  `preco_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_dinheiro` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_referencia` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `profissional_id` BIGINT UNSIGNED NULL,
  `comissao_tipo_snapshot` VARCHAR(12) NULL,
  `comissao_valor_snapshot` DECIMAL(12,2) NULL,
  `fidelidade_utilizacao_id` BIGINT UNSIGNED NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comanda_itens_comanda` (`comanda_id`),
  KEY `idx_comanda_itens_empresa` (`empresa_id`, `criado_em`),
  CONSTRAINT `fk_comanda_itens_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comanda_itens_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comanda_itens_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comanda_pagamentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `comanda_id` BIGINT UNSIGNED NOT NULL,
  `forma_pagamento_id` BIGINT UNSIGNED NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `parcelas` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `bandeira` VARCHAR(30) NULL,
  `troco` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `caixa_movimento_id` BIGINT UNSIGNED NULL,
  `estornado_em` DATETIME NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comanda_pagamentos_comanda` (`comanda_id`),
  CONSTRAINT `fk_comanda_pag_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comanda_pag_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
