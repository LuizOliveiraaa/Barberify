-- Domínio K — Estoque e produtos (7 tabelas).

CREATE TABLE `produto_categorias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(80) NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produto_categoria_nome` (`empresa_id`, `nome`),
  CONSTRAINT `fk_produto_categoria_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fornecedores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(140) NOT NULL,
  `cnpj` VARCHAR(18) NULL,
  `telefone` VARCHAR(20) NULL,
  `email` VARCHAR(160) NULL,
  `observacoes` TEXT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fornecedores_empresa` (`empresa_id`, `ativo`),
  CONSTRAINT `fk_fornecedores_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `produtos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `categoria_id` BIGINT UNSIGNED NULL,
  `fornecedor_padrao_id` BIGINT UNSIGNED NULL,
  `sku` VARCHAR(40) NULL,
  `nome` VARCHAR(140) NOT NULL,
  `descricao` TEXT NULL,
  `unidade_medida` VARCHAR(8) NOT NULL DEFAULT 'un',
  `preco_custo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `preco_venda` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `controla_estoque` TINYINT(1) NOT NULL DEFAULT 1,
  `estoque_minimo` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `comissao_percentual` DECIMAL(5,2) NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluido_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produtos_sku` (`empresa_id`, `sku`),
  KEY `idx_produtos_empresa` (`empresa_id`, `ativo`),
  KEY `idx_produtos_categoria` (`categoria_id`),
  CONSTRAINT `fk_produtos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `produto_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_produtos_fornecedor` FOREIGN KEY (`fornecedor_padrao_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `estoque_saldos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `produto_id` BIGINT UNSIGNED NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_estoque_unidade_produto` (`unidade_id`, `produto_id`),
  KEY `idx_estoque_empresa_produto` (`empresa_id`, `produto_id`),
  CONSTRAINT `fk_estoque_saldo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_estoque_saldo_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_estoque_saldo_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `estoque_movimentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `produto_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(16) NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL,
  `custo_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `saldo_apos` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `comanda_id` BIGINT UNSIGNED NULL,
  `compra_id` BIGINT UNSIGNED NULL,
  `transferencia_id` CHAR(26) NULL,
  `motivo` VARCHAR(160) NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estoque_mov_produto` (`empresa_id`, `produto_id`, `criado_em`),
  KEY `idx_estoque_mov_unidade` (`unidade_id`, `criado_em`),
  CONSTRAINT `fk_estoque_mov_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_estoque_mov_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estoque_mov_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estoque_mov_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_estoque_mov_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compras` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `unidade_id` BIGINT UNSIGNED NOT NULL,
  `fornecedor_id` BIGINT UNSIGNED NULL,
  `numero` VARCHAR(20) NOT NULL,
  `data_compra` DATE NOT NULL,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(12) NOT NULL DEFAULT 'rascunho',
  `lancamento_id` BIGINT UNSIGNED NULL,
  `criado_por` BIGINT UNSIGNED NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_compras_numero` (`empresa_id`, `numero`),
  KEY `idx_compras_empresa` (`empresa_id`, `data_compra`),
  CONSTRAINT `fk_compras_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_compras_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_compras_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_compras_lancamento` FOREIGN KEY (`lancamento_id`) REFERENCES `financeiro_lancamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compra_itens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `compra_id` BIGINT UNSIGNED NOT NULL,
  `produto_id` BIGINT UNSIGNED NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL,
  `custo_unitario` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_compra_itens_compra` (`compra_id`),
  KEY `idx_compra_itens_empresa` (`empresa_id`),
  CONSTRAINT `fk_compra_itens_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_compra_itens_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_compra_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
