<?php
// api/vistorias.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            // Buscar vistoria específica
            $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $vistoria = $stmt->fetch();
            echo json_encode($vistoria);
        } else {
            // Listar todas as vistorias
            $stmt = $pdo->query("SELECT * FROM vistorias ORDER BY data_vistoria DESC");
            $vistorias = $stmt->fetchAll();
            echo json_encode($vistorias);
        }
        break;
        
    case 'POST':
        // Criar nova vistoria
        $data = json_decode(file_get_contents('php://input'), true);
        
        $sql = "INSERT INTO vistorias (cliente, cpf, telefone, endereco, tipo_imovel, data_vistoria, status, observacoes) 
                VALUES (:cliente, :cpf, :telefone, :endereco, :tipo_imovel, :data_vistoria, :status, :observacoes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cliente' => $data['cliente'],
            ':cpf' => $data['cpf'],
            ':telefone' => $data['telefone'],
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
            $data = json_decode(file_get_contents('php://input'), true);
            
            $sql = "UPDATE vistorias SET 
                    cliente = :cliente,
                    cpf = :cpf,
                    telefone = :telefone,
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
        // Excluir vistoria
        if(isset($_GET['id'])) {
            $stmt = $pdo->prepare("DELETE FROM vistorias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            echo json_encode(['message' => 'Vistoria excluída com sucesso']);
        }
        break;
}
?>