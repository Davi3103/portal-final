-- ============================================================
--  PORTAL DE PEDIDO DE COMPRAS — Schema MySQL
--  Gerado com base nos arquivos HTML do sistema
--  Execute este script no MySQL Workbench ou via terminal:
--    mysql -u root -p < portal_compras_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS portal_compras
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE portal_compras;

-- ============================================================
-- 1. USUÁRIOS / LOGIN
--    Baseado em: login_v3.html, relatorios.html (colunas: ID, Nome, E-mail, Perfil)
-- ============================================================
CREATE TABLE usuarios (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(120)    NOT NULL,
  email         VARCHAR(150)    NOT NULL UNIQUE,
  senha_hash    VARCHAR(255)    NOT NULL,          -- armazene sempre com bcrypt/argon2
  perfil        ENUM(
                  'solicitante',
                  'comprador',
                  'aprovador',
                  'admin'
                )               NOT NULL DEFAULT 'solicitante',
  situacao      ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
-- 2. UNIDADES
--    Baseado em: f-unid-lancamento, f-unid-solicitante
-- ============================================================
CREATE TABLE unidades (
  id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo    VARCHAR(20)   NOT NULL UNIQUE,
  nome      VARCHAR(120)  NOT NULL,
  situacao  ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
-- 3. DESTINOS DE SOLICITAÇÃO
--    Baseado em: f-destino, relatorios.html (Código, Destino)
-- ============================================================
CREATE TABLE destinos (
  id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo    VARCHAR(20)   NOT NULL UNIQUE,
  nome      VARCHAR(120)  NOT NULL,
  situacao  ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
-- 4. CATEGORIAS DE PRODUTO
--    Baseado em: cat-list / cat-filter no portal
-- ============================================================
CREATE TABLE categorias (
  id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(80)   NOT NULL UNIQUE,
  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
-- 5. PRODUTOS
--    Baseado em: "Tabela Produtos", cat-list, itens do pedido
-- ============================================================
CREATE TABLE produtos (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  codigo       VARCHAR(30)      NOT NULL UNIQUE,
  nome         VARCHAR(200)     NOT NULL,
  categoria_id INT UNSIGNED,
  unidade_med  VARCHAR(20)      NOT NULL DEFAULT 'UN',  -- ex: UN, KG, L, CX
  preco_ref    DECIMAL(12,2),                            -- preço de referência
  situacao     ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  PRIMARY KEY (id),
  CONSTRAINT fk_produto_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- 6. SOLICITAÇÕES DE COMPRA (cabeçalho)
--    Baseado em: f-data, f-data-lancamento, f-comprador,
--                f-destino, f-unid-lancamento, f-unid-solicitante,
--                f-prioridade, f-justificativa, f-tipo-justificativa,
--                f-obs, f-nome
--    Status: Pendente | Em análise | Aprovado | Recusado | Cancelado
-- ============================================================
CREATE TABLE solicitacoes (
  id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  numero            VARCHAR(10)     NOT NULL UNIQUE,   -- ex: 000001
  data_solicitacao  DATE            NOT NULL,
  data_lancamento   DATE,

  -- quem fez
  solicitante_id    INT UNSIGNED    NOT NULL,
  unid_lancamento_id INT UNSIGNED   NOT NULL,
  unid_solicitante_id INT UNSIGNED  NOT NULL,

  -- para onde
  destino_id        INT UNSIGNED    NOT NULL,

  -- responsável pela compra
  comprador_id      INT UNSIGNED,

  prioridade        ENUM('Normal','Urgente') NOT NULL DEFAULT 'Normal',
  tipo_justificativa ENUM('Complementar','Extra') DEFAULT NULL,
  justificativa     TEXT,
  observacoes       TEXT,
  nome_referencia   VARCHAR(150),                      -- campo f-nome

  status            ENUM(
                      'Pendente',
                      'Em análise',
                      'Aprovado',
                      'Recusado',
                      'Cancelado'
                    ) NOT NULL DEFAULT 'Pendente',

  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_solic_solicitante
    FOREIGN KEY (solicitante_id)     REFERENCES usuarios(id),
  CONSTRAINT fk_solic_unid_lanc
    FOREIGN KEY (unid_lancamento_id) REFERENCES unidades(id),
  CONSTRAINT fk_solic_unid_solic
    FOREIGN KEY (unid_solicitante_id)REFERENCES unidades(id),
  CONSTRAINT fk_solic_destino
    FOREIGN KEY (destino_id)         REFERENCES destinos(id),
  CONSTRAINT fk_solic_comprador
    FOREIGN KEY (comprador_id)       REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Gera o número sequencial automático via trigger
DELIMITER $$
CREATE TRIGGER trg_numero_solicitacao
BEFORE INSERT ON solicitacoes
FOR EACH ROW
BEGIN
  DECLARE proximo INT;
  SELECT IFNULL(MAX(CAST(numero AS UNSIGNED)), 0) + 1
    INTO proximo
    FROM solicitacoes;
  SET NEW.numero = LPAD(proximo, 6, '0');
END$$
DELIMITER ;


-- ============================================================
-- 7. ITENS DA SOLICITAÇÃO
--    Baseado em: "Itens do Pedido", summary-itens, summary-qtd,
--                rv-count, rv-total
-- ============================================================
CREATE TABLE solicitacao_itens (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  solicitacao_id   INT UNSIGNED    NOT NULL,
  produto_id       INT UNSIGNED    NOT NULL,
  quantidade       DECIMAL(10,3)   NOT NULL DEFAULT 1,
  preco_unitario   DECIMAL(12,2),
  observacao_item  TEXT,
  PRIMARY KEY (id),
  CONSTRAINT fk_item_solicitacao
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_item_produto
    FOREIGN KEY (produto_id)     REFERENCES produtos(id)
) ENGINE=InnoDB;


-- ============================================================
-- 8. HISTÓRICO / ANÁLISE DA SOLICITAÇÃO
--    Baseado em: analises[] no JS (Recebido, Aprovado, Recusado),
--                "Analisar Solicitação"
-- ============================================================
CREATE TABLE solicitacao_analises (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NOT NULL,
  data_acao      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acao           ENUM(
                   'Recebido',
                   'Em análise',
                   'Aprovado',
                   'Recusado',
                   'Cancelado',
                   'Comentário'
                 ) NOT NULL,
  observacao     TEXT,
  PRIMARY KEY (id),
  CONSTRAINT fk_analise_solic
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_analise_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;


-- ============================================================
-- 9. SESSÕES DE LOGIN (opcional — para controle de tokens)
-- ============================================================
CREATE TABLE sessoes (
  id          VARCHAR(64)  NOT NULL,   -- token UUID
  usuario_id  INT UNSIGNED NOT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em   DATETIME     NOT NULL,
  ip_origem   VARCHAR(45),
  PRIMARY KEY (id),
  CONSTRAINT fk_sessao_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- VIEWS ÚTEIS PARA OS RELATÓRIOS
-- ============================================================

-- Relatório por usuário (aba Usuários em relatorios.html)
CREATE OR REPLACE VIEW vw_relatorio_usuarios AS
SELECT
  u.id,
  u.nome,
  u.email,
  u.perfil,
  COUNT(s.id)                                         AS total_solicitacoes,
  SUM(s.status = 'Aprovado')                          AS aprovadas,
  SUM(s.status IN ('Pendente','Em análise'))           AS pendentes,
  u.situacao
FROM usuarios u
LEFT JOIN solicitacoes s ON s.solicitante_id = u.id
GROUP BY u.id;

-- Relatório por produto
CREATE OR REPLACE VIEW vw_relatorio_produtos AS
SELECT
  p.codigo,
  p.nome,
  COUNT(DISTINCT si.solicitacao_id)                   AS total_solicitacoes,
  SUM(s.status = 'Aprovado')                          AS aprovadas,
  SUM(s.status IN ('Pendente','Em análise'))           AS pendentes,
  SUM(s.status = 'Recusado')                          AS recusadas,
  p.situacao
FROM produtos p
LEFT JOIN solicitacao_itens si ON si.produto_id = p.id
LEFT JOIN solicitacoes s       ON s.id = si.solicitacao_id
GROUP BY p.id;

-- Relatório por destino
CREATE OR REPLACE VIEW vw_relatorio_destinos AS
SELECT
  d.codigo,
  d.nome                                              AS destino,
  COUNT(s.id)                                         AS total_solicitacoes,
  SUM(s.status = 'Aprovado')                          AS aprovadas,
  SUM(s.status = 'Em análise')                        AS em_analise,
  SUM(s.status IN ('Pendente'))                       AS pendentes,
  SUM(s.status = 'Recusado')                          AS recusadas,
  d.situacao
FROM destinos d
LEFT JOIN solicitacoes s ON s.destino_id = d.id
GROUP BY d.id;

-- Listagem geral de solicitações (Minhas Solicitações + Relatório geral)
CREATE OR REPLACE VIEW vw_solicitacoes AS
SELECT
  s.id,
  s.numero,
  s.data_solicitacao                                  AS data,
  u_solic.nome                                        AS solicitante,
  un_solic.nome                                       AS unidade,
  d.nome                                              AS destino,
  u_comp.nome                                         AS comprador,
  COUNT(si.id)                                        AS qtd_itens,
  s.status,
  s.prioridade
FROM solicitacoes s
JOIN usuarios  u_solic  ON u_solic.id  = s.solicitante_id
JOIN unidades  un_solic ON un_solic.id = s.unid_solicitante_id
JOIN destinos  d        ON d.id        = s.destino_id
LEFT JOIN usuarios u_comp ON u_comp.id = s.comprador_id
LEFT JOIN solicitacao_itens si ON si.solicitacao_id = s.id
GROUP BY s.id;


-- ============================================================
-- DADOS INICIAIS DE EXEMPLO
-- ============================================================

INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES
  ('Admin Sistema',  'admin@empresa.com.br',  '$2b$12$placeholder_hash_admin',    'admin'),
  ('Thais Camargo',  '359915@empresa.com.br', '$2b$12$placeholder_hash_comprador','comprador'),
  ('João Solicitante','joao@empresa.com.br',  '$2b$12$placeholder_hash_solic',    'solicitante');

INSERT INTO unidades (codigo, nome) VALUES
  ('UND-01', 'Matriz'),
  ('UND-02', 'Filial SP'),
  ('UND-03', 'Filial RJ');

INSERT INTO destinos (codigo, nome) VALUES
  ('DEST-01', 'Almoxarifado Central'),
  ('DEST-02', 'TI'),
  ('DEST-03', 'Facilities');

INSERT INTO categorias (nome) VALUES
  ('Informática'),
  ('Escritório'),
  ('Limpeza'),
  ('Manutenção');

INSERT INTO produtos (codigo, nome, categoria_id, unidade_med, preco_ref) VALUES
  ('PRD-001', 'Notebook Dell Inspiron',   1, 'UN',  4500.00),
  ('PRD-002', 'Resma de Papel A4',        2, 'CX',    35.00),
  ('PRD-003', 'Desinfetante 5L',          3, 'UN',    22.50),
  ('PRD-004', 'Cabo de Rede Cat6 (rolo)', 1, 'RL',   180.00);
