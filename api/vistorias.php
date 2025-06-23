<?php
// api/vistorias.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Função para validar dados da vistoria
function validarDadosVistoria($data) {
    $erros = [];
    
    // Campos obrigatórios
    if (empty($data['cliente'])) {
        $erros[] = 'Nome do cliente é obrigatório';
    }
    
    if (empty($data['cpf'])) {
        $erros[] = 'CPF é obrigatório';
    } else {
        // Validar formato do CPF
        $cpf = preg_replace('/\D/', '', $data['cpf']);
        if (strlen($cpf) !== 11) {
            $erros[] = 'CPF inválido';
        }
    }
    
    if (empty($data['telefone'])) {
        $erros[] = 'Telefone é obrigatório';
    }
    
    if (empty($data['endereco'])) {
        $erros[] = 'Endereço é obrigatório';
    }
    
    if (empty($data['tipo_imovel'])) {
        $erros[] = 'Tipo de imóvel é obrigatório';
    }
    
    if (empty($data['data_vistoria'])) {
        $erros[] = 'Data da vistoria é obrigatória';
    }
    
    // Validar email se fornecido
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido';
    }
    
    return $erros;
}

// Função para obter ID do vendedor pelo nome
function getVendedorId($pdo, $nomeVendedor) {
    if (empty($nomeVendedor)) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ? AND tipo = 'vendedor' AND ativo = 1");
    $stmt->execute([$nomeVendedor]);
    $result = $stmt->fetch();
    
    return $result ? $result['id'] : null;
}

// Função para verificar se usuário pode editar vistoria
function podeEditarVistoria($vistoria, $usuario) {
    // Gestor pode editar todas
    if ($usuario['tipo'] === 'gestor') {
        return true;
    }
    
    // Vendedor só pode editar suas próprias vistorias
    if ($usuario['tipo'] === 'vendedor' && $vistoria['vendedor'] === $usuario['nome']) {
        return true;
    }
    
    return false;
}

// Função para verificar se usuário pode ver vistoria
function podeVerVistoria($vistoria, $usuario) {
    // Gestor pode ver todas
    if ($usuario['tipo'] === 'gestor') {
        return true;
    }
    
    // Vendedor só pode ver suas próprias vistorias
    if ($usuario['tipo'] === 'vendedor' && $vistoria['vendedor'] === $usuario['nome']) {
        return true;
    }
    
    return false;
}

// Função para registrar histórico
function registrarHistorico($pdo, $tabela, $registroId, $statusAnterior, $statusNovo, $usuarioId, $observacoes) {
    $stmt = $pdo->prepare("
        INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tabela, $registroId, $statusAnterior, $statusNovo, $usuarioId, $observacoes]);
}

// Função para criar notificação
function criarNotificacao($pdo, $usuarioId, $tipo, $titulo, $mensagem) {
    $stmt = $pdo->prepare("
        INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$usuarioId, $tipo, $titulo, $mensagem]);
}

// Validar se usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$usuario = [
    'id' => $_SESSION['usuario_id'],
    'nome' => $_SESSION['usuario_nome'],
    'tipo' => $_SESSION['usuario_tipo']
];

switch($method) {
    case 'GET':
        try {
            if(isset($_GET['id'])) {
                // Buscar vistoria específica
                $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $vistoria = $stmt->fetch();
                
                if (!$vistoria) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Vistoria não encontrada']);
                    break;
                }
                
                // Verificar permissão
                if (!podeVerVistoria($vistoria, $usuario)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sem permissão para visualizar esta vistoria']);
                    break;
                }
                
                echo json_encode($vistoria);
                
            } else {
                // Listar vistorias
                $where = [];
                $params = [];
                
                // Aplicar filtros baseado no tipo de usuário
                if ($usuario['tipo'] === 'vendedor') {
                    $where[] = 'vendedor = ?';
                    $params[] = $usuario['nome'];
                }
                
                // Filtros adicionais
                if (isset($_GET['status'])) {
                    $where[] = 'status = ?';
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
                    $where[] = 'DATE(data_vistoria) BETWEEN ? AND ?';
                    $params[] = $_GET['data_inicio'];
                    $params[] = $_GET['data_fim'];
                }
                
                if (isset($_GET['cliente'])) {
                    $where[] = 'cliente LIKE ?';
                    $params[] = '%' . $_GET['cliente'] . '%';
                }
                
                $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $sql = "SELECT * FROM vistorias $whereClause ORDER BY data_vistoria DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $vistorias = $stmt->fetchAll();
                
                // Adicionar estatísticas se solicitado
                if (isset($_GET['incluir_estatisticas'])) {
                    $estatisticas = [
                        'total' => count($vistorias),
                        'pendentes' => count(array_filter($vistorias, fn($v) => $v['status'] === 'Pendente')),
                        'concluidas' => count(array_filter($vistorias, fn($v) => $v['status'] === 'Concluída')),
                        'canceladas' => count(array_filter($vistorias, fn($v) => $v['status'] === 'Cancelada'))
                    ];
                    
                    echo json_encode([
                        'vistorias' => $vistorias,
                        'estatisticas' => $estatisticas
                    ]);
                } else {
                    echo json_encode($vistorias);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar vistorias: ' . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        try {
            // Criar nova vistoria
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos']);
                break;
            }
            
            // Validar dados
            $erros = validarDadosVistoria($data);
            if (!empty($erros)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos', 'details' => $erros]);
                break;
            }
            
            // Se não for gestor, forçar o vendedor a ser o usuário logado
            if ($usuario['tipo'] === 'vendedor') {
                $data['vendedor'] = $usuario['nome'];
            } elseif (empty($data['vendedor'])) {
                // Gestor deve informar o vendedor
                http_response_code(400);
                echo json_encode(['error' => 'Vendedor é obrigatório']);
                break;
            }
            
            // Obter ID do vendedor
            $vendedorId = getVendedorId($pdo, $data['vendedor']);
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO vistorias (
                        cliente, cpf, telefone, email, vendedor, vendedor_id, 
                        endereco, tipo_imovel, data_vistoria, status, observacoes
                    ) VALUES (
                        :cliente, :cpf, :telefone, :email, :vendedor, :vendedor_id,
                        :endereco, :tipo_imovel, :data_vistoria, :status, :observacoes
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cliente' => trim($data['cliente']),
                ':cpf' => $data['cpf'],
                ':telefone' => $data['telefone'],
                ':email' => $data['email'] ?? null,
                ':vendedor' => $data['vendedor'],
                ':vendedor_id' => $vendedorId,
                ':endereco' => trim($data['endereco']),
                ':tipo_imovel' => $data['tipo_imovel'],
                ':data_vistoria' => $data['data_vistoria'],
                ':status' => $data['status'] ?? 'Pendente',
                ':observacoes' => $data['observacoes'] ?? null
            ]);
            
            $vistoriaId = $pdo->lastInsertId();
            
            // Registrar no histórico
            registrarHistorico(
                $pdo,
                'vistorias',
                $vistoriaId,
                null,
                $data['status'] ?? 'Pendente',
                $usuario['id'],
                'Vistoria criada'
            );
            
            // Criar notificação para o vendedor se foi criada por gestor
            if ($usuario['tipo'] === 'gestor' && $vendedorId) {
                criarNotificacao(
                    $pdo,
                    $vendedorId,
                    'nova_vistoria',
                    'Nova Vistoria Agendada',
                    "Uma nova vistoria foi agendada para você: {$data['cliente']} - {$data['endereco']}"
                );
            }
            
            // Buscar vistoria criada
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            $vistoriaCriada = $stmt->fetch();
            
            // Commit da transação
            $pdo->commit();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'id' => $vistoriaId,
                'message' => 'Vistoria criada com sucesso',
                'vistoria' => $vistoriaCriada
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar vistoria: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        try {
            // Atualizar vistoria
            if(!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da vistoria não fornecido']);
                break;
            }
            
            $vistoriaId = intval($_GET['id']);
            
            // Verificar se vistoria existe
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            $vistoriaAtual = $stmt->fetch();
            
            if(!$vistoriaAtual) {
                http_response_code(404);
                echo json_encode(['error' => 'Vistoria não encontrada']);
                break;
            }
            
            // Verificar permissão
            if (!podeEditarVistoria($vistoriaAtual, $usuario)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para editar esta vistoria']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos']);
                break;
            }
            
            // Validar dados
            $erros = validarDadosVistoria($data);
            if (!empty($erros)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos', 'details' => $erros]);
                break;
            }
            
            // Se não for gestor, manter o vendedor original
            if ($usuario['tipo'] === 'vendedor') {
                $data['vendedor'] = $vistoriaAtual['vendedor'];
                $data['vendedor_id'] = $vistoriaAtual['vendedor_id'];
            } else {
                // Gestor pode alterar o vendedor
                $data['vendedor_id'] = getVendedorId($pdo, $data['vendedor']);
            }
            
            // Verificar mudança de status
            $statusMudou = $vistoriaAtual['status'] !== $data['status'];
            
            // Validar mudança de status
            $statusPermitidos = ['Pendente', 'Concluída', 'Cancelada'];
            if (!in_array($data['status'], $statusPermitidos)) {
                // Verificar se é um status avançado e se pode ser mantido
                if ($data['status'] !== $vistoriaAtual['status']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Status inválido ou não pode ser alterado manualmente']);
                    break;
                }
            }
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            $sql = "UPDATE vistorias SET 
                    cliente = :cliente,
                    cpf = :cpf,
                    telefone = :telefone,
                    email = :email,
                    vendedor = :vendedor,
                    vendedor_id = :vendedor_id,
                    endereco = :endereco,
                    tipo_imovel = :tipo_imovel,
                    data_vistoria = :data_vistoria,
                    status = :status,
                    observacoes = :observacoes,
                    data_atualizacao = NOW()
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cliente' => trim($data['cliente']),
                ':cpf' => $data['cpf'],
                ':telefone' => $data['telefone'],
                ':email' => $data['email'] ?? null,
                ':vendedor' => $data['vendedor'],
                ':vendedor_id' => $data['vendedor_id'],
                ':endereco' => trim($data['endereco']),
                ':tipo_imovel' => $data['tipo_imovel'],
                ':data_vistoria' => $data['data_vistoria'],
                ':status' => $data['status'],
                ':observacoes' => $data['observacoes'] ?? null,
                ':id' => $vistoriaId
            ]);
            
            // Registrar mudança de status no histórico
            if ($statusMudou) {
                registrarHistorico(
                    $pdo,
                    'vistorias',
                    $vistoriaId,
                    $vistoriaAtual['status'],
                    $data['status'],
                    $usuario['id'],
                    'Status alterado manualmente'
                );
                
                // Notificar vendedor se status mudou para Concluída
                if ($data['status'] === 'Concluída' && $data['vendedor_id']) {
                    criarNotificacao(
                        $pdo,
                        $data['vendedor_id'],
                        'vistoria_concluida',
                        'Vistoria Concluída',
                        "A vistoria do cliente {$data['cliente']} foi marcada como concluída"
                    );
                }
            }
            
            // Se vendedor mudou, notificar novo vendedor
            if ($vistoriaAtual['vendedor'] !== $data['vendedor'] && $data['vendedor_id']) {
                criarNotificacao(
                    $pdo,
                    $data['vendedor_id'],
                    'vistoria_transferida',
                    'Vistoria Transferida',
                    "A vistoria do cliente {$data['cliente']} foi transferida para você"
                );
            }
            
            // Buscar vistoria atualizada
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            $vistoriaAtualizada = $stmt->fetch();
            
            // Commit da transação
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Vistoria atualizada com sucesso',
                'vistoria' => $vistoriaAtualizada
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao atualizar vistoria: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        try {
            // Excluir vistoria (apenas gestor)
            if ($usuario['tipo'] !== 'gestor') {
                http_response_code(403);
                echo json_encode(['error' => 'Apenas gestores podem excluir vistorias']);
                break;
            }
            
            if(!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da vistoria não fornecido']);
                break;
            }
            
            $vistoriaId = intval($_GET['id']);
            
            // Verificar se vistoria existe
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            $vistoria = $stmt->fetch();
            
            if (!$vistoria) {
                http_response_code(404);
                echo json_encode(['error' => 'Vistoria não encontrada']);
                break;
            }
            
            // Verificar se pode ser excluída
            $statusBloqueados = ['Enviada_Cotacao', 'Cotacao_Aprovada', 'Proposta_Enviada', 'Proposta_Aceita', 'Em_Andamento', 'Finalizada'];
            if (in_array($vistoria['status'], $statusBloqueados)) {
                http_response_code(400);
                echo json_encode(['error' => "Não é possível excluir vistoria com status: {$vistoria['status']}"]);
                break;
            }
            
            // Verificar dependências
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes WHERE vistoria_id = ?");
            $stmt->execute([$vistoriaId]);
            $cotacoes = $stmt->fetch();
            
            if ($cotacoes['total'] > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Não é possível excluir vistoria com cotações associadas']);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM propostas WHERE vistoria_id = ?");
            $stmt->execute([$vistoriaId]);
            $propostas = $stmt->fetch();
            
            if ($propostas['total'] > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Não é possível excluir vistoria com propostas associadas']);
                break;
            }
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Excluir arquivo se existir
            if ($vistoria['arquivo_lista_seguro']) {
                $arquivoPath = '../uploads/lista_seguro/' . $vistoria['arquivo_lista_seguro'];
                if (file_exists($arquivoPath)) {
                    unlink($arquivoPath);
                }
            }
            
            // Excluir histórico
            $stmt = $pdo->prepare("DELETE FROM historico_status WHERE tabela = 'vistorias' AND registro_id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Excluir vistoria
            $stmt = $pdo->prepare("DELETE FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Notificar vendedor
            if ($vistoria['vendedor_id']) {
                criarNotificacao(
                    $pdo,
                    $vistoria['vendedor_id'],
                    'vistoria_excluida',
                    'Vistoria Excluída',
                    "A vistoria do cliente {$vistoria['cliente']} foi excluída pelo gestor"
                );
            }
            
            // Commit da transação
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Vistoria excluída com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir vistoria: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}
?>