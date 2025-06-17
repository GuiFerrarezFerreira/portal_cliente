<?php
// api/vendedores.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            // Buscar vendedor específico
            $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $vendedor = $stmt->fetch();
            echo json_encode($vendedor);
        } else if(isset($_GET['ativos'])) {
            // Listar apenas vendedores ativos
            $stmt = $pdo->query("SELECT * FROM vendedores WHERE ativo = 1 ORDER BY nome");
            $vendedores = $stmt->fetchAll();
            echo json_encode($vendedores);
        } else {
            // Listar todos os vendedores
            $stmt = $pdo->query("SELECT * FROM vendedores ORDER BY nome");
            $vendedores = $stmt->fetchAll();
            echo json_encode($vendedores);
        }
        break;
        
    case 'POST':
        // Criar novo vendedor
        $data = json_decode(file_get_contents('php://input'), true);
        
        $sql = "INSERT INTO vendedores (nome, email, telefone, ativo) 
                VALUES (:nome, :email, :telefone, :ativo)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $data['nome'],
            ':email' => $data['email'],
            ':telefone' => $data['telefone'],
            ':ativo' => $data['ativo'] ?? true
        ]);
        
        echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Vendedor criado com sucesso']);
        break;
        
    case 'PUT':
        // Atualizar vendedor
        if(isset($_GET['id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $sql = "UPDATE vendedores SET 
                    nome = :nome,
                    email = :email,
                    telefone = :telefone,
                    ativo = :ativo
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $data['nome'],
                ':email' => $data['email'],
                ':telefone' => $data['telefone'],
                ':ativo' => $data['ativo'],
                ':id' => $_GET['id']
            ]);
            
            echo json_encode(['message' => 'Vendedor atualizado com sucesso']);
        }
        break;
        
    case 'DELETE':
        // Desativar vendedor (soft delete)
        if(isset($_GET['id'])) {
            $stmt = $pdo->prepare("UPDATE vendedores SET ativo = 0 WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            echo json_encode(['message' => 'Vendedor desativado com sucesso']);
        }
        break;
}
?>