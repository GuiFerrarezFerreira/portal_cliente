<?php
// api/upload.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Diretório para uploads
$uploadDir = '../uploads/lista_seguro/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

switch($method) {
    case 'POST':
        if (!isset($_FILES['arquivo']) || !isset($_POST['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo ou ID da vistoria não fornecido']);
            exit;
        }
        
        $vistoriaId = $_POST['vistoria_id'];
        
        // Verificar permissão
        $stmt = $pdo->prepare("SELECT vendedor FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria || (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para fazer upload nesta vistoria']);
            exit;
        }
        
        $arquivo = $_FILES['arquivo'];
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nomeArquivo = 'lista_seguro_' . $vistoriaId . '_' . time() . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        // Validar tipo de arquivo
        $tiposPermitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($extensao), $tiposPermitidos)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tipo de arquivo não permitido']);
            exit;
        }
        
        // Validar tamanho (máximo 10MB)
        if ($arquivo['size'] > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo muito grande (máximo 10MB)']);
            exit;
        }
        
        // Remover arquivo anterior se existir
        $stmt = $pdo->prepare("SELECT arquivo_lista_seguro FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoriaAtual = $stmt->fetch();
        
        if ($vistoriaAtual['arquivo_lista_seguro'] && file_exists($uploadDir . $vistoriaAtual['arquivo_lista_seguro'])) {
            unlink($uploadDir . $vistoriaAtual['arquivo_lista_seguro']);
        }
        
        // Fazer upload
        if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            // Atualizar banco de dados
            $stmt = $pdo->prepare("UPDATE vistorias SET arquivo_lista_seguro = ? WHERE id = ?");
            $stmt->execute([$nomeArquivo, $vistoriaId]);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                                  VALUES ('vistorias', ?, 'sem_arquivo', 'arquivo_anexado', ?, ?)");
            $stmt->execute([$vistoriaId, $_SESSION['usuario_id'], 'Upload de lista de seguro: ' . $arquivo['name']]);
            
            echo json_encode([
                'success' => true,
                'arquivo' => $nomeArquivo,
                'nome_original' => $arquivo['name']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao fazer upload do arquivo']);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $vistoriaId = $data['vistoria_id'] ?? 0;
        
        // Verificar permissão
        $stmt = $pdo->prepare("SELECT vendedor, arquivo_lista_seguro FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria || (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para remover arquivo desta vistoria']);
            exit;
        }
        
        if ($vistoria['arquivo_lista_seguro'] && file_exists($uploadDir . $vistoria['arquivo_lista_seguro'])) {
            unlink($uploadDir . $vistoria['arquivo_lista_seguro']);
        }
        
        // Atualizar banco de dados
        $stmt = $pdo->prepare("UPDATE vistorias SET arquivo_lista_seguro = NULL WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        
        // Registrar no histórico
        $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                              VALUES ('vistorias', ?, 'arquivo_anexado', 'sem_arquivo', ?, 'Arquivo removido')");
        $stmt->execute([$vistoriaId, $_SESSION['usuario_id']]);
        
        echo json_encode(['success' => true]);
        break;
}
?>