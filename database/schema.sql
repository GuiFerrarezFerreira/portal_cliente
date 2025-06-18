-- Estrutura do banco de dados para o Sistema Completo de Mudanças

-- Tabela de usuários (atualizada com novos tipos)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    tipo ENUM('gestor', 'vendedor', 'cotador', 'coordenador', 'cliente') NOT NULL,
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME,
    INDEX idx_tipo (tipo),
    INDEX idx_email (email)
);

-- Tabela de vistorias (atualizada)
CREATE TABLE IF NOT EXISTS vistorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    vendedor VARCHAR(100),
    vendedor_id INT,
    endereco TEXT NOT NULL,
    tipo_imovel VARCHAR(50) NOT NULL,
    data_vistoria DATETIME NOT NULL,
    status ENUM('Pendente', 'Concluída', 'Enviada_Cotacao', 'Cotacao_Aprovada', 'Proposta_Enviada', 'Proposta_Aceita', 'Em_Andamento', 'Finalizada', 'Cancelada') DEFAULT 'Pendente',
    observacoes TEXT,
    arquivo_lista_seguro VARCHAR(255),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
    INDEX idx_status (status),
    INDEX idx_cpf (cpf)
);

-- Tabela de cotações
CREATE TABLE IF NOT EXISTS cotacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vistoria_id INT NOT NULL,
    responsavel_id INT,
    status ENUM('Aguardando_Parceiros', 'Em_Cotacao', 'Cotacoes_Recebidas', 'Aprovada', 'Rejeitada') DEFAULT 'Aguardando_Parceiros',
    valor_aprovado DECIMAL(10,2),
    mapa_cotacao TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aprovacao DATETIME,
    FOREIGN KEY (vistoria_id) REFERENCES vistorias(id),
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    INDEX idx_status (status)
);

-- Tabela de parceiros
CREATE TABLE IF NOT EXISTS parceiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    ativo BOOLEAN DEFAULT 1,
    token_acesso VARCHAR(64),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de cotações dos parceiros
CREATE TABLE IF NOT EXISTS cotacoes_parceiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotacao_id INT NOT NULL,
    parceiro_id INT NOT NULL,
    valor DECIMAL(10,2),
    prazo_dias INT,
    observacoes TEXT,
    data_resposta TIMESTAMP NULL,
    ip_resposta VARCHAR(45),
    FOREIGN KEY (cotacao_id) REFERENCES cotacoes(id),
    FOREIGN KEY (parceiro_id) REFERENCES parceiros(id),
    UNIQUE KEY unique_cotacao_parceiro (cotacao_id, parceiro_id)
);

-- Tabela de propostas
CREATE TABLE IF NOT EXISTS propostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vistoria_id INT NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    descricao_servicos TEXT,
    validade_dias INT DEFAULT 30,
    status ENUM('Criada', 'Enviada', 'Aceita', 'Rejeitada', 'Expirada') DEFAULT 'Criada',
    token_aceite VARCHAR(64),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_envio DATETIME,
    data_aceite DATETIME,
    ip_aceite VARCHAR(45),
    FOREIGN KEY (vistoria_id) REFERENCES vistorias(id)
);

-- Tabela de mudanças (projetos)
CREATE TABLE IF NOT EXISTS mudancas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vistoria_id INT NOT NULL,
    proposta_id INT NOT NULL,
    cliente_id INT NOT NULL,
    coordenador_id INT,
    status ENUM('Aguardando_Documentos', 'Documentos_Recebidos', 'Agendada', 'Em_Embalagem', 'Em_Transporte', 'Entregue', 'Finalizada') DEFAULT 'Aguardando_Documentos',
    data_embalagem DATE,
    data_retirada DATE,
    data_entrega_prevista DATE,
    data_entrega_real DATE,
    observacoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vistoria_id) REFERENCES vistorias(id),
    FOREIGN KEY (proposta_id) REFERENCES propostas(id),
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    FOREIGN KEY (coordenador_id) REFERENCES usuarios(id),
    INDEX idx_status (status)
);

-- Tabela de documentos
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mudanca_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    enviado_por INT NOT NULL,
    status ENUM('Pendente', 'Enviado', 'Aprovado', 'Rejeitado') DEFAULT 'Pendente',
    observacoes TEXT,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mudanca_id) REFERENCES mudancas(id),
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id)
);

-- Tabela de solicitações de documentos
CREATE TABLE IF NOT EXISTS solicitacoes_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mudanca_id INT NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    descricao TEXT,
    obrigatorio BOOLEAN DEFAULT 1,
    status ENUM('Pendente', 'Recebido') DEFAULT 'Pendente',
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_recebimento DATETIME,
    FOREIGN KEY (mudanca_id) REFERENCES mudancas(id)
);

-- Tabela de histórico de status
CREATE TABLE IF NOT EXISTS historico_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabela VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50) NOT NULL,
    usuario_id INT,
    observacoes TEXT,
    data_mudanca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_tabela_registro (tabela, registro_id)
);

-- Tabela de feedbacks
CREATE TABLE IF NOT EXISTS feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mudanca_id INT NOT NULL,
    cliente_id INT NOT NULL,
    nota_geral INT CHECK (nota_geral >= 1 AND nota_geral <= 5),
    nota_embalagem INT CHECK (nota_embalagem >= 1 AND nota_embalagem <= 5),
    nota_transporte INT CHECK (nota_transporte >= 1 AND nota_transporte <= 5),
    nota_entrega INT CHECK (nota_entrega >= 1 AND nota_entrega <= 5),
    comentarios TEXT,
    melhorias TEXT,
    recomendaria BOOLEAN,
    data_feedback TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mudanca_id) REFERENCES mudancas(id),
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id)
);

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT,
    lida BOOLEAN DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_leitura DATETIME,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_usuario_lida (usuario_id, lida)
);

-- Tabela de logs de email
CREATE TABLE IF NOT EXISTS logs_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destinatario VARCHAR(100) NOT NULL,
    assunto VARCHAR(200) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    status ENUM('Enviado', 'Erro') NOT NULL,
    erro TEXT,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_status (status)
);

-- Inserir usuários padrão
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Admin Sistema', 'admin@sistema.com', 'admin123', 'gestor'),
('João Vendedor', 'vendedor@sistema.com', 'vendedor123', 'vendedor'),
('Maria Cotadora', 'cotador@sistema.com', 'cotador123', 'cotador'),
('Pedro Coordenador', 'coordenador@sistema.com', 'coordenador123', 'coordenador');

-- Inserir parceiros de exemplo
INSERT INTO parceiros (nome, email, telefone) VALUES
('Transportadora ABC', 'contato@transportadoraabc.com', '(11) 1234-5678'),
('Mudanças Express', 'comercial@mudancasexpress.com', '(11) 2345-6789'),
('Logística Prime', 'orcamento@logisticaprime.com', '(11) 3456-7890'),
('Fast Mudanças', 'vendas@fastmudancas.com', '(11) 4567-8901'),
('Transporte Seguro', 'contato@transporteseguro.com', '(11) 5678-9012');