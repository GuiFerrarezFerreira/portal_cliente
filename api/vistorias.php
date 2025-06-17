<?php
// api/vistorias.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            // Buscar vistoria específica
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $vistoria = $stmt->fetch();
            
            // Verificar permissão
            if($vistoria && (isGestor() || $vistoria['vendedor'] === $_SESSION['usuario_nome'])) {
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
        break;
        
    case 'POST':
        // Criar nova vistoria
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Se não for gestor, forçar o vendedor a ser o usuário logado
        if(!isGestor()) {
            $data['vendedor'] = $_SESSION['usuario_nome'];
        }
        
        $sql = "INSERT INTO vistorias (cliente, cpf, telefone, vendedor, endereco, tipo_imovel, data_vistoria, status, observacoes) 
                VALUES (:cliente, :cpf, :telefone, :vendedor, :endereco, :tipo_imovel, :data_vistoria, :status, :observacoes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cliente' => $data['cliente'],
            ':cpf' => $data['cpf'],
            ':telefone' => $data['telefone'],
            ':vendedor' => $data['vendedor'],
            ':endereco' => $data['endereco'],
            ':tipo_imovel' => $data['tipo_imovel'],
            ':data_vistoria' => $data['data_vistoria'],
            ':status' => $data['status'],
            ':observacoes' => $data['observacoes']
        ]);
        
        echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Vistoria criada com sucesso']);
        break;
        
    case 'PUT':
        // Atualizar vistoria
        if(isset($_GET['id'])) {
            // Verificar permissão
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $vistoriaAtual = $stmt->fetch();
            
            if(!$vistoriaAtual) {
                http_response_code(404);
                echo json_encode(['error' => 'Vistoria não encontrada']);
                break;
            }
            
            // Verificar se tem permissão para editar
            if(!isGestor() && $vistoriaAtual['vendedor'] !== $_SESSION['usuario_nome']) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para editar esta vistoria']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Se não for gestor, manter o vendedor original
            if(!isGestor()) {
                $data['vendedor'] = $vistoriaAtual['vendedor'];
            }
            
            $sql = "UPDATE vistorias SET 
                    cliente = :cliente,
                    cpf = :cpf,
                    telefone = :telefone,
                    vendedor = :vendedor,
                    endereco = :endereco,
                    tipo_imovel = :tipo_imovel,
                    data_vistoria = :data_vistoria,
                    status = :status,
                    observacoes = :observacoes
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cliente' => $data['cliente'],
                ':cpf' => $data['cpf'],
                ':telefone' => $data['telefone'],
                ':vendedor' => $data['vendedor'],
                ':endereco' => $data['endereco'],
                ':tipo_imovel' => $data['tipo_imovel'],
                ':data_vistoria' => $data['data_vistoria'],
                ':status' => $data['status'],
                ':observacoes' => $data['observacoes'],
                ':id' => $_GET['id']
            ]);
            
            echo json_encode(['message' => 'Vistoria atualizada com sucesso']);
        }
        break;
        
    case 'DELETE':
        // Excluir vistoria (apenas gestor)
        if(!isGestor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas gestores podem excluir vistorias']);
            break;
        }
        
        if(isset($_GET['id'])) {
            $stmt = $pdo->prepare("DELETE FROM vistorias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            echo json_encode(['message' => 'Vistoria excluída com sucesso']);
        }
        break;
}
?>