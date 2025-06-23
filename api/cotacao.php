<?php
// api/cotacao.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Função para enviar email aos parceiros
function enviarCotacaoParceiros($cotacaoId, $vistoria, $pdo) {
    // Buscar parceiros ativos
    $stmt = $pdo->query("SELECT * FROM parceiros WHERE ativo = 1");
    $parceiros = $stmt->fetchAll();
    
    foreach ($parceiros as $parceiro) {
        // Gerar token único para cada parceiro
        $token = bin2hex(random_bytes(32));
        
        // Criar registro na tabela cotacoes_parceiros
        $stmt = $pdo->prepare("
            INSERT INTO cotacoes_parceiros (cotacao_id, parceiro_id, token_acesso) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$cotacaoId, $parceiro['id'], $token]);
        
        // Enviar email (simulado - em produção usar PHPMailer)
        $linkCotacao = "http://localhost/sistema-mudancas/parceiro-cotacao.php?token=" . $token;
        
        // Registrar log de email
        $stmt = $pdo->prepare("
            INSERT INTO logs_email (destinatario, assunto, tipo, status) 
            VALUES (?, ?, 'cotacao_parceiro', 'Enviado')
        ");
        $stmt->execute([
            $parceiro['email'], 
            'Nova Solicitação de Cotação - Cliente: ' . $vistoria['cliente']
        ]);
    }
    
    return count($parceiros);
}

// Função para calcular estatísticas da cotação
function calcularEstatisticas($cotacaoId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT cp.id) as total_parceiros,
            COUNT(DISTINCT CASE WHEN cp.valor IS NOT NULL THEN cp.id END) as parceiros_responderam,
            MIN(cp.valor) as menor_valor,
            MAX(cp.valor) as maior_valor,
            AVG(cp.valor) as media_valor,
            COUNT(DISTINCT CASE WHEN cp.valor IS NULL THEN cp.id END) as parceiros_pendentes
        FROM cotacoes_parceiros cp
        WHERE cp.cotacao_id = ?
    ");
    $stmt->execute([$cotacaoId]);
    return $stmt->fetch();
}

// Função para verificar se todas as respostas foram recebidas
function todasRespostasRecebidas($cotacaoId, $pdo) {
    $stats = calcularEstatisticas($cotacaoId, $pdo);
    return $stats['parceiros_pendentes'] == 0;
}

switch($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Buscar cotação específica
            $stmt = $pdo->prepare("
                SELECT c.*, v.cliente, v.endereco, v.tipo_imovel, v.vendedor
                FROM cotacoes c
                JOIN vistorias v ON c.vistoria_id = v.id
                WHERE c.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $cotacao = $stmt->fetch();
            
            if ($cotacao) {
                // Verificar permissão
                if (!isGestor() && $cotacao['vendedor'] !== $_SESSION['usuario_nome']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sem permissão para visualizar esta cotação']);
                    exit;
                }
                
                // Adicionar estatísticas
                $cotacao['estatisticas'] = calcularEstatisticas($cotacao['id'], $pdo);
                
                // Buscar respostas dos parceiros
                $stmt = $pdo->prepare("
                    SELECT cp.*, p.nome as parceiro_nome, p.email as parceiro_email
                    FROM cotacoes_parceiros cp
                    JOIN parceiros p ON cp.parceiro_id = p.id
                    WHERE cp.cotacao_id = ?
                    ORDER BY cp.valor ASC, cp.data_resposta ASC
                ");
                $stmt->execute([$cotacao['id']]);
                $cotacao['parceiros'] = $stmt->fetchAll();
                
                // Calcular tempo restante para respostas
                $dataCriacao = new DateTime($cotacao['data_criacao']);
                $agora = new DateTime();
                $prazoResposta = clone $dataCriacao;
                $prazoResposta->add(new DateInterval('PT48H')); // 48 horas de prazo
                
                if ($agora < $prazoResposta) {
                    $intervalo = $agora->diff($prazoResposta);
                    $cotacao['horas_restantes'] = ($intervalo->days * 24) + $intervalo->h;
                } else {
                    $cotacao['horas_restantes'] = 0;
                }
            }
            
            echo json_encode($cotacao ?: null);
            
        } elseif (isset($_GET['vistoria_id'])) {
            // Buscar cotação por vistoria
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       v.cliente, v.endereco, v.tipo_imovel, v.vendedor
                FROM cotacoes c
                JOIN vistorias v ON c.vistoria_id = v.id
                WHERE c.vistoria_id = ?
                ORDER BY c.data_criacao DESC
                LIMIT 1
            ");
            $stmt->execute([$_GET['vistoria_id']]);
            $cotacao = $stmt->fetch();
            
            if ($cotacao) {
                // Verificar permissão
                if (!isGestor() && $cotacao['vendedor'] !== $_SESSION['usuario_nome']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sem permissão']);
                    exit;
                }
                
                // Adicionar estatísticas e parceiros
                $cotacao['estatisticas'] = calcularEstatisticas($cotacao['id'], $pdo);
                
                $stmt = $pdo->prepare("
                    SELECT cp.*, p.nome as parceiro_nome
                    FROM cotacoes_parceiros cp
                    JOIN parceiros p ON cp.parceiro_id = p.id
                    WHERE cp.cotacao_id = ?
                    ORDER BY cp.valor ASC
                ");
                $stmt->execute([$cotacao['id']]);
                $cotacao['parceiros'] = $stmt->fetchAll();
            }
            
            echo json_encode($cotacao ?: null);
            
        } else {
            // Listar todas as cotações (com filtros)
            $where = [];
            $params = [];
            
            // Filtrar por status
            if (isset($_GET['status'])) {
                $where[] = 'c.status = ?';
                $params[] = $_GET['status'];
            }
            
            // Se não for gestor, mostrar apenas suas cotações
            if (!isGestor()) {
                $where[] = 'v.vendedor = ?';
                $params[] = $_SESSION['usuario_nome'];
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "
                SELECT c.*, v.cliente, v.endereco, v.vendedor,
                       COUNT(DISTINCT cp.id) as total_parceiros,
                       COUNT(DISTINCT CASE WHEN cp.valor IS NOT NULL THEN cp.id END) as parceiros_responderam
                FROM cotacoes c
                JOIN vistorias v ON c.vistoria_id = v.id
                LEFT JOIN cotacoes_parceiros cp ON c.id = cp.cotacao_id
                $whereClause
                GROUP BY c.id
                ORDER BY c.data_criacao DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cotacoes = $stmt->fetchAll();
            
            echo json_encode($cotacoes);
        }
        break;
        
    case 'POST':
        // Criar nova cotação
        $data = json_decode(file_get_contents('php://input'), true);
        $vistoriaId = $data['vistoria_id'] ?? 0;
        
        // Validar vistoria
        $stmt = $pdo->prepare("
            SELECT * FROM vistorias 
            WHERE id = ? AND status = 'Concluída' AND arquivo_lista_seguro IS NOT NULL
        ");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(400);
            echo json_encode(['error' => 'Vistoria inválida ou não está pronta para cotação']);
            exit;
        }
        
        // Verificar permissão
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        
        // Verificar se já existe cotação
        $stmt = $pdo->prepare("SELECT id FROM cotacoes WHERE vistoria_id = ?");
        $stmt->execute([$vistoriaId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Já existe uma cotação para esta vistoria']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Criar cotação
            $stmt = $pdo->prepare("
                INSERT INTO cotacoes (vistoria_id, responsavel_id, status, mapa_cotacao) 
                VALUES (?, ?, 'Aguardando_Parceiros', ?)
            ");
            
            // Criar mapa da cotação com informações relevantes
            $mapaCotacao = json_encode([
                'cliente' => $vistoria['cliente'],
                'endereco' => $vistoria['endereco'],
                'tipo_imovel' => $vistoria['tipo_imovel'],
                'data_vistoria' => $vistoria['data_vistoria'],
                'arquivo_lista' => $vistoria['arquivo_lista_seguro'],
                'observacoes' => $vistoria['observacoes']
            ]);
            
            $stmt->execute([$vistoriaId, $_SESSION['usuario_id'], $mapaCotacao]);
            $cotacaoId = $pdo->lastInsertId();
            
            // Enviar para parceiros
            $totalParceiros = enviarCotacaoParceiros($cotacaoId, $vistoria, $pdo);
            
            // Atualizar status da vistoria
            $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Enviada_Cotacao' WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Registrar histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('vistorias', ?, 'Concluída', 'Enviada_Cotacao', ?, ?)
            ");
            $stmt->execute([
                $vistoriaId, 
                $_SESSION['usuario_id'],
                "Cotação enviada para $totalParceiros parceiros"
            ]);
            
            // Criar notificações
            if (isGestor()) {
                // Notificar o vendedor
                $stmt = $pdo->prepare("
                    SELECT id FROM usuarios WHERE nome = ? AND tipo = 'vendedor'
                ");
                $stmt->execute([$vistoria['vendedor']]);
                $vendedor = $stmt->fetch();
                
                if ($vendedor) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                        VALUES (?, 'cotacao_enviada', 'Cotação Enviada', ?)
                    ");
                    $stmt->execute([
                        $vendedor['id'],
                        "A vistoria do cliente {$vistoria['cliente']} foi enviada para cotação"
                    ]);
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'cotacao_id' => $cotacaoId,
                'parceiros_notificados' => $totalParceiros,
                'message' => 'Cotação criada e enviada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar cotação: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        // Atualizar cotação
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['aprovar'])) {
            // Aprovar cotação com valor específico
            if (!isGestor()) {
                http_response_code(403);
                echo json_encode(['error' => 'Apenas gestores podem aprovar cotações']);
                exit;
            }
            
            $cotacaoId = $data['cotacao_id'] ?? 0;
            $parceiroId = $data['parceiro_id'] ?? 0;
            $valorAprovado = $data['valor_aprovado'] ?? 0;
            
            try {
                $pdo->beginTransaction();
                
                // Buscar cotação
                $stmt = $pdo->prepare("
                    SELECT c.*, v.id as vistoria_id, v.cliente, v.vendedor 
                    FROM cotacoes c
                    JOIN vistorias v ON c.vistoria_id = v.id
                    WHERE c.id = ? AND c.status != 'Aprovada'
                ");
                $stmt->execute([$cotacaoId]);
                $cotacao = $stmt->fetch();
                
                if (!$cotacao) {
                    throw new Exception('Cotação não encontrada ou já aprovada');
                }
                
                // Marcar parceiro como selecionado
                $stmt = $pdo->prepare("
                    UPDATE cotacoes_parceiros 
                    SET selecionado = CASE WHEN parceiro_id = ? THEN 1 ELSE 0 END 
                    WHERE cotacao_id = ?
                ");
                $stmt->execute([$parceiroId, $cotacaoId]);
                
                // Atualizar cotação
                $stmt = $pdo->prepare("
                    UPDATE cotacoes 
                    SET status = 'Aprovada', 
                        valor_aprovado = ?, 
                        data_aprovacao = NOW(),
                        parceiro_aprovado_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$valorAprovado, $parceiroId, $cotacaoId]);
                
                // Atualizar vistoria
                $stmt = $pdo->prepare("
                    UPDATE vistorias 
                    SET status = 'Cotacao_Aprovada',
                        valor_aprovado = ?
                    WHERE id = ?
                ");
                $stmt->execute([$valorAprovado, $cotacao['vistoria_id']]);
                
                // Registrar histórico
                $stmt = $pdo->prepare("
                    INSERT INTO historico_status 
                    (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                    VALUES ('vistorias', ?, 'Enviada_Cotacao', 'Cotacao_Aprovada', ?, ?)
                ");
                $stmt->execute([
                    $cotacao['vistoria_id'],
                    $_SESSION['usuario_id'],
                    'Cotação aprovada - Valor: R$ ' . number_format($valorAprovado, 2, ',', '.')
                ]);
                
                // Notificar vendedor
                $stmt = $pdo->prepare("
                    SELECT id FROM usuarios WHERE nome = ? AND tipo = 'vendedor'
                ");
                $stmt->execute([$cotacao['vendedor']]);
                $vendedor = $stmt->fetch();
                
                if ($vendedor) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                        VALUES (?, 'cotacao_aprovada', 'Cotação Aprovada', ?)
                    ");
                    $stmt->execute([
                        $vendedor['id'],
                        "Cotação do cliente {$cotacao['cliente']} aprovada. Valor: R$ " . 
                        number_format($valorAprovado, 2, ',', '.')
                    ]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cotação aprovada com sucesso'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            
        } elseif (isset($data['rejeitar'])) {
            // Rejeitar cotação
            if (!isGestor()) {
                http_response_code(403);
                echo json_encode(['error' => 'Apenas gestores podem rejeitar cotações']);
                exit;
            }
            
            $cotacaoId = $data['cotacao_id'] ?? 0;
            $motivo = $data['motivo'] ?? 'Não informado';
            
            try {
                $pdo->beginTransaction();
                
                // Buscar cotação
                $stmt = $pdo->prepare("
                    SELECT c.*, v.id as vistoria_id 
                    FROM cotacoes c
                    JOIN vistorias v ON c.vistoria_id = v.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$cotacaoId]);
                $cotacao = $stmt->fetch();
                
                if (!$cotacao) {
                    throw new Exception('Cotação não encontrada');
                }
                
                // Atualizar cotação
                $stmt = $pdo->prepare("
                    UPDATE cotacoes 
                    SET status = 'Rejeitada', 
                        motivo_rejeicao = ?,
                        data_rejeicao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$motivo, $cotacaoId]);
                
                // Voltar vistoria para status anterior
                $stmt = $pdo->prepare("
                    UPDATE vistorias SET status = 'Concluída' WHERE id = ?
                ");
                $stmt->execute([$cotacao['vistoria_id']]);
                
                // Registrar histórico
                $stmt = $pdo->prepare("
                    INSERT INTO historico_status 
                    (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                    VALUES ('vistorias', ?, 'Enviada_Cotacao', 'Concluída', ?, ?)
                ");
                $stmt->execute([
                    $cotacao['vistoria_id'],
                    $_SESSION['usuario_id'],
                    'Cotação rejeitada. Motivo: ' . $motivo
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cotação rejeitada'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            
        } elseif (isset($data['reenviar'])) {
            // Reenviar cotação para parceiros pendentes
            $cotacaoId = $data['cotacao_id'] ?? 0;
            
            try {
                // Buscar parceiros que não responderam
                $stmt = $pdo->prepare("
                    SELECT cp.*, p.email, p.nome
                    FROM cotacoes_parceiros cp
                    JOIN parceiros p ON cp.parceiro_id = p.id
                    WHERE cp.cotacao_id = ? AND cp.valor IS NULL
                ");
                $stmt->execute([$cotacaoId]);
                $parceirosPendentes = $stmt->fetchAll();
                
                $reenviados = 0;
                foreach ($parceirosPendentes as $parceiro) {
                    // Simular reenvio de email
                    $stmt = $pdo->prepare("
                        INSERT INTO logs_email (destinatario, assunto, tipo, status) 
                        VALUES (?, ?, 'cotacao_reenvio', 'Enviado')
                    ");
                    $stmt->execute([
                        $parceiro['email'],
                        'Lembrete: Solicitação de Cotação Pendente'
                    ]);
                    $reenviados++;
                }
                
                echo json_encode([
                    'success' => true,
                    'parceiros_notificados' => $reenviados,
                    'message' => "Cotação reenviada para $reenviados parceiros"
                ]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
        }
        break;
        
    case 'DELETE':
        // Cancelar cotação
        if (!isGestor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas gestores podem cancelar cotações']);
            exit;
        }
        
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da cotação não fornecido']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Buscar cotação
            $stmt = $pdo->prepare("
                SELECT c.*, v.id as vistoria_id 
                FROM cotacoes c
                JOIN vistorias v ON c.vistoria_id = v.id
                WHERE c.id = ? AND c.status NOT IN ('Aprovada', 'Cancelada')
            ");
            $stmt->execute([$_GET['id']]);
            $cotacao = $stmt->fetch();
            
            if (!$cotacao) {
                throw new Exception('Cotação não pode ser cancelada');
            }
            
            // Cancelar cotação
            $stmt = $pdo->prepare("
                UPDATE cotacoes SET status = 'Cancelada' WHERE id = ?
            ");
            $stmt->execute([$_GET['id']]);
            
            // Voltar vistoria para concluída
            $stmt = $pdo->prepare("
                UPDATE vistorias SET status = 'Concluída' WHERE id = ?
            ");
            $stmt->execute([$cotacao['vistoria_id']]);
            
            // Registrar histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('vistorias', ?, 'Enviada_Cotacao', 'Concluída', ?, 'Cotação cancelada')
            ");
            $stmt->execute([$cotacao['vistoria_id'], $_SESSION['usuario_id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cotação cancelada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
}
?>