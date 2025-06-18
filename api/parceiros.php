<?php
// api/parceiros.php - API de Gerenciamento de Parceiros
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

// Apenas gestores e cotadores podem gerenciar parceiros
if (!isGestor() && $_SESSION['usuario_tipo'] !== 'cotador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Apenas gestores e cotadores podem gerenciar parceiros.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Buscar parceiro específico
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       COUNT(DISTINCT cp.id) as total_cotacoes,
                       COUNT(DISTINCT CASE WHEN c.parceiro_aprovado_id = p.id THEN c.id END) as cotacoes_ganhas,
                       AVG(cp.valor) as valor_medio,
                       AVG(cp.prazo_dias) as prazo_medio
                FROM parceiros p
                LEFT JOIN cotacoes_parceiros cp ON p.id = cp.parceiro_id AND cp.valor IS NOT NULL
                LEFT JOIN cotacoes c ON cp.cotacao_id = c.id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$_GET['id']]);
            $parceiro = $stmt->fetch();
            
            if ($parceiro) {
                // Buscar histórico de cotações
                $stmt = $pdo->prepare("
                    SELECT cp.*, c.data_criacao as cotacao_data, v.cliente, v.endereco,
                           CASE WHEN c.parceiro_aprovado_id = cp.parceiro_id THEN 1 ELSE 0 END as ganhou
                    FROM cotacoes_parceiros cp
                    JOIN cotacoes c ON cp.cotacao_id = c.id
                    JOIN vistorias v ON c.vistoria_id = v.id
                    WHERE cp.parceiro_id = ? AND cp.valor IS NOT NULL
                    ORDER BY cp.data_resposta DESC
                    LIMIT 20
                ");
                $stmt->execute([$_GET['id']]);
                $parceiro['historico_cotacoes'] = $stmt->fetchAll();
                
                // Calcular taxa de sucesso
                $parceiro['taxa_sucesso'] = $parceiro['total_cotacoes'] > 0 
                    ? round(($parceiro['cotacoes_ganhas'] / $parceiro['total_cotacoes']) * 100, 2) 
                    : 0;
            }
            
            echo json_encode($parceiro ?: null);
            
        } else if (isset($_GET['ranking'])) {
            // Ranking de parceiros
            $periodo = $_GET['periodo'] ?? '30'; // dias
            $stmt = $pdo->prepare("
                SELECT p.*,
                       COUNT(DISTINCT cp.id) as total_respostas,
                       COUNT(DISTINCT CASE WHEN c.parceiro_aprovado_id = p.id THEN c.id END) as cotacoes_ganhas,
                       AVG(cp.valor) as valor_medio,
                       MIN(cp.valor) as menor_valor,
                       AVG(cp.prazo_dias) as prazo_medio,
                       AVG(TIMESTAMPDIFF(HOUR, c.data_criacao, cp.data_resposta)) as tempo_resposta_medio
                FROM parceiros p
                LEFT JOIN cotacoes_parceiros cp ON p.id = cp.parceiro_id 
                    AND cp.valor IS NOT NULL 
                    AND cp.data_resposta >= DATE_SUB(NOW(), INTERVAL ? DAY)
                LEFT JOIN cotacoes c ON cp.cotacao_id = c.id
                WHERE p.ativo = 1
                GROUP BY p.id
                HAVING total_respostas > 0
                ORDER BY cotacoes_ganhas DESC, total_respostas DESC
            ");
            $stmt->execute([$periodo]);
            $ranking = $stmt->fetchAll();
            
            echo json_encode($ranking);
            
        } else if (isset($_GET['performance'])) {
            // Dashboard de performance
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT p.id) as total_parceiros,
                    COUNT(DISTINCT CASE WHEN p.ativo = 1 THEN p.id END) as parceiros_ativos,
                    COUNT(DISTINCT cp.id) as total_cotacoes_enviadas,
                    COUNT(DISTINCT CASE WHEN cp.valor IS NOT NULL THEN cp.id END) as total_respostas,
                    AVG(CASE WHEN cp.valor IS NOT NULL THEN 1 ELSE 0 END) * 100 as taxa_resposta,
                    AVG(cp.valor) as valor_medio_geral,
                    AVG(TIMESTAMPDIFF(HOUR, c.data_criacao, cp.data_resposta)) as tempo_resposta_medio
                FROM parceiros p
                LEFT JOIN cotacoes_parceiros cp ON p.id = cp.parceiro_id
                LEFT JOIN cotacoes c ON cp.cotacao_id = c.id
                WHERE cp.data_resposta >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $performance = $stmt->fetch();
            
            echo json_encode($performance);
            
        } else {
            // Listar todos os parceiros
            $ativos = $_GET['ativos'] ?? null;
            $especialidade = $_GET['especialidade'] ?? null;
            $cidade = $_GET['cidade'] ?? null;
            
            $sql = "SELECT p.*,
                           COUNT(DISTINCT cp.id) as total_cotacoes_recebidas,
                           COUNT(DISTINCT CASE WHEN cp.valor IS NOT NULL THEN cp.id END) as total_respostas
                    FROM parceiros p
                    LEFT JOIN cotacoes_parceiros cp ON p.id = cp.parceiro_id
                    WHERE 1=1";
            
            $params = [];
            
            if ($ativos !== null) {
                $sql .= " AND p.ativo = ?";
                $params[] = $ativos;
            }
            
            if ($especialidade) {
                $sql .= " AND p.especialidades LIKE ?";
                $params[] = "%$especialidade%";
            }
            
            if ($cidade) {
                $sql .= " AND p.cidade = ?";
                $params[] = $cidade;
            }
            
            $sql .= " GROUP BY p.id ORDER BY p.nome";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $parceiros = $stmt->fetchAll();
            
            echo json_encode($parceiros);
        }
        break;
        
    case 'POST':
        // Criar novo parceiro
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validações
        if (!isset($data['nome']) || !isset($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome e email são obrigatórios']);
            exit;
        }
        
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM parceiros WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email já cadastrado']);
            exit;
        }
        
        // Verificar CNPJ se fornecido
        if (!empty($data['cnpj'])) {
            $stmt = $pdo->prepare("SELECT id FROM parceiros WHERE cnpj = ?");
            $stmt->execute([$data['cnpj']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'CNPJ já cadastrado']);
                exit;
            }
        }
        
        try {
            // Gerar token de acesso
            $token = bin2hex(random_bytes(32));
            
            $sql = "INSERT INTO parceiros (
                        nome, razao_social, cnpj, email, telefone, telefone_secundario,
                        endereco, cidade, estado, cep, contato_principal,
                        especialidades, capacidade_mensal, ativo, token_acesso
                    ) VALUES (
                        :nome, :razao_social, :cnpj, :email, :telefone, :telefone_secundario,
                        :endereco, :cidade, :estado, :cep, :contato_principal,
                        :especialidades, :capacidade_mensal, :ativo, :token
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $data['nome'],
                ':razao_social' => $data['razao_social'] ?? null,
                ':cnpj' => $data['cnpj'] ?? null,
                ':email' => $data['email'],
                ':telefone' => $data['telefone'] ?? null,
                ':telefone_secundario' => $data['telefone_secundario'] ?? null,
                ':endereco' => $data['endereco'] ?? null,
                ':cidade' => $data['cidade'] ?? null,
                ':estado' => $data['estado'] ?? null,
                ':cep' => $data['cep'] ?? null,
                ':contato_principal' => $data['contato_principal'] ?? null,
                ':especialidades' => $data['especialidades'] ?? null,
                ':capacidade_mensal' => $data['capacidade_mensal'] ?? null,
                ':ativo' => $data['ativo'] ?? 1,
                ':token' => $token
            ]);
            
            $parceiroId = $pdo->lastInsertId();
            
            // Enviar email de boas-vindas
            $assunto = "Bem-vindo ao Sistema de Cotações";
            $mensagem = "
                Olá {$data['nome']},
                
                Seu cadastro como parceiro foi realizado com sucesso!
                
                Você receberá notificações por email sempre que houver novas oportunidades de cotação.
                
                Dados de acesso:
                Email: {$data['email']}
                Token de acesso: $token
                
                Guarde este token com segurança, ele será necessário para acessar o sistema.
                
                Atenciosamente,
                Equipe do Sistema de Mudanças
            ";
            
            @mail($data['email'], $assunto, $mensagem);
            
            echo json_encode([
                'success' => true,
                'id' => $parceiroId,
                'message' => 'Parceiro criado com sucesso'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar parceiro: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        // Atualizar parceiro
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do parceiro não fornecido']);
            exit;
        }
        
        $parceiroId = $_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar se parceiro existe
        $stmt = $pdo->prepare("SELECT * FROM parceiros WHERE id = ?");
        $stmt->execute([$parceiroId]);
        $parceiroAtual = $stmt->fetch();
        
        if (!$parceiroAtual) {
            http_response_code(404);
            echo json_encode(['error' => 'Parceiro não encontrado']);
            exit;
        }
        
        // Verificar email único
        if (isset($data['email']) && $data['email'] !== $parceiroAtual['email']) {
            $stmt = $pdo->prepare("SELECT id FROM parceiros WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $parceiroId]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Email já está em uso por outro parceiro']);
                exit;
            }
        }
        
        try {
            $campos = [];
            $valores = [];
            
            // Campos que podem ser atualizados
            $camposPermitidos = [
                'nome', 'razao_social', 'cnpj', 'email', 'telefone', 'telefone_secundario',
                'endereco', 'cidade', 'estado', 'cep', 'contato_principal',
                'especialidades', 'capacidade_mensal', 'ativo'
            ];
            
            foreach ($camposPermitidos as $campo) {
                if (isset($data[$campo])) {
                    $campos[] = "$campo = :$campo";
                    $valores[":$campo"] = $data[$campo];
                }
            }
            
            if (empty($campos)) {
                http_response_code(400);
                echo json_encode(['error' => 'Nenhum campo para atualizar']);
                exit;
            }
            
            $sql = "UPDATE parceiros SET " . implode(', ', $campos) . " WHERE id = :id";
            $valores[':id'] = $parceiroId;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
            
            // Se foi desativado, notificar
            if (isset($data['ativo']) && $data['ativo'] == 0 && $parceiroAtual['ativo'] == 1) {
                $assunto = "Cadastro Desativado";
                $mensagem = "Seu cadastro como parceiro foi temporariamente desativado. Entre em contato conosco para mais informações.";
                @mail($parceiroAtual['email'], $assunto, $mensagem);
            }
            
            echo json_encode(['success' => true, 'message' => 'Parceiro atualizado com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao atualizar parceiro: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Desativar parceiro (soft delete)
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do parceiro não fornecido']);
            exit;
        }
        
        try {
            // Verificar se tem cotações em andamento
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as pendentes
                FROM cotacoes_parceiros cp
                JOIN cotacoes c ON cp.cotacao_id = c.id
                WHERE cp.parceiro_id = ? 
                  AND c.status IN ('Aguardando_Parceiros', 'Em_Cotacao')
                  AND cp.valor IS NULL
            ");
            $stmt->execute([$_GET['id']]);
            $result = $stmt->fetch();
            
            if ($result['pendentes'] > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Parceiro possui cotações pendentes. Não é possível desativar.']);
                exit;
            }
            
            // Desativar parceiro
            $stmt = $pdo->prepare("UPDATE parceiros SET ativo = 0 WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Parceiro desativado com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao desativar parceiro: ' . $e->getMessage()]);
        }
        break;
}
?>