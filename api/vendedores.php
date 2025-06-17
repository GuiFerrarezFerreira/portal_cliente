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

switch($method) {
    case 'GET':
        if(isset($_GET['id'])) {
            // Buscar vendedor específico
            $stmt = $pdo->prepare("SELECT id, nome, email, telefone, ativo, tipo, ultimo_acesso FROM usuarios WHERE id = ? AND tipo = 'vendedor'");
            $stmt->execute([$_GET['id']]);
            $vendedor = $stmt->fetch();
            echo json_encode($vendedor);
        } else if(isset($_GET['ativos'])) {
            // Listar apenas vendedores ativos
            $stmt = $pdo->query("SELECT id, nome, email, telefone, ativo, tipo, ultimo_acesso FROM usuarios WHERE ativo = 1 AND tipo = 'vendedor' ORDER BY nome");
            $vendedores = $stmt->fetchAll();
            echo json_encode($vendedores);
        } else {
            // Listar todos os vendedores
            $stmt = $pdo->query("SELECT id, nome, email, telefone, ativo, tipo, ultimo_acesso FROM usuarios WHERE tipo = 'vendedor' ORDER BY nome");
            $vendedores = $stmt->fetchAll();
            echo json_encode($vendedores);
        }
        break;
        
    case 'POST':
        // Criar novo vendedor
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        if($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email já cadastrado']);
            break;
        }
        
        // Hash da senha
        $senhaHash = password_hash($data['senha'] ?? 'vendedor123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nome, email, senha, telefone, tipo, ativo) 
                VALUES (:nome, :email, :senha, :telefone, 'vendedor', :ativo)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $data['nome'],
            ':email' => $data['email'],
            ':senha' => $data['senha'],
            ':telefone' => $data['telefone'],
            ':ativo' => $data['ativo'] ?? 1
        ]);
        
        echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Vendedor criado com sucesso']);
        break;
        
    case 'PUT':
        // Atualizar vendedor
        if(isset($_GET['id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Se houver nova senha
            if(!empty($data['senha'])) {
                $sql = "UPDATE usuarios SET 
                        nome = :nome,
                        email = :email,
                        senha = :senha,
                        telefone = :telefone,
                        ativo = :ativo
                        WHERE id = :id AND tipo = 'vendedor'";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nome' => $data['nome'],
                    ':email' => $data['email'],
                    ':senha' => password_hash($data['senha'], PASSWORD_DEFAULT),
                    ':telefone' => $data['telefone'],
                    ':ativo' => $data['ativo'],
                    ':id' => $_GET['id']
                ]);
            } else {
                // Sem alterar senha
                $sql = "UPDATE usuarios SET 
                        nome = :nome,
                        email = :email,
                        telefone = :telefone,
                        ativo = :ativo
                        WHERE id = :id AND tipo = 'vendedor'";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nome' => $data['nome'],
                    ':email' => $data['email'],
                    ':telefone' => $data['telefone'],
                    ':ativo' => $data['ativo'],
                    ':id' => $_GET['id']
                ]);
            }
            
            echo json_encode(['message' => 'Vendedor atualizado com sucesso']);
        }
        break;
        
    case 'DELETE':
        // Desativar vendedor (soft delete)
        if(isset($_GET['id'])) {
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ? AND tipo = 'vendedor'");
            $stmt->execute([$_GET['id']]);
            
            echo json_encode(['message' => 'Vendedor desativado com sucesso']);
        }
        break;
}
?>