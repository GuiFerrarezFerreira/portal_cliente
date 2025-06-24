<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Verificar se está logado como cliente
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'cliente') {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se foi enviado arquivo e doc_id
if (!isset($_FILES['documento']) || !isset($_POST['doc_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Arquivo ou ID do documento não fornecido']);
    exit;
}

$docId = intval($_POST['doc_id']);
$clienteId = $_SESSION['usuario_id'];

try {
    // Verificar se o documento pertence ao cliente
    $stmt = $pdo->prepare("
        SELECT sd.*, m.cliente_id 
        FROM solicitacoes_documentos sd
        JOIN mudancas m ON sd.mudanca_id = m.id
        JOIN clientes c ON m.cliente_id = c.id
        JOIN usuarios u ON c.email = u.email
        WHERE sd.id = ? AND u.id = ? AND sd.status = 'Pendente'
    ");
    $stmt->execute([$docId, $clienteId]);
    $documento = $stmt->fetch();
    
    if (!$documento) {
        http_response_code(404);
        echo json_encode(['error' => 'Documento não encontrado ou já foi enviado']);
        exit;
    }
    
    // Validar arquivo
    $arquivo = $_FILES['documento'];
    $tamanhoMaximo = 10 * 1024 * 1024; // 10MB
    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Erro no upload do arquivo']);
        exit;
    }
    
    if ($arquivo['size'] > $tamanhoMaximo) {
        http_response_code(400);
        echo json_encode(['error' => 'Arquivo muito grande. Máximo permitido: 10MB']);
        exit;
    }
    
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoesPermitidas)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de arquivo não permitido']);
        exit;
    }
    
    // Criar diretório se não existir
    $uploadDir = '../uploads/documentos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Gerar nome único para o arquivo
    $nomeArquivo = 'doc_' . $documento['mudanca_id'] . '_' . $docId . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    // Mover arquivo
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar arquivo']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Inserir registro do documento
        $stmt = $pdo->prepare("
            INSERT INTO documentos (mudanca_id, tipo, nome_arquivo, caminho_arquivo, enviado_por, status) 
            VALUES (?, ?, ?, ?, ?, 'Enviado')
        ");
        $stmt->execute([
            $documento['mudanca_id'],
            $documento['tipo_documento'],
            $arquivo['name'],
            'uploads/documentos/' . $nomeArquivo,
            $clienteId
        ]);
        
        // Atualizar status da solicitação
        $stmt = $pdo->prepare("
            UPDATE solicitacoes_documentos 
            SET status = 'Recebido', data_recebimento = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$docId]);
        
        // Verificar se todos os documentos obrigatórios foram enviados
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pendentes 
            FROM solicitacoes_documentos 
            WHERE mudanca_id = ? AND obrigatorio = 1 AND status = 'Pendente'
        ");
        $stmt->execute([$documento['mudanca_id']]);
        $pendentes = $stmt->fetch();
        
        // Se não há mais documentos pendentes, atualizar status da mudança
        if ($pendentes['pendentes'] == 0) {
            $stmt = $pdo->prepare("
                UPDATE mudancas 
                SET status = 'Documentos_Recebidos' 
                WHERE id = ? AND status = 'Aguardando_Documentos'
            ");
            $stmt->execute([$documento['mudanca_id']]);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('mudancas', ?, 'Aguardando_Documentos', 'Documentos_Recebidos', ?, 'Todos os documentos obrigatórios foram recebidos')
            ");
            $stmt->execute([$documento['mudanca_id'], $clienteId]);
            
            // Criar notificação para o coordenador
            $stmt = $pdo->prepare("
                SELECT coordenador_id FROM mudancas WHERE id = ?
            ");
            $stmt->execute([$documento['mudanca_id']]);
            $mudanca = $stmt->fetch();
            
            if ($mudanca['coordenador_id']) {
                $stmt = $pdo->prepare("
                    INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                    VALUES (?, 'documentos_completos', 'Documentos Recebidos', ?)
                ");
                $stmt->execute([
                    $mudanca['coordenador_id'],
                    "Todos os documentos da mudança #{$documento['mudanca_id']} foram recebidos."
                ]);
            }
        }
        
        // Registrar no histórico
        $stmt = $pdo->prepare("
            INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
            VALUES ('documentos', ?, 'Pendente', 'Enviado', ?, ?)
        ");
        $stmt->execute([
            $pdo->lastInsertId(),
            $clienteId,
            "Documento enviado: {$documento['tipo_documento']}"
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento enviado com sucesso'
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        
        // Remover arquivo se houver erro
        if (file_exists($caminhoCompleto)) {
            unlink($caminhoCompleto);
        }
        
        throw $e;
    }
    
} catch(PDOException $e) {
    error_log('Erro ao processar upload de documento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar documento']);
}