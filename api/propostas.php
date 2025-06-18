<?php
// api/propostas.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Função para gerar token único
function gerarToken() {
    return bin2hex(random_bytes(32));
}

// Função para enviar email (simplificada - em produção usar PHPMailer)
function enviarEmailProposta($destinatario, $proposta, $vistoria) {
    global $pdo;
    
    $assunto = "Proposta de Mudança - " . $vistoria['cliente'];
    $linkAceite = "http://localhost/sistema-mudancas/aceitar-proposta.php?token=" . $proposta['token_aceite'];
    
    $mensagem = "
    <html>
    <body>
        <h2>Proposta de Mudança</h2>
        <p>Prezado(a) {$vistoria['cliente']},</p>
        <p>Segue abaixo a proposta para sua mudança:</p>
        
        <h3>Detalhes do Serviço:</h3>
        <p><strong>Endereço:</strong> {$vistoria['endereco']}</p>
        <p><strong>Tipo de Imóvel:</strong> {$vistoria['tipo_imovel']}</p>
        <p><strong>Data da Vistoria:</strong> " . date('d/m/Y', strtotime($vistoria['data_vistoria'])) . "</p>
        
        <h3>Valor Total: R$ " . number_format($proposta['valor_total'], 2, ',', '.') . "</h3>
        
        <h3>Descrição dos Serviços:</h3>
        <p>" . nl2br($proposta['descricao_servicos']) . "</p>
        
        <p><strong>Validade da Proposta:</strong> {$proposta['validade_dias']} dias</p>
        
        <p>Para aceitar esta proposta, clique no link abaixo:</p>
        <p><a href='{$linkAceite}' style='background-color: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ACEITAR PROPOSTA</a></p>
        
        <p>Ou copie e cole este link no seu navegador:</p>
        <p>{$linkAceite}</p>
        
        <p>Atenciosamente,<br>Equipe de Mudanças</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: noreply@sistema-mudancas.com' . "\r\n";
    
    $enviado = @mail($destinatario, $assunto, $mensagem, $headers);
    
    // Registrar log de email
    $stmt = $pdo->prepare("INSERT INTO logs_email (destinatario, assunto, tipo, status, erro) VALUES (?, ?, 'proposta', ?, ?)");
    $stmt->execute([$destinatario, $assunto, $enviado ? 'Enviado' : 'Erro', $enviado ? null : 'Erro ao enviar email']);
    
    return $enviado;
}

switch($method) {
    case 'POST':
        // Criar nova proposta
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['vistoria_id']) || !isset($data['valor_total']) || !isset($data['descricao_servicos'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            exit;
        }
        
        // Verificar se vistoria tem cotação aprovada
        $stmt = $pdo->prepare("
            SELECT v.*, c.valor_aprovado 
            FROM vistorias v 
            JOIN cotacoes c ON v.id = c.vistoria_id 
            WHERE v.id = ? AND v.status = 'Cotacao_Aprovada' AND c.status = 'Aprovada'
        ");
        $stmt->execute([$data['vistoria_id']]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(400);
            echo json_encode(['error' => 'Vistoria não encontrada ou sem cotação aprovada']);
            exit;
        }
        
        // Verificar permissão
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para criar proposta para esta vistoria']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Criar proposta
            $token = gerarToken();
            $stmt = $pdo->prepare("
                INSERT INTO propostas (vistoria_id, valor_total, descricao_servicos, validade_dias, token_aceite, status) 
                VALUES (?, ?, ?, ?, ?, 'Criada')
            ");
            $stmt->execute([
                $data['vistoria_id'],
                $data['valor_total'],
                $data['descricao_servicos'],
                $data['validade_dias'] ?? 30,
                $token
            ]);
            $propostaId = $pdo->lastInsertId();
            
            // Se solicitado, enviar por email
            if ($data['enviar_email'] ?? false) {
                if (!$vistoria['email']) {
                    throw new Exception('Cliente não possui email cadastrado');
                }
                
                $proposta = [
                    'id' => $propostaId,
                    'valor_total' => $data['valor_total'],
                    'descricao_servicos' => $data['descricao_servicos'],
                    'validade_dias' => $data['validade_dias'] ?? 30,
                    'token_aceite' => $token
                ];
                
                if (enviarEmailProposta($vistoria['email'], $proposta, $vistoria)) {
                    // Atualizar status da proposta
                    $stmt = $pdo->prepare("UPDATE propostas SET status = 'Enviada', data_envio = NOW() WHERE id = ?");
                    $stmt->execute([$propostaId]);
                    
                    // Atualizar status da vistoria
                    $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Proposta_Enviada' WHERE id = ?");
                    $stmt->execute([$data['vistoria_id']]);
                    
                    // Registrar histórico
                    $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                                          VALUES ('vistorias', ?, 'Cotacao_Aprovada', 'Proposta_Enviada', ?, 'Proposta enviada por email')");
                    $stmt->execute([$data['vistoria_id'], $_SESSION['usuario_id']]);
                } else {
                    throw new Exception('Erro ao enviar email');
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'proposta_id' => $propostaId,
                'message' => 'Proposta criada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar proposta: ' . $e->getMessage()]);
        }
        break;
        
    case 'GET':
        // Obter proposta
        if (!isset($_GET['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vistoria não fornecido']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM propostas WHERE vistoria_id = ? ORDER BY data_criacao DESC LIMIT 1");
        $stmt->execute([$_GET['vistoria_id']]);
        $proposta = $stmt->fetch();
        
        echo json_encode($proposta ?: null);
        break;
        
    case 'PUT':
        // Processar aceite da proposta
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Token não fornecido']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Buscar proposta pelo token
            $stmt = $pdo->prepare("
                SELECT p.*, v.cliente, v.cpf, v.email, v.telefone 
                FROM propostas p 
                JOIN vistorias v ON p.vistoria_id = v.id 
                WHERE p.token_aceite = ? AND p.status = 'Enviada'
            ");
            $stmt->execute([$data['token']]);
            $proposta = $stmt->fetch();
            
            if (!$proposta) {
                throw new Exception('Proposta não encontrada ou já processada');
            }
            
            // Verificar validade
            $dataValidade = new DateTime($proposta['data_criacao']);
            $dataValidade->add(new DateInterval('P' . $proposta['validade_dias'] . 'D'));
            if ($dataValidade < new DateTime()) {
                throw new Exception('Proposta expirada');
            }
            
            // Criar usuário cliente se não existir
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$proposta['email']]);
            $clienteExistente = $stmt->fetch();
            
            if (!$clienteExistente) {
                // Criar novo usuário cliente
                $senhaTemporaria = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, telefone, tipo) VALUES (?, ?, ?, ?, 'cliente')");
                $stmt->execute([$proposta['cliente'], $proposta['email'], $senhaTemporaria, $proposta['telefone']]);
                $clienteId = $pdo->lastInsertId();
                
                // TODO: Enviar email com credenciais de acesso
            } else {
                $clienteId = $clienteExistente['id'];
            }
            
            // Atualizar proposta
            $stmt = $pdo->prepare("UPDATE propostas SET status = 'Aceita', data_aceite = NOW(), ip_aceite = ? WHERE id = ?");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $proposta['id']]);
            
            // Atualizar vistoria
            $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Proposta_Aceita' WHERE id = ?");
            $stmt->execute([$proposta['vistoria_id']]);
            
            // Criar registro de mudança
            $stmt = $pdo->prepare("
                INSERT INTO mudancas (vistoria_id, proposta_id, cliente_id, status) 
                VALUES (?, ?, ?, 'Aguardando_Documentos')
            ");
            $stmt->execute([$proposta['vistoria_id'], $proposta['id'], $clienteId]);
            $mudancaId = $pdo->lastInsertId();
            
            // Registrar histórico
            $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                                  VALUES ('vistorias', ?, 'Proposta_Enviada', 'Proposta_Aceita', ?, 'Proposta aceita pelo cliente')");
            $stmt->execute([$proposta['vistoria_id'], $clienteId]);
            
            // Criar notificação para gestores
            $stmt = $pdo->query("SELECT id FROM usuarios WHERE tipo = 'gestor' AND ativo = 1");
            $gestores = $stmt->fetchAll();
            
            foreach ($gestores as $gestor) {
                $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                                      VALUES (?, 'proposta_aceita', 'Proposta Aceita', ?)");
                $mensagem = "O cliente {$proposta['cliente']} aceitou a proposta #{$proposta['id']}. É necessário definir um coordenador.";
                $stmt->execute([$gestor['id'], $mensagem]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'mudanca_id' => $mudancaId,
                'message' => 'Proposta aceita com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao processar aceite: ' . $e->getMessage()]);
        }
        break;
}
?>