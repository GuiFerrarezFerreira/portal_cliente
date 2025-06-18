<?php
// api/cotacao.php - Sistema de Cotação Completo
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Função para enviar notificação
function enviarNotificacao($usuarioId, $tipo, $titulo, $mensagem) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuarioId, $tipo, $titulo, $mensagem]);
}

// Função para registrar histórico
function registrarHistorico($tabela, $registroId, $statusAnterior, $statusNovo, $observacoes = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tabela, $registroId, $statusAnterior, $statusNovo, $_SESSION['usuario_id'], $observacoes]);
}

// Função para enviar email para parceiros
function enviarEmailParceiros($cotacaoId, $vistoria) {
    global $pdo;
    
    // Buscar parceiros ativos
    $stmt = $pdo->query("SELECT * FROM parceiros WHERE ativo = 1");
    $parceiros = $stmt->fetchAll();
    
    foreach ($parceiros as $parceiro) {
        // Gerar token único para resposta
        $token = bin2hex(random_bytes(32));
        
        // Criar registro de cotação do parceiro
        try {
            $stmt = $pdo->prepare("INSERT INTO cotacoes_parceiros (cotacao_id, parceiro_id, token_resposta) VALUES (?, ?, ?)");
            $stmt->execute([$cotacaoId, $parceiro['id'], $token]);
            
            // Enviar email (simplificado - em produção usar PHPMailer)
            $linkResposta = "http://localhost/sistema-mudancas/parceiro-cotacao.php?token=" . $token;
            $assunto = "Nova Solicitação de Cotação - Cliente: " . $vistoria['cliente'];
            
            $mensagem = "
            <html>
            <body>
                <h2>Nova Solicitação de Cotação</h2>
                <p>Prezado parceiro {$parceiro['nome']},</p>
                <p>Recebemos uma nova solicitação de cotação com os seguintes detalhes:</p>
                
                <h3>Informações da Mudança:</h3>
                <ul>
                    <li><strong>Cliente:</strong> {$vistoria['cliente']}</li>
                    <li><strong>Endereço:</strong> {$vistoria['endereco']}</li>
                    <li><strong>Tipo de Imóvel:</strong> {$vistoria['tipo_imovel']}</li>
                    <li><strong>Data da Vistoria:</strong> " . date('d/m/Y', strtotime($vistoria['data_vistoria'])) . "</li>
                </ul>
                
                <p>Para visualizar a lista de seguro e enviar sua cotação, acesse:</p>
                <p><a href='{$linkResposta}' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ACESSAR SISTEMA DE COTAÇÃO</a></p>
                
                <p>Prazo para resposta: 48 horas</p>
                
                <p>Atenciosamente,<br>Sistema de Mudanças</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: cotacao@sistema-mudancas.com' . "\r\n";
            
            @mail($parceiro['email'], $assunto, $mensagem, $headers);
            
        } catch (Exception $e) {
            // Log do erro
            error_log("Erro ao criar cotação para parceiro {$parceiro['id']}: " . $e->getMessage());
        }
    }
}

// Função para calcular estatísticas de cotação
function calcularEstatisticasCotacao($cotacaoId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_parceiros,
            COUNT(CASE WHEN valor IS NOT NULL THEN 1 END) as parceiros_responderam,
            MIN(valor) as menor_valor,
            MAX(valor) as maior_valor,
            AVG(valor) as media_valor,
            MIN(prazo_dias) as menor_prazo,
            MAX(prazo_dias) as maior_prazo
        FROM cotacoes_parceiros 
        WHERE cotacao_id = ?
    ");
    $stmt->execute([$cotacaoId]);
    return $stmt->fetch();
}

switch($method) {
    case 'POST':
        // Enviar vistoria para cotação
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vistoria não fornecido']);
            exit;
        }
        
        $vistoriaId = $data['vistoria_id'];
        $observacoes = $data['observacoes'] ?? '';
        $prazoResposta = $data['prazo_resposta'] ?? 48; // Horas
        
        // Verificar vistoria
        $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ? AND status = 'Concluída' AND arquivo_lista_seguro IS NOT NULL");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(400);
            echo json_encode(['error' => 'Vistoria não encontrada, não está concluída ou não possui arquivo anexado']);
            exit;
        }
        
        // Verificar permissão
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para enviar esta vistoria para cotação']);
            exit;
        }
        
        // Verificar se já existe cotação ativa
        $stmt = $pdo->prepare("SELECT id, status FROM cotacoes WHERE vistoria_id = ? AND status NOT IN ('Rejeitada', 'Cancelada')");
        $stmt->execute([$vistoriaId]);
        $cotacaoExistente = $stmt->fetch();
        
        if ($cotacaoExistente) {
            http_response_code(400);
            echo json_encode(['error' => 'Já existe uma cotação ativa para esta vistoria']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Criar cotação
            $stmt = $pdo->prepare("
                INSERT INTO cotacoes (vistoria_id, responsavel_id, status, observacoes, prazo_resposta_horas) 
                VALUES (?, ?, 'Aguardando_Parceiros', ?, ?)
            ");
            $stmt->execute([$vistoriaId, $_SESSION['usuario_id'], $observacoes, $prazoResposta]);
            $cotacaoId = $pdo->lastInsertId();
            
            // Atualizar status da vistoria
            $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Enviada_Cotacao' WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Registrar histórico
            registrarHistorico('vistorias', $vistoriaId, 'Concluída', 'Enviada_Cotacao', 
                             'Vistoria enviada para cotação. Prazo: ' . $prazoResposta . ' horas');
            
            // Enviar emails para parceiros
            enviarEmailParceiros($cotacaoId, $vistoria);
            
            // Notificar cotadores
            $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo = 'cotador' AND ativo = 1");
            $cotadores = $stmt->fetchAll();
            
            foreach ($cotadores as $cotador) {
                enviarNotificacao($cotador['id'], 'nova_cotacao', 'Nova Vistoria para Cotação',
                                "Vistoria #{$vistoriaId} - Cliente: {$vistoria['cliente']} foi enviada para cotação.");
            }
            
            // Agendar verificação de prazo (seria um cron job em produção)
            $stmt = $pdo->prepare("
                INSERT INTO tarefas_agendadas (tipo, dados, executar_em) 
                VALUES ('verificar_prazo_cotacao', ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
            ");
            $stmt->execute([json_encode(['cotacao_id' => $cotacaoId]), $prazoResposta]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'cotacao_id' => $cotacaoId,
                'message' => 'Vistoria enviada para cotação com sucesso',
                'parceiros_notificados' => count($parceiros ?? [])
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao processar cotação: ' . $e->getMessage()]);
        }
        break;
        
    case 'GET':
        if (isset($_GET['id'])) {
            // Buscar cotação específica
            $cotacaoId = $_GET['id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, v.cliente, v.endereco, v.tipo_imovel, u.nome as responsavel_nome
                FROM cotacoes c
                JOIN vistorias v ON c.vistoria_id = v.id
                LEFT JOIN usuarios u ON c.responsavel_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$cotacaoId]);
            $cotacao = $stmt->fetch();
            
            if (!$cotacao) {
                http_response_code(404);
                echo json_encode(['error' => 'Cotação não encontrada']);
                exit;
            }
            
            // Buscar estatísticas
            $cotacao['estatisticas'] = calcularEstatisticasCotacao($cotacaoId);
            
            // Buscar respostas dos parceiros
            $stmt = $pdo->prepare("
                SELECT cp.*, p.nome as parceiro_nome, p.email as parceiro_email
                FROM cotacoes_parceiros cp
                JOIN parceiros p ON cp.parceiro_id = p.id
                WHERE cp.cotacao_id = ?
                ORDER BY cp.valor ASC, cp.data_resposta DESC
            ");
            $stmt->execute([$cotacaoId]);
            $cotacao['parceiros'] = $stmt->fetchAll();
            
            // Calcular tempo restante para respostas
            $dataCriacao = new DateTime($cotacao['data_criacao']);
            $prazoFinal = clone $dataCriacao;
            $prazoFinal->add(new DateInterval('PT' . $cotacao['prazo_resposta_horas'] . 'H'));
            $agora = new DateTime();
            
            if ($agora < $prazoFinal) {
                $intervalo = $agora->diff($prazoFinal);
                $cotacao['horas_restantes'] = ($intervalo->days * 24) + $intervalo->h;
            } else {
                $cotacao['horas_restantes'] = 0;
            }
            
            echo json_encode($cotacao);
            
        } else if (isset($_GET['vistoria_id'])) {
            // Buscar cotações de uma vistoria
            $vistoriaId = $_GET['vistoria_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, u.nome as responsavel_nome
                FROM cotacoes c
                LEFT JOIN usuarios u ON c.responsavel_id = u.id
                WHERE c.vistoria_id = ?
                ORDER BY c.data_criacao DESC
            ");
            $stmt->execute([$vistoriaId]);
            $cotacoes = $stmt->fetchAll();
            
            // Para cada cotação, buscar estatísticas
            foreach ($cotacoes as &$cotacao) {
                $cotacao['estatisticas'] = calcularEstatisticasCotacao($cotacao['id']);
            }
            
            echo json_encode($cotacoes);
            
        } else {
            // Listar todas as cotações (com filtros)
            $status = $_GET['status'] ?? null;
            $responsavelId = $_GET['responsavel_id'] ?? null;
            
            $sql = "SELECT c.*, v.cliente, v.endereco, u.nome as responsavel_nome
                    FROM cotacoes c
                    JOIN vistorias v ON c.vistoria_id = v.id
                    LEFT JOIN usuarios u ON c.responsavel_id = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND c.status = ?";
                $params[] = $status;
            }
            
            if ($responsavelId) {
                $sql .= " AND c.responsavel_id = ?";
                $params[] = $responsavelId;
            }
            
            // Se não for gestor ou cotador, mostrar apenas suas cotações
            if (!isGestor() && $_SESSION['usuario_tipo'] !== 'cotador') {
                $sql .= " AND v.vendedor = ?";
                $params[] = $_SESSION['usuario_nome'];
            }
            
            $sql .= " ORDER BY c.data_criacao DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cotacoes = $stmt->fetchAll();
            
            echo json_encode($cotacoes);
        }
        break;
        
    case 'PUT':
        // Atualizar cotação
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id']) || !isset($data['acao'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            exit;
        }
        
        $cotacaoId = $data['id'];
        $acao = $data['acao'];
        
        // Buscar cotação
        $stmt = $pdo->prepare("SELECT * FROM cotacoes WHERE id = ?");
        $stmt->execute([$cotacaoId]);
        $cotacao = $stmt->fetch();
        
        if (!$cotacao) {
            http_response_code(404);
            echo json_encode(['error' => 'Cotação não encontrada']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            switch ($acao) {
                case 'aprovar':
                    // Apenas gestores podem aprovar
                    if (!isGestor()) {
                        throw new Exception('Sem permissão para aprovar cotações');
                    }
                    
                    if (!isset($data['parceiro_id']) || !isset($data['valor_aprovado'])) {
                        throw new Exception('Parceiro e valor devem ser informados');
                    }
                    
                    // Verificar se há respostas
                    $stats = calcularEstatisticasCotacao($cotacaoId);
                    if ($stats['parceiros_responderam'] == 0) {
                        throw new Exception('Nenhum parceiro respondeu à cotação');
                    }
                    
                    // Atualizar cotação
                    $stmt = $pdo->prepare("
                        UPDATE cotacoes SET 
                            status = 'Aprovada',
                            parceiro_aprovado_id = ?,
                            valor_aprovado = ?,
                            data_aprovacao = NOW(),
                            aprovado_por = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $data['parceiro_id'],
                        $data['valor_aprovado'],
                        $_SESSION['usuario_id'],
                        $cotacaoId
                    ]);
                    
                    // Atualizar status da vistoria
                    $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Cotacao_Aprovada' WHERE id = ?");
                    $stmt->execute([$cotacao['vistoria_id']]);
                    
                    // Registrar histórico
                    registrarHistorico('cotacoes', $cotacaoId, $cotacao['status'], 'Aprovada',
                                     'Valor aprovado: R$ ' . number_format($data['valor_aprovado'], 2, ',', '.'));
                    
                    // Notificar vendedor
                    $stmt = $pdo->prepare("SELECT vendedor_id FROM vistorias WHERE id = ?");
                    $stmt->execute([$cotacao['vistoria_id']]);
                    $vistoria = $stmt->fetch();
                    
                    if ($vistoria['vendedor_id']) {
                        enviarNotificacao($vistoria['vendedor_id'], 'cotacao_aprovada', 
                                        'Cotação Aprovada',
                                        'A cotação #' . $cotacaoId . ' foi aprovada. Você já pode criar a proposta.');
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Cotação aprovada com sucesso']);
                    break;
                    
                case 'rejeitar':
                    // Gestor ou cotador podem rejeitar
                    if (!isGestor() && $_SESSION['usuario_tipo'] !== 'cotador') {
                        throw new Exception('Sem permissão para rejeitar cotações');
                    }
                    
                    $motivo = $data['motivo'] ?? 'Não informado';
                    
                    // Atualizar cotação
                    $stmt = $pdo->prepare("
                        UPDATE cotacoes SET 
                            status = 'Rejeitada',
                            motivo_rejeicao = ?,
                            data_rejeicao = NOW(),
                            rejeitado_por = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$motivo, $_SESSION['usuario_id'], $cotacaoId]);
                    
                    // Voltar status da vistoria
                    $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Concluída' WHERE id = ?");
                    $stmt->execute([$cotacao['vistoria_id']]);
                    
                    // Registrar histórico
                    registrarHistorico('cotacoes', $cotacaoId, $cotacao['status'], 'Rejeitada',
                                     'Motivo: ' . $motivo);
                    
                    echo json_encode(['success' => true, 'message' => 'Cotação rejeitada']);
                    break;
                    
                case 'reenviar':
                    // Reenviar para parceiros que não responderam
                    if (!isGestor() && $_SESSION['usuario_tipo'] !== 'cotador') {
                        throw new Exception('Sem permissão para reenviar cotações');
                    }
                    
                    // Buscar parceiros que não responderam
                    $stmt = $pdo->prepare("
                        SELECT cp.*, p.nome, p.email
                        FROM cotacoes_parceiros cp
                        JOIN parceiros p ON cp.parceiro_id = p.id
                        WHERE cp.cotacao_id = ? AND cp.valor IS NULL
                    ");
                    $stmt->execute([$cotacaoId]);
                    $parceirosNaoResponderam = $stmt->fetchAll();
                    
                    // Reenviar emails
                    foreach ($parceirosNaoResponderam as $parceiro) {
                        // Reenviar email (implementar)
                    }
                    
                    // Estender prazo
                    $novoPrazo = $data['novo_prazo_horas'] ?? 24;
                    $stmt = $pdo->prepare("
                        UPDATE cotacoes SET 
                            prazo_resposta_horas = prazo_resposta_horas + ?,
                            reenvios = reenvios + 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$novoPrazo, $cotacaoId]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cotação reenviada para ' . count($parceirosNaoResponderam) . ' parceiros'
                    ]);
                    break;
                    
                case 'cancelar':
                    // Apenas gestor pode cancelar
                    if (!isGestor()) {
                        throw new Exception('Sem permissão para cancelar cotações');
                    }
                    
                    $motivo = $data['motivo'] ?? 'Não informado';
                    
                    // Atualizar cotação
                    $stmt = $pdo->prepare("
                        UPDATE cotacoes SET 
                            status = 'Cancelada',
                            motivo_cancelamento = ?,
                            data_cancelamento = NOW(),
                            cancelado_por = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$motivo, $_SESSION['usuario_id'], $cotacaoId]);
                    
                    // Voltar status da vistoria
                    $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Concluída' WHERE id = ?");
                    $stmt->execute([$cotacao['vistoria_id']]);
                    
                    echo json_encode(['success' => true, 'message' => 'Cotação cancelada']);
                    break;
                    
                default:
                    throw new Exception('Ação não reconhecida');
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Deletar cotação (apenas se estiver em rascunho)
        if (!isGestor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para excluir cotações']);
            exit;
        }
        
        $cotacaoId = $_GET['id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT status FROM cotacoes WHERE id = ?");
        $stmt->execute([$cotacaoId]);
        $cotacao = $stmt->fetch();
        
        if (!$cotacao) {
            http_response_code(404);
            echo json_encode(['error' => 'Cotação não encontrada']);
            exit;
        }
        
        if (!in_array($cotacao['status'], ['Rascunho', 'Cancelada', 'Rejeitada'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Apenas cotações em rascunho, canceladas ou rejeitadas podem ser excluídas']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Deletar registros relacionados
            $stmt = $pdo->prepare("DELETE FROM cotacoes_parceiros WHERE cotacao_id = ?");
            $stmt->execute([$cotacaoId]);
            
            // Deletar cotação
            $stmt = $pdo->prepare("DELETE FROM cotacoes WHERE id = ?");
            $stmt->execute([$cotacaoId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Cotação excluída com sucesso']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir cotação: ' . $e->getMessage()]);
        }
        break;
}
?>