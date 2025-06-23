<?php
// api/vendedores.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

// Apenas gestores podem acessar a API de vendedores
if(!isGestor()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado. Apenas gestores podem gerenciar vendedores.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Classe para gerenciar vendedores
class VendedorManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Listar vendedores com estatísticas
    public function listar($filtros = []) {
        $where = ["tipo = 'vendedor'"];
        $params = [];
        
        // Filtrar por status
        if (isset($filtros['ativos'])) {
            $where[] = 'ativo = 1';
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query com estatísticas
        $sql = "
            SELECT 
                u.id, 
                u.nome, 
                u.email, 
                u.telefone, 
                u.ativo, 
                u.tipo, 
                u.ultimo_acesso,
                u.data_criacao,
                COUNT(DISTINCT v.id) as total_vistorias,
                COUNT(DISTINCT CASE WHEN v.status = 'Concluída' THEN v.id END) as vistorias_concluidas,
                COUNT(DISTINCT CASE WHEN v.status = 'Pendente' THEN v.id END) as vistorias_pendentes,
                COUNT(DISTINCT CASE WHEN v.data_vistoria >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN v.id END) as vistorias_mes
            FROM usuarios u
            LEFT JOIN vistorias v ON u.nome = v.vendedor
            WHERE $whereClause
            GROUP BY u.id
            ORDER BY u.nome
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    // Buscar vendedor específico
    public function buscar($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.*,
                COUNT(DISTINCT v.id) as total_vistorias,
                COUNT(DISTINCT CASE WHEN v.status = 'Concluída' THEN v.id END) as vistorias_concluidas
            FROM usuarios u
            LEFT JOIN vistorias v ON u.nome = v.vendedor
            WHERE u.id = ? AND u.tipo = 'vendedor'
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Criar novo vendedor
    public function criar($dados) {
        // Validar dados obrigatórios
        $camposObrigatorios = ['nome', 'email'];
        foreach ($camposObrigatorios as $campo) {
            if (empty($dados[$campo])) {
                throw new Exception("Campo obrigatório ausente: $campo");
            }
        }
        
        // Validar email
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        // Verificar se email já existe
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$dados['email']]);
        if($stmt->fetch()) {
            throw new Exception('Email já cadastrado');
        }
        
        // Senha padrão ou fornecida
        $senha = $dados['senha'] ?? 'vendedor123';
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        
        $this->pdo->beginTransaction();
        
        try {
            $sql = "INSERT INTO usuarios (nome, email, senha, telefone, tipo, ativo) 
                    VALUES (:nome, :email, :senha, :telefone, 'vendedor', :ativo)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome' => trim($dados['nome']),
                ':email' => strtolower(trim($dados['email'])),
                ':senha' => $senhaHash,
                ':telefone' => $dados['telefone'] ?? null,
                ':ativo' => $dados['ativo'] ?? 1
            ]);
            
            $vendedorId = $this->pdo->lastInsertId();
            
            // Registrar no histórico
            $this->registrarHistorico(
                'usuarios',
                $vendedorId,
                null,
                'criado',
                'Vendedor criado'
            );
            
            // Enviar email de boas-vindas (se implementado)
            if (isset($dados['enviar_email']) && $dados['enviar_email']) {
                $this->enviarEmailBoasVindas($dados['email'], $dados['nome'], $senha);
            }
            
            $this->pdo->commit();
            
            return [
                'id' => $vendedorId,
                'nome' => $dados['nome'],
                'email' => $dados['email']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Atualizar vendedor
    public function atualizar($id, $dados) {
        $vendedor = $this->buscar($id);
        if (!$vendedor) {
            throw new Exception('Vendedor não encontrado');
        }
        
        // Validar email se fornecido
        if (!empty($dados['email']) && $dados['email'] !== $vendedor['email']) {
            if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Verificar se novo email já existe
            $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$dados['email'], $id]);
            if($stmt->fetch()) {
                throw new Exception('Email já cadastrado por outro usuário');
            }
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Se houver nova senha
            if(!empty($dados['senha'])) {
                $sql = "UPDATE usuarios SET 
                        nome = :nome,
                        email = :email,
                        senha = :senha,
                        telefone = :telefone,
                        ativo = :ativo
                        WHERE id = :id AND tipo = 'vendedor'";
                
                $params = [
                    ':nome' => trim($dados['nome'] ?? $vendedor['nome']),
                    ':email' => strtolower(trim($dados['email'] ?? $vendedor['email'])),
                    ':senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
                    ':telefone' => $dados['telefone'] ?? $vendedor['telefone'],
                    ':ativo' => $dados['ativo'] ?? $vendedor['ativo'],
                    ':id' => $id
                ];
            } else {
                // Sem alterar senha
                $sql = "UPDATE usuarios SET 
                        nome = :nome,
                        email = :email,
                        telefone = :telefone,
                        ativo = :ativo
                        WHERE id = :id AND tipo = 'vendedor'";
                
                $params = [
                    ':nome' => trim($dados['nome'] ?? $vendedor['nome']),
                    ':email' => strtolower(trim($dados['email'] ?? $vendedor['email'])),
                    ':telefone' => $dados['telefone'] ?? $vendedor['telefone'],
                    ':ativo' => $dados['ativo'] ?? $vendedor['ativo'],
                    ':id' => $id
                ];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Registrar alterações no histórico
            $alteracoes = [];
            if (($dados['nome'] ?? '') !== $vendedor['nome']) $alteracoes[] = 'nome';
            if (($dados['email'] ?? '') !== $vendedor['email']) $alteracoes[] = 'email';
            if (!empty($dados['senha'])) $alteracoes[] = 'senha';
            if (($dados['ativo'] ?? $vendedor['ativo']) != $vendedor['ativo']) {
                $alteracoes[] = $dados['ativo'] ? 'ativado' : 'desativado';
            }
            
            if (!empty($alteracoes)) {
                $this->registrarHistorico(
                    'usuarios',
                    $id,
                    'atualizado',
                    'atualizado',
                    'Alterações: ' . implode(', ', $alteracoes)
                );
            }
            
            $this->pdo->commit();
            
            return $this->buscar($id);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Desativar vendedor (soft delete)
    public function desativar($id) {
        $vendedor = $this->buscar($id);
        if (!$vendedor) {
            throw new Exception('Vendedor não encontrado');
        }
        
        if (!$vendedor['ativo']) {
            throw new Exception('Vendedor já está desativado');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Verificar se tem vistorias pendentes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM vistorias 
                WHERE vendedor = ? AND status = 'Pendente'
            ");
            $stmt->execute([$vendedor['nome']]);
            $pendentes = $stmt->fetch();
            
            if ($pendentes['total'] > 0) {
                throw new Exception("Vendedor possui {$pendentes['total']} vistorias pendentes");
            }
            
            // Desativar
            $stmt = $this->pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ? AND tipo = 'vendedor'");
            $stmt->execute([$id]);
            
            // Registrar no histórico
            $this->registrarHistorico(
                'usuarios',
                $id,
                'ativo',
                'desativado',
                'Vendedor desativado'
            );
            
            // Criar notificação
            $this->criarNotificacao(
                $_SESSION['usuario_id'],
                'vendedor_desativado',
                'Vendedor Desativado',
                "O vendedor {$vendedor['nome']} foi desativado com sucesso"
            );
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Vendedor desativado com sucesso'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Ativar vendedor
    public function ativar($id) {
        $vendedor = $this->buscar($id);
        if (!$vendedor) {
            throw new Exception('Vendedor não encontrado');
        }
        
        if ($vendedor['ativo']) {
            throw new Exception('Vendedor já está ativo');
        }
        
        $stmt = $this->pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ? AND tipo = 'vendedor'");
        $stmt->execute([$id]);
        
        // Registrar no histórico
        $this->registrarHistorico(
            'usuarios',
            $id,
            'desativado',
            'ativo',
            'Vendedor reativado'
        );
        
        return ['success' => true, 'message' => 'Vendedor ativado com sucesso'];
    }
    
    // Obter estatísticas detalhadas do vendedor
    public function obterEstatisticas($id, $periodo = 30) {
        $vendedor = $this->buscar($id);
        if (!$vendedor) {
            throw new Exception('Vendedor não encontrado');
        }
        
        $dataInicio = date('Y-m-d', strtotime("-$periodo days"));
        
        $sql = "
            SELECT 
                COUNT(DISTINCT v.id) as total_vistorias,
                COUNT(DISTINCT CASE WHEN v.status = 'Concluída' THEN v.id END) as concluidas,
                COUNT(DISTINCT CASE WHEN v.status = 'Pendente' THEN v.id END) as pendentes,
                COUNT(DISTINCT CASE WHEN v.status = 'Cancelada' THEN v.id END) as canceladas,
                COUNT(DISTINCT CASE WHEN p.status = 'Aceita' THEN p.id END) as propostas_aceitas,
                COUNT(DISTINCT p.id) as total_propostas,
                SUM(CASE WHEN p.status = 'Aceita' THEN p.valor_total ELSE 0 END) as valor_total_vendas,
                AVG(CASE WHEN p.status = 'Aceita' THEN p.valor_total ELSE NULL END) as ticket_medio
            FROM usuarios u
            LEFT JOIN vistorias v ON u.nome = v.vendedor AND v.data_criacao >= ?
            LEFT JOIN propostas p ON v.id = p.vistoria_id
            WHERE u.id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dataInicio, $id]);
        $stats = $stmt->fetch();
        
        // Taxa de conversão
        $stats['taxa_conversao'] = $stats['total_propostas'] > 0 
            ? round(($stats['propostas_aceitas'] / $stats['total_propostas']) * 100, 2) 
            : 0;
        
        // Vistorias por dia da semana
        $sql = "
            SELECT 
                DAYNAME(data_vistoria) as dia_semana,
                COUNT(*) as total
            FROM vistorias
            WHERE vendedor = ? AND data_criacao >= ?
            GROUP BY DAYOFWEEK(data_vistoria)
            ORDER BY DAYOFWEEK(data_vistoria)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vendedor['nome'], $dataInicio]);
        $stats['por_dia_semana'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    // Métodos auxiliares
    private function registrarHistorico($tabela, $registroId, $statusAnterior, $statusNovo, $observacoes) {
        $stmt = $this->pdo->prepare("
            INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tabela, $registroId, $statusAnterior, $statusNovo, $_SESSION['usuario_id'], $observacoes]);
    }
    
    private function criarNotificacao($usuarioId, $tipo, $titulo, $mensagem) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuarioId, $tipo, $titulo, $mensagem]);
    }
    
    private function enviarEmailBoasVindas($email, $nome, $senha) {
        // TODO: Implementar envio de email
        // Por enquanto, apenas registrar no log
        $stmt = $this->pdo->prepare("
            INSERT INTO logs_email (destinatario, assunto, tipo, status, erro) 
            VALUES (?, ?, 'boas_vindas', 'Enviado', NULL)
        ");
        $stmt->execute([$email, "Bem-vindo ao Sistema de Vistorias - $nome"]);
    }
}

// Processar requisições
try {
    $manager = new VendedorManager($pdo);
    
    switch($method) {
        case 'GET':
            if(isset($_GET['id'])) {
                // Buscar vendedor específico
                if (isset($_GET['estatisticas'])) {
                    // Obter estatísticas detalhadas
                    $periodo = $_GET['periodo'] ?? 30;
                    $resultado = $manager->obterEstatisticas($_GET['id'], $periodo);
                } else {
                    // Dados básicos do vendedor
                    $resultado = $manager->buscar($_GET['id']);
                    if (!$resultado) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Vendedor não encontrado']);
                        exit;
                    }
                }
                echo json_encode($resultado);
            } else {
                // Listar vendedores
                $filtros = [];
                if (isset($_GET['ativos'])) {
                    $filtros['ativos'] = true;
                }
                
                $vendedores = $manager->listar($filtros);
                echo json_encode($vendedores);
            }
            break;
            
        case 'POST':
            // Criar novo vendedor
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            
            $resultado = $manager->criar($data);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'id' => $resultado['id'],
                'message' => 'Vendedor criado com sucesso',
                'vendedor' => $resultado
            ]);
            break;
            
        case 'PUT':
            // Atualizar vendedor
            if(!isset($_GET['id'])) {
                throw new Exception('ID do vendedor não fornecido');
            }
            
            $vendedorId = intval($_GET['id']);
            
            // Verificar ações especiais
            if (isset($_GET['acao'])) {
                switch ($_GET['acao']) {
                    case 'ativar':
                        $resultado = $manager->ativar($vendedorId);
                        break;
                    case 'desativar':
                        $resultado = $manager->desativar($vendedorId);
                        break;
                    default:
                        throw new Exception('Ação inválida');
                }
                echo json_encode($resultado);
            } else {
                // Atualização normal
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    throw new Exception('Dados inválidos');
                }
                
                $vendedor = $manager->atualizar($vendedorId, $data);
                echo json_encode([
                    'success' => true,
                    'message' => 'Vendedor atualizado com sucesso',
                    'vendedor' => $vendedor
                ]);
            }
            break;
            
        case 'DELETE':
            // Desativar vendedor
            if(!isset($_GET['id'])) {
                throw new Exception('ID do vendedor não fornecido');
            }
            
            $resultado = $manager->desativar(intval($_GET['id']));
            echo json_encode($resultado);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>