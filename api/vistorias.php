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
                if(isGestor() || $vistoria['vendedor'] === $_SESSION['usuario_nome']) {
                    echo json_encode($vistoria);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Acesso negado']);
                }
            } else {
                // Listar vistorias
                if(isGestor()) {
                    // Gestor vê todas
                    $sql = "SELECT * FROM vistorias ORDER BY data_vistoria DESC";
                    $stmt = $pdo->query($sql);
                } else {
                    // Vendedor vê apenas as suas
                    $sql = "SELECT * FROM vistorias WHERE vendedor = ? ORDER BY data_vistoria DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_SESSION['usuario_nome']]);
                }
                
                $vistorias = $stmt->fetchAll();
                echo json_encode($vistorias);
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
            
            // Validar dados
            $erros = validarDadosVistoria($data);
            if (!empty($erros)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos', 'details' => $erros]);
                break;
            }
            
            // Se não for gestor, forçar o vendedor a ser o usuário logado
            if(!isGestor()) {
                $data['vendedor'] = $_SESSION['usuario_nome'];
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
            $stmt = $pdo->prepare("
                INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('vistorias', ?, NULL, ?, ?, 'Vistoria criada')
            ");
            $stmt->execute([$vistoriaId, $data['status'] ?? 'Pendente', $_SESSION['usuario_id']]);
            
            // Commit da transação
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'id' => $vistoriaId, 
                'message' => 'Vistoria criada com sucesso'
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
            
            $vistoriaId = $_GET['id'];
            
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
            if(!isGestor() && $vistoriaAtual['vendedor'] !== $_SESSION['usuario_nome']) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para editar esta vistoria']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar dados
            $erros = validarDadosVistoria($data);
            if (!empty($erros)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados inválidos', 'details' => $erros]);
                break;
            }
            
            // Se não for gestor, manter o vendedor original
            if(!isGestor()) {
                $data['vendedor'] = $vistoriaAtual['vendedor'];
            }
            
            // Obter ID do vendedor
            $vendedorId = getVendedorId($pdo, $data['vendedor']);
            
            // Verificar mudança de status para registrar histórico
            $statusMudou = $vistoriaAtual['status'] !== $data['status'];
            
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
                    observacoes = :observacoes
                    WHERE id = :id";
            
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
                ':status' => $data['status'],
                ':observacoes' => $data['observacoes'] ?? null,
                ':id' => $vistoriaId
            ]);
            
            // Registrar mudança de status no histórico
            if ($statusMudou) {
                $stmt = $pdo->prepare("
                    INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                    VALUES ('vistorias', ?, ?, ?, ?, 'Status alterado manualmente')
                ");
                $stmt->execute([
                    $vistoriaId, 
                    $vistoriaAtual['status'], 
                    $data['status'], 
                    $_SESSION['usuario_id']
                ]);
            }
            
            // Commit da transação
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Vistoria atualizada com sucesso'
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
            if(!isGestor()) {
                http_response_code(403);
                echo json_encode(['error' => 'Apenas gestores podem excluir vistorias']);
                break;
            }
            
            if(!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID da vistoria não fornecido']);
                break;
            }
            
            $vistoriaId = $_GET['id'];
            
            // Verificar se vistoria existe
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            $vistoria = $stmt->fetch();
            
            if (!$vistoria) {
                http_response_code(404);
                echo json_encode(['error' => 'Vistoria não encontrada']);
                break;
            }
            
            // Verificar se pode ser excluída (não ter cotações, propostas, etc)
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