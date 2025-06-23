<?php
// api/upload.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Configurações de upload
define('UPLOAD_BASE_DIR', '../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);

// Função para criar diretório se não existir
function criarDiretorio($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            throw new Exception('Erro ao criar diretório de upload');
        }
    }
    return true;
}

// Função para validar arquivo
function validarArquivo($arquivo) {
    // Verificar se houve erro no upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        switch ($arquivo['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('Arquivo muito grande');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('Upload incompleto');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Nenhum arquivo enviado');
            default:
                throw new Exception('Erro desconhecido no upload');
        }
    }
    
    // Validar tamanho
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        throw new Exception('Arquivo muito grande (máximo ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB)');
    }
    
    // Validar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ALLOWED_EXTENSIONS)) {
        throw new Exception('Tipo de arquivo não permitido. Permitidos: ' . implode(', ', ALLOWED_EXTENSIONS));
    }
    
    // Validar MIME type real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    $mimeTypes = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png']
    ];
    
    if (!isset($mimeTypes[$extensao]) || !in_array($mimeType, $mimeTypes[$extensao])) {
        throw new Exception('Tipo de arquivo inválido');
    }
    
    return $extensao;
}

// Função para gerar nome único de arquivo
function gerarNomeArquivo($tipo, $id, $extensao) {
    $timestamp = date('YmdHis');
    $random = bin2hex(random_bytes(4));
    return "{$tipo}_{$id}_{$timestamp}_{$random}.{$extensao}";
}

// Função para obter informações do arquivo
function obterInfoArquivo($caminhoCompleto) {
    if (!file_exists($caminhoCompleto)) {
        return null;
    }
    
    return [
        'nome' => basename($caminhoCompleto),
        'tamanho' => filesize($caminhoCompleto),
        'tamanho_formatado' => formatarTamanho(filesize($caminhoCompleto)),
        'tipo' => mime_content_type($caminhoCompleto),
        'extensao' => pathinfo($caminhoCompleto, PATHINFO_EXTENSION),
        'data_upload' => date('Y-m-d H:i:s', filemtime($caminhoCompleto))
    ];
}

// Função para formatar tamanho de arquivo
function formatarTamanho($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

// Função para limpar uploads antigos (opcional)
function limparUploadsAntigos($diretorio, $diasRetencao = 90) {
    $arquivos = glob($diretorio . '*');
    $dataLimite = time() - ($diasRetencao * 24 * 60 * 60);
    $arquivosRemovidos = 0;
    
    foreach ($arquivos as $arquivo) {
        if (is_file($arquivo) && filemtime($arquivo) < $dataLimite) {
            unlink($arquivo);
            $arquivosRemovidos++;
        }
    }
    
    return $arquivosRemovidos;
}

switch($method) {
    case 'GET':
        // Obter informações do arquivo ou download
        if (!isset($_GET['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vistoria não fornecido']);
            exit;
        }
        
        $vistoriaId = intval($_GET['vistoria_id']);
        $acao = $_GET['acao'] ?? 'info';
        
        // Verificar permissão
        $stmt = $pdo->prepare("SELECT vendedor, arquivo_lista_seguro FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria || (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para acessar este arquivo']);
            exit;
        }
        
        if (!$vistoria['arquivo_lista_seguro']) {
            http_response_code(404);
            echo json_encode(['error' => 'Nenhum arquivo encontrado']);
            exit;
        }
        
        $caminhoCompleto = UPLOAD_BASE_DIR . 'lista_seguro/' . $vistoria['arquivo_lista_seguro'];
        
        if (!file_exists($caminhoCompleto)) {
            http_response_code(404);
            echo json_encode(['error' => 'Arquivo não encontrado no servidor']);
            exit;
        }
        
        if ($acao === 'download') {
            // Download do arquivo
            header('Content-Type: ' . mime_content_type($caminhoCompleto));
            header('Content-Disposition: attachment; filename="' . basename($caminhoCompleto) . '"');
            header('Content-Length: ' . filesize($caminhoCompleto));
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            readfile($caminhoCompleto);
            exit;
        } else {
            // Retornar informações do arquivo
            echo json_encode([
                'success' => true,
                'arquivo' => obterInfoArquivo($caminhoCompleto)
            ]);
        }
        break;
        
    case 'POST':
        if (!isset($_FILES['arquivo']) || !isset($_POST['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo ou ID da vistoria não fornecido']);
            exit;
        }
        
        $vistoriaId = intval($_POST['vistoria_id']);
        $tipoUpload = $_POST['tipo'] ?? 'lista_seguro';
        
        // Verificar permissão
        $stmt = $pdo->prepare("SELECT vendedor, status, arquivo_lista_seguro FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(404);
            echo json_encode(['error' => 'Vistoria não encontrada']);
            exit;
        }
        
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para fazer upload nesta vistoria']);
            exit;
        }
        
        // Validar status da vistoria
        if ($tipoUpload === 'lista_seguro' && $vistoria['status'] !== 'Concluída') {
            http_response_code(400);
            echo json_encode(['error' => 'Só é possível anexar lista de seguro em vistorias concluídas']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Criar diretório
            $uploadDir = UPLOAD_BASE_DIR . $tipoUpload . '/';
            criarDiretorio($uploadDir);
            
            // Validar arquivo
            $arquivo = $_FILES['arquivo'];
            $extensao = validarArquivo($arquivo);
            
            // Gerar nome único
            $nomeArquivo = gerarNomeArquivo($tipoUpload, $vistoriaId, $extensao);
            $caminhoCompleto = $uploadDir . $nomeArquivo;
            
            // Remover arquivo anterior se existir
            if ($vistoria['arquivo_lista_seguro']) {
                $arquivoAnterior = $uploadDir . $vistoria['arquivo_lista_seguro'];
                if (file_exists($arquivoAnterior)) {
                    unlink($arquivoAnterior);
                    
                    // Registrar remoção no histórico
                    $stmt = $pdo->prepare("
                        INSERT INTO historico_status 
                        (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                        VALUES ('vistorias', ?, 'arquivo_substituido', 'novo_arquivo', ?, ?)
                    ");
                    $stmt->execute([
                        $vistoriaId, 
                        $_SESSION['usuario_id'], 
                        'Arquivo anterior removido: ' . $vistoria['arquivo_lista_seguro']
                    ]);
                }
            }
            
            // Fazer upload
            if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                throw new Exception('Erro ao mover arquivo para destino');
            }
            
            // Atualizar banco de dados
            $stmt = $pdo->prepare("UPDATE vistorias SET arquivo_lista_seguro = ?, data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$nomeArquivo, $vistoriaId]);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('vistorias', ?, ?, 'arquivo_anexado', ?, ?)
            ");
            $stmt->execute([
                $vistoriaId,
                $vistoria['arquivo_lista_seguro'] ? 'arquivo_existente' : 'sem_arquivo',
                $_SESSION['usuario_id'], 
                'Upload de lista de seguro: ' . $arquivo['name'] . ' (' . formatarTamanho($arquivo['size']) . ')'
            ]);
            
            // Se for gestor, criar notificação
            if (isGestor()) {
                $stmt = $pdo->prepare("
                    INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                    VALUES (?, 'arquivo_anexado', 'Arquivo Anexado', ?)
                ");
                $mensagem = "Lista de seguro anexada à vistoria #{$vistoriaId}";
                $stmt->execute([$_SESSION['usuario_id'], $mensagem]);
            }
            
            $pdo->commit();
            
            // Retornar sucesso com informações do arquivo
            echo json_encode([
                'success' => true,
                'arquivo' => $nomeArquivo,
                'nome_original' => $arquivo['name'],
                'tamanho' => formatarTamanho($arquivo['size']),
                'info' => obterInfoArquivo($caminhoCompleto)
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $vistoriaId = intval($data['vistoria_id'] ?? 0);
        
        if (!$vistoriaId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vistoria não fornecido']);
            exit;
        }
        
        // Verificar permissão
        $stmt = $pdo->prepare("SELECT vendedor, arquivo_lista_seguro, status FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(404);
            echo json_encode(['error' => 'Vistoria não encontrada']);
            exit;
        }
        
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para remover arquivo desta vistoria']);
            exit;
        }
        
        // Verificar se pode remover baseado no status
        $statusBloqueados = ['Enviada_Cotacao', 'Cotacao_Aprovada', 'Proposta_Enviada', 'Proposta_Aceita'];
        if (in_array($vistoria['status'], $statusBloqueados)) {
            http_response_code(400);
            echo json_encode(['error' => 'Não é possível remover arquivo de vistoria com status: ' . $vistoria['status']]);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Remover arquivo físico
            if ($vistoria['arquivo_lista_seguro']) {
                $caminhoArquivo = UPLOAD_BASE_DIR . 'lista_seguro/' . $vistoria['arquivo_lista_seguro'];
                if (file_exists($caminhoArquivo)) {
                    if (!unlink($caminhoArquivo)) {
                        throw new Exception('Erro ao remover arquivo do servidor');
                    }
                }
            }
            
            // Atualizar banco de dados
            $stmt = $pdo->prepare("UPDATE vistorias SET arquivo_lista_seguro = NULL, data_atualizacao = NOW() WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('vistorias', ?, 'arquivo_anexado', 'sem_arquivo', ?, ?)
            ");
            $stmt->execute([
                $vistoriaId, 
                $_SESSION['usuario_id'],
                'Arquivo removido: ' . ($vistoria['arquivo_lista_seguro'] ?? 'arquivo.pdf')
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Arquivo removido com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}
?>