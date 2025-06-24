-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24/06/2025 às 21:42
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_vistoria`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vistoria_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `cpf`, `telefone`, `email`, `vistoria_id`, `created_at`, `updated_at`) VALUES
(15, 'Teste 4', '444.444.444-44', '(44) 44444-4444', 'gui.ferrarez.ferreira@gmail.com', 4, '2025-06-24 19:39:41', '2025-06-24 19:39:41');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cotacoes`
--

CREATE TABLE `cotacoes` (
  `id` int(11) NOT NULL,
  `vistoria_id` int(11) NOT NULL,
  `responsavel_id` int(11) DEFAULT NULL,
  `status` enum('Aguardando_Parceiros','Em_Cotacao','Cotacoes_Recebidas','Aprovada','Rejeitada') DEFAULT 'Aguardando_Parceiros',
  `valor_aprovado` decimal(10,2) DEFAULT NULL,
  `mapa_cotacao` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_aprovacao` datetime DEFAULT NULL,
  `parceiro_aprovado_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cotacoes`
--

INSERT INTO `cotacoes` (`id`, `vistoria_id`, `responsavel_id`, `status`, `valor_aprovado`, `mapa_cotacao`, `data_criacao`, `data_aprovacao`, `parceiro_aprovado_id`) VALUES
(1, 1, NULL, 'Aguardando_Parceiros', NULL, NULL, '2025-06-18 18:03:06', NULL, NULL),
(4, 4, 1, 'Aprovada', 8000.00, '{\"cliente\":\"Teste 4\",\"endereco\":\"Rua eee\",\"tipo_imovel\":\"Apartamento\",\"data_vistoria\":\"2025-06-25 10:32:00\",\"arquivo_lista\":\"lista_seguro_4_20250624153250_7b964e7d.xlsx\",\"observacoes\":\"dsf\\\\sfdaz\"}', '2025-06-24 13:38:45', '2025-06-24 13:03:05', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cotacoes_parceiros`
--

CREATE TABLE `cotacoes_parceiros` (
  `id` int(11) NOT NULL,
  `cotacao_id` int(11) NOT NULL,
  `parceiro_id` int(11) NOT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `prazo_dias` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data_resposta` timestamp NULL DEFAULT NULL,
  `ip_resposta` varchar(45) DEFAULT NULL,
  `token_acesso` text DEFAULT NULL,
  `selecionado` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cotacoes_parceiros`
--

INSERT INTO `cotacoes_parceiros` (`id`, `cotacao_id`, `parceiro_id`, `valor`, `prazo_dias`, `observacoes`, `data_resposta`, `ip_resposta`, `token_acesso`, `selecionado`) VALUES
(1, 4, 1, 12000.00, 4, 'aaaaaaaaaaaa', '2025-06-24 15:46:45', '::1', '76e4550492f6ce29defa3b301b5437dd99f8060a83055088352989afa5a454fe', 0),
(2, 4, 2, 20000.00, 1, '', '2025-06-24 15:45:20', '::1', 'a4b07bac871ef5dff7dfb6d151c531698b2060d1f908379ea9d7279a2a450d6d', 0),
(3, 4, 3, 8000.00, 5, 'teste', '2025-06-24 15:47:32', '::1', '4f0662a4cd82f675f0698798d875158797d2639e98d61435e970607baa01fe05', 1),
(4, 4, 4, 10000.00, 3, '', '2025-06-24 15:41:27', '::1', '02d7ca8238e505c2ada8056e7a990a388c78e8cef12a9e11f2e5d07fe3afd742', 0),
(5, 4, 5, 15000.00, 3, 'teste', '2025-06-24 15:51:16', '::1', 'b67dfa2631a1e7be5a491cdc9cc808c8021ec60832f7438a90ce34ee7eb45d3b', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `mudanca_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(500) NOT NULL,
  `enviado_por` int(11) NOT NULL,
  `status` enum('Pendente','Enviado','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `observacoes` text DEFAULT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `mudanca_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nota_geral` int(11) DEFAULT NULL CHECK (`nota_geral` >= 1 and `nota_geral` <= 5),
  `nota_embalagem` int(11) DEFAULT NULL CHECK (`nota_embalagem` >= 1 and `nota_embalagem` <= 5),
  `nota_transporte` int(11) DEFAULT NULL CHECK (`nota_transporte` >= 1 and `nota_transporte` <= 5),
  `nota_entrega` int(11) DEFAULT NULL CHECK (`nota_entrega` >= 1 and `nota_entrega` <= 5),
  `comentarios` text DEFAULT NULL,
  `melhorias` text DEFAULT NULL,
  `recomendaria` tinyint(1) DEFAULT NULL,
  `data_feedback` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_status`
--

CREATE TABLE `historico_status` (
  `id` int(11) NOT NULL,
  `tabela` varchar(50) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `status_anterior` varchar(50) DEFAULT NULL,
  `status_novo` varchar(50) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data_mudanca` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `historico_status`
--

INSERT INTO `historico_status` (`id`, `tabela`, `registro_id`, `status_anterior`, `status_novo`, `usuario_id`, `observacoes`, `data_mudanca`) VALUES
(1, 'vistorias', 1, NULL, 'Pendente', 2, 'Vistoria criada', '2025-06-18 18:01:52'),
(2, 'vistorias', 1, 'Pendente', 'Concluída', 2, 'Status alterado manualmente', '2025-06-18 18:02:15'),
(3, 'vistorias', 1, 'sem_arquivo', 'arquivo_anexado', 2, 'Upload de lista de seguro: Lista Inscritos VIII ABEMMI SUMMIT SP 2025 - Patrocinadores.xlsx', '2025-06-18 18:02:50'),
(4, 'vistorias', 1, 'Concluída', 'Enviada_Cotacao', 2, 'Vistoria enviada para cotação', '2025-06-18 18:03:06'),
(5, 'usuarios', 1, 'login_failed', 'login_failed', NULL, 'Tentativa de login falhada - IP: ::1', '2025-06-23 17:56:32'),
(6, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-23 18:22:39'),
(7, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-23 18:29:07'),
(8, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 12:43:42'),
(10, 'usuarios', 1, 'logado', 'deslogado', 1, 'Logout realizado', '2025-06-24 13:15:30'),
(11, 'usuarios', 2, 'logout', 'login', 2, 'Login realizado - IP: ::1', '2025-06-24 13:15:41'),
(12, 'usuarios', 2, 'logado', 'deslogado', 2, 'Logout realizado', '2025-06-24 13:23:11'),
(13, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 13:23:56'),
(14, 'usuarios', 1, 'logado', 'deslogado', 1, 'Logout realizado', '2025-06-24 13:27:22'),
(15, 'usuarios', 3, 'logout', 'login', 3, 'Login realizado - IP: ::1', '2025-06-24 13:27:31'),
(16, 'usuarios', 3, 'logado', 'deslogado', 3, 'Logout realizado', '2025-06-24 13:27:43'),
(17, 'usuarios', 4, 'logout', 'login', 4, 'Login realizado - IP: ::1', '2025-06-24 13:27:57'),
(18, 'usuarios', 4, 'logado', 'deslogado', 4, 'Logout realizado', '2025-06-24 13:28:07'),
(19, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 13:28:22'),
(21, 'usuarios', 1, 'logado', 'deslogado', 1, 'Logout realizado', '2025-06-24 13:31:13'),
(22, 'usuarios', 2, 'logout', 'login', 2, 'Login realizado - IP: ::1', '2025-06-24 13:31:26'),
(23, 'vistorias', 4, NULL, 'Concluída', 2, 'Vistoria criada', '2025-06-24 13:32:08'),
(24, 'vistorias', 4, 'sem_arquivo', 'arquivo_anexado', 2, 'Upload de lista de seguro: Rate Sheet.xlsx (9.86 KB)', '2025-06-24 13:32:50'),
(25, 'usuarios', 2, 'logado', 'deslogado', 2, 'Logout realizado', '2025-06-24 13:33:12'),
(26, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 13:33:28'),
(27, 'usuarios', 3, 'logout', 'login', 3, 'Login realizado - IP: ::1', '2025-06-24 13:33:55'),
(28, 'vistorias', 4, 'Concluída', 'Enviada_Cotacao', 1, 'Cotação enviada para 5 parceiros', '2025-06-24 13:38:45'),
(29, 'usuarios', 3, 'logado', 'deslogado', 3, 'Logout realizado', '2025-06-24 13:40:25'),
(30, 'usuarios', 1, 'logado', 'deslogado', 1, 'Logout realizado', '2025-06-24 15:44:13'),
(31, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 15:44:29'),
(32, 'vistorias', 4, 'Enviada_Cotacao', 'Cotacao_Aprovada', 1, 'Cotação aprovada - Valor: R$ 8.000,00', '2025-06-24 16:03:05'),
(33, 'usuarios', 1, 'logado', 'deslogado', 1, 'Logout realizado', '2025-06-24 16:04:07'),
(34, 'usuarios', 1, 'logout', 'login', 1, 'Login realizado - IP: ::1', '2025-06-24 16:04:31'),
(35, 'propostas', 5, 'Criada', 'Enviada', 1, 'Proposta enviada por email para: gui.ferrarez.ferreira@gmail.com', '2025-06-24 16:17:36'),
(36, 'vistorias', 4, 'Cotacao_Aprovada', 'Proposta_Enviada', 1, 'Proposta enviada ao cliente', '2025-06-24 16:17:36'),
(37, 'propostas', 5, NULL, 'Criada', 1, 'Proposta criada', '2025-06-24 16:17:36'),
(41, 'propostas', 5, 'Enviada', 'Aceita', NULL, 'Cliente aceitou via portal', '2025-06-24 19:39:41'),
(42, 'vistorias', 4, 'Proposta_Enviada', 'Proposta_Aceita', NULL, 'Proposta aceita pelo cliente', '2025-06-24 19:39:41'),
(43, 'mudancas', 11, NULL, 'Aguardando_Documentacao', NULL, 'Mudança criada após aceite da proposta', '2025-06-24 19:39:41'),
(44, 'usuarios', 5, 'logout', 'login', 5, 'Login realizado - IP: ::1', '2025-06-24 19:41:40');

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_email`
--

CREATE TABLE `logs_email` (
  `id` int(11) NOT NULL,
  `destinatario` varchar(100) NOT NULL,
  `assunto` varchar(200) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `status` enum('Enviado','Erro') NOT NULL,
  `erro` text DEFAULT NULL,
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `logs_email`
--

INSERT INTO `logs_email` (`id`, `destinatario`, `assunto`, `tipo`, `status`, `erro`, `data_envio`) VALUES
(1, 'contato@transportadoraabc.com', 'Nova Solicitação de Cotação - Cliente: Teste 4', 'cotacao_parceiro', 'Enviado', NULL, '2025-06-24 13:38:45'),
(2, 'comercial@mudancasexpress.com', 'Nova Solicitação de Cotação - Cliente: Teste 4', 'cotacao_parceiro', 'Enviado', NULL, '2025-06-24 13:38:45'),
(3, 'orcamento@logisticaprime.com', 'Nova Solicitação de Cotação - Cliente: Teste 4', 'cotacao_parceiro', 'Enviado', NULL, '2025-06-24 13:38:45'),
(4, 'vendas@fastmudancas.com', 'Nova Solicitação de Cotação - Cliente: Teste 4', 'cotacao_parceiro', 'Enviado', NULL, '2025-06-24 13:38:45'),
(5, 'contato@transporteseguro.com', 'Nova Solicitação de Cotação - Cliente: Teste 4', 'cotacao_parceiro', 'Enviado', NULL, '2025-06-24 13:38:45'),
(10, 'gui.ferrarez.ferreira@gmail.com', 'Proposta de Mudança - Teste 4', 'proposta', 'Erro', NULL, '2025-06-24 16:17:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `mudancas`
--

CREATE TABLE `mudancas` (
  `id` int(11) NOT NULL,
  `vistoria_id` int(11) NOT NULL,
  `proposta_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `coordenador_id` int(11) DEFAULT NULL,
  `status` enum('Aguardando_Documentos','Documentos_Recebidos','Agendada','Em_Embalagem','Em_Transporte','Entregue','Finalizada') DEFAULT 'Aguardando_Documentos',
  `data_embalagem` date DEFAULT NULL,
  `data_retirada` date DEFAULT NULL,
  `data_entrega_prevista` date DEFAULT NULL,
  `data_entrega_real` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `valor_total` varchar(255) DEFAULT NULL,
  `endereco_origem` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mudancas`
--

INSERT INTO `mudancas` (`id`, `vistoria_id`, `proposta_id`, `cliente_id`, `coordenador_id`, `status`, `data_embalagem`, `data_retirada`, `data_entrega_prevista`, `data_entrega_real`, `observacoes`, `data_criacao`, `data_atualizacao`, `valor_total`, `endereco_origem`) VALUES
(11, 4, 5, 15, NULL, '', NULL, NULL, NULL, NULL, NULL, '2025-06-24 19:39:41', '2025-06-24 19:39:41', '8000.00', 'Rua eee');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensagem` text DEFAULT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_leitura` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `usuario_id`, `tipo`, `titulo`, `mensagem`, `lida`, `data_criacao`, `data_leitura`) VALUES
(1, 3, 'nova_cotacao', 'Nova Vistoria para Cotação', 'A vistoria #1 do cliente teste foi enviada para cotação.', 0, '2025-06-18 18:03:06', NULL),
(2, 2, 'nova_vistoria', 'Nova Vistoria Agendada', 'Uma nova vistoria foi agendada para você: Guilherme - Rua abc', 0, '2025-06-24 13:15:09', NULL),
(3, 2, 'nova_vistoria', 'Nova Vistoria Agendada', 'Uma nova vistoria foi agendada para você: teste 2 - Rua abd1', 0, '2025-06-24 13:30:33', NULL),
(4, 2, 'vistoria_excluida', 'Vistoria Excluída', 'A vistoria do cliente Guilherme foi excluída pelo gestor', 0, '2025-06-24 13:30:45', NULL),
(5, 2, 'vistoria_excluida', 'Vistoria Excluída', 'A vistoria do cliente teste 2 foi excluída pelo gestor', 0, '2025-06-24 13:30:48', NULL),
(6, 2, 'cotacao_enviada', 'Cotação Enviada', 'A vistoria do cliente Teste 4 foi enviada para cotação', 0, '2025-06-24 13:38:45', NULL),
(7, 1, 'cotacoes_completas', 'Todas as Cotações Recebidas', 'Todos os parceiros responderam à cotação #4', 0, '2025-06-24 15:51:16', NULL),
(8, 2, 'cotacao_aprovada', 'Cotação Aprovada', 'Cotação do cliente Teste 4 aprovada. Valor: R$ 8.000,00', 0, '2025-06-24 16:03:05', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `parceiros`
--

CREATE TABLE `parceiros` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `token_acesso` varchar(64) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `parceiros`
--

INSERT INTO `parceiros` (`id`, `nome`, `email`, `telefone`, `ativo`, `token_acesso`, `data_criacao`) VALUES
(1, 'Transportadora ABC', 'contato@transportadoraabc.com', '(11) 1234-5678', 1, NULL, '2025-06-18 17:51:15'),
(2, 'Mudanças Express', 'comercial@mudancasexpress.com', '(11) 2345-6789', 1, NULL, '2025-06-18 17:51:15'),
(3, 'Logística Prime', 'orcamento@logisticaprime.com', '(11) 3456-7890', 1, NULL, '2025-06-18 17:51:15'),
(4, 'Fast Mudanças', 'vendas@fastmudancas.com', '(11) 4567-8901', 1, NULL, '2025-06-18 17:51:15'),
(5, 'Transporte Seguro', 'contato@transporteseguro.com', '(11) 5678-9012', 1, NULL, '2025-06-18 17:51:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas`
--

CREATE TABLE `propostas` (
  `id` int(11) NOT NULL,
  `vistoria_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `descricao_servicos` text DEFAULT NULL,
  `validade_dias` int(11) DEFAULT 30,
  `status` enum('Criada','Enviada','Aceita','Rejeitada','Expirada') DEFAULT 'Criada',
  `token_aceite` varchar(64) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_envio` datetime DEFAULT NULL,
  `data_aceite` datetime DEFAULT NULL,
  `ip_aceite` varchar(45) DEFAULT NULL,
  `criado_por` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas`
--

INSERT INTO `propostas` (`id`, `vistoria_id`, `cliente_id`, `valor_total`, `descricao_servicos`, `validade_dias`, `status`, `token_aceite`, `data_criacao`, `data_envio`, `data_aceite`, `ip_aceite`, `criado_por`) VALUES
(5, 4, 0, 8000.00, 'Serviço de mudança residencial conforme vistoria realizada.\n            \nInclui:\n- Embalagem profissional de todos os itens\n- Desmontagem e montagem de móveis\n- Transporte seguro com caminhão apropriado\n- Seguro durante o transporte\n- Equipe especializada\n\nEndereço: Rua eee\nTipo de imóvel: Apartamento', 30, 'Aceita', 'b7a872dd301c2a9bab8699404ad4045566da623aebf3a5bb7c442347c20a440e', '2025-06-24 16:17:34', '2025-06-24 13:17:36', '2025-06-24 16:39:41', NULL, '1');

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_documentos`
--

CREATE TABLE `solicitacoes_documentos` (
  `id` int(11) NOT NULL,
  `mudanca_id` int(11) NOT NULL,
  `tipo_documento` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `obrigatorio` tinyint(1) DEFAULT 1,
  `status` enum('Pendente','Recebido') DEFAULT 'Pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_recebimento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `tipo` enum('gestor','vendedor','cotador','coordenador','cliente') NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `telefone`, `tipo`, `ativo`, `data_criacao`, `ultimo_acesso`) VALUES
(1, 'Admin Sistema', 'admin@sistema.com', 'admin123', NULL, 'gestor', 1, '2025-06-18 17:51:15', '2025-06-24 13:04:31'),
(2, 'João Vendedor', 'vendedor@sistema.com', 'vendedor123', NULL, 'vendedor', 1, '2025-06-18 17:51:15', '2025-06-24 10:33:12'),
(3, 'Maria Cotadora', 'cotador@sistema.com', 'cotador123', NULL, 'cotador', 1, '2025-06-18 17:51:15', '2025-06-24 10:40:25'),
(4, 'Pedro Coordenador', 'coordenador@sistema.com', 'coordenador123', NULL, 'coordenador', 1, '2025-06-18 17:51:15', '2025-06-24 10:28:07'),
(5, 'Teste 4', 'gui.ferrarez.ferreira@gmail.com', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', NULL, 'cliente', 1, '2025-06-24 19:23:57', '2025-06-24 16:41:40');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vistorias`
--

CREATE TABLE `vistorias` (
  `id` int(11) NOT NULL,
  `cliente` varchar(100) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vendedor` varchar(100) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `endereco` text NOT NULL,
  `tipo_imovel` varchar(50) NOT NULL,
  `data_vistoria` datetime NOT NULL,
  `status` enum('Pendente','Concluída','Enviada_Cotacao','Cotacao_Aprovada','Proposta_Enviada','Proposta_Aceita','Em_Andamento','Finalizada','Cancelada') DEFAULT 'Pendente',
  `observacoes` text DEFAULT NULL,
  `arquivo_lista_seguro` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `valor_aprovado` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vistorias`
--

INSERT INTO `vistorias` (`id`, `cliente`, `cpf`, `telefone`, `email`, `vendedor`, `vendedor_id`, `endereco`, `tipo_imovel`, `data_vistoria`, `status`, `observacoes`, `arquivo_lista_seguro`, `data_criacao`, `data_atualizacao`, `valor_aprovado`) VALUES
(1, 'teste', '111.111.111-11', '(11) 11111-1111', 'teste@teste.com', 'João Vendedor', 2, 'rua 1234', 'Apartamento', '2025-06-23 00:00:00', 'Enviada_Cotacao', 'zopv\\kjdfoipk', 'lista_seguro_1_1750269770.xlsx', '2025-06-18 18:01:52', '2025-06-18 18:03:06', NULL),
(4, 'Teste 4', '444.444.444-44', '(44) 44444-4444', 'gui.ferrarez.ferreira@gmail.com', 'João Vendedor', 2, 'Rua eee', 'Apartamento', '2025-06-25 10:32:00', 'Proposta_Aceita', 'dsf\\sfdaz', 'lista_seguro_4_20250624153250_7b964e7d.xlsx', '2025-06-24 13:32:08', '2025-06-24 19:39:41', 8000.00);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cpf` (`cpf`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_cpf` (`cpf`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_vistoria` (`vistoria_id`);

--
-- Índices de tabela `cotacoes`
--
ALTER TABLE `cotacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vistoria_id` (`vistoria_id`),
  ADD KEY `responsavel_id` (`responsavel_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `cotacoes_parceiros`
--
ALTER TABLE `cotacoes_parceiros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cotacao_parceiro` (`cotacao_id`,`parceiro_id`),
  ADD KEY `parceiro_id` (`parceiro_id`);

--
-- Índices de tabela `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mudanca_id` (`mudanca_id`),
  ADD KEY `enviado_por` (`enviado_por`);

--
-- Índices de tabela `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mudanca_id` (`mudanca_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `historico_status`
--
ALTER TABLE `historico_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_tabela_registro` (`tabela`,`registro_id`);

--
-- Índices de tabela `logs_email`
--
ALTER TABLE `logs_email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `mudancas`
--
ALTER TABLE `mudancas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vistoria_id` (`vistoria_id`),
  ADD KEY `proposta_id` (`proposta_id`),
  ADD KEY `coordenador_id` (`coordenador_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_lida` (`usuario_id`,`lida`);

--
-- Índices de tabela `parceiros`
--
ALTER TABLE `parceiros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `propostas`
--
ALTER TABLE `propostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vistoria_id` (`vistoria_id`);

--
-- Índices de tabela `solicitacoes_documentos`
--
ALTER TABLE `solicitacoes_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mudanca_id` (`mudanca_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_email` (`email`);

--
-- Índices de tabela `vistorias`
--
ALTER TABLE `vistorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_cpf` (`cpf`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `cotacoes`
--
ALTER TABLE `cotacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `cotacoes_parceiros`
--
ALTER TABLE `cotacoes_parceiros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_status`
--
ALTER TABLE `historico_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `logs_email`
--
ALTER TABLE `logs_email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `mudancas`
--
ALTER TABLE `mudancas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `parceiros`
--
ALTER TABLE `parceiros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `propostas`
--
ALTER TABLE `propostas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `solicitacoes_documentos`
--
ALTER TABLE `solicitacoes_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `vistorias`
--
ALTER TABLE `vistorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`vistoria_id`) REFERENCES `vistorias` (`id`);

--
-- Restrições para tabelas `cotacoes`
--
ALTER TABLE `cotacoes`
  ADD CONSTRAINT `cotacoes_ibfk_1` FOREIGN KEY (`vistoria_id`) REFERENCES `vistorias` (`id`),
  ADD CONSTRAINT `cotacoes_ibfk_2` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `cotacoes_parceiros`
--
ALTER TABLE `cotacoes_parceiros`
  ADD CONSTRAINT `cotacoes_parceiros_ibfk_1` FOREIGN KEY (`cotacao_id`) REFERENCES `cotacoes` (`id`),
  ADD CONSTRAINT `cotacoes_parceiros_ibfk_2` FOREIGN KEY (`parceiro_id`) REFERENCES `parceiros` (`id`);

--
-- Restrições para tabelas `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`mudanca_id`) REFERENCES `mudancas` (`id`),
  ADD CONSTRAINT `documentos_ibfk_2` FOREIGN KEY (`enviado_por`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`mudanca_id`) REFERENCES `mudancas` (`id`),
  ADD CONSTRAINT `feedbacks_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `historico_status`
--
ALTER TABLE `historico_status`
  ADD CONSTRAINT `historico_status_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `mudancas`
--
ALTER TABLE `mudancas`
  ADD CONSTRAINT `mudancas_ibfk_1` FOREIGN KEY (`vistoria_id`) REFERENCES `vistorias` (`id`),
  ADD CONSTRAINT `mudancas_ibfk_2` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`id`),
  ADD CONSTRAINT `mudancas_ibfk_3` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `mudancas_ibfk_4` FOREIGN KEY (`coordenador_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `propostas`
--
ALTER TABLE `propostas`
  ADD CONSTRAINT `propostas_ibfk_1` FOREIGN KEY (`vistoria_id`) REFERENCES `vistorias` (`id`);

--
-- Restrições para tabelas `solicitacoes_documentos`
--
ALTER TABLE `solicitacoes_documentos`
  ADD CONSTRAINT `solicitacoes_documentos_ibfk_1` FOREIGN KEY (`mudanca_id`) REFERENCES `mudancas` (`id`);

--
-- Restrições para tabelas `vistorias`
--
ALTER TABLE `vistorias`
  ADD CONSTRAINT `vistorias_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
