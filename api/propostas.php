<?php
// api/propostas.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

// Classe para gerenciar propostas
class PropostaManager {
    private $pdo;
    private $usuario_id;
    private $usuario_tipo;
    private $usuario_nome;
    
    public function __construct($pdo, $usuario) {
        $this->pdo = $pdo;
        $this->usuario_id = $usuario['id'];
        $this->usuario_tipo = $usuario['tipo'];
        $this->usuario_nome = $usuario['nome'];
    }
    
    // Gerar token único e seguro
    private function gerarToken() {
        return bin2hex(random_bytes(32));
    }
    
    // Verificar se usuário pode criar proposta para vistoria
    private function podeGerenciarProposta($vistoria) {
        return isGestor() || $vistoria['vendedor'] === $this->usuario_nome;
    }
    
    // Criar nova proposta
    public function criar($data) {
        // Validar dados obrigatórios
        $camposObrigatorios = ['vistoria_id', 'valor_total', 'descricao_servicos'];
        foreach ($camposObrigatorios as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                throw new Exception("Campo obrigatório ausente: $campo");
            }
        }
        
        // Validar valor
        if ($data['valor_total'] <= 0) {
            throw new Exception('Valor total deve ser maior que zero');
        }
        
        // Buscar vistoria e verificar status
        $stmt = $this->pdo->prepare("
            SELECT v.*, c.valor_aprovado, c.id as cotacao_id
            FROM vistorias v 
            LEFT JOIN cotacoes c ON v.id = c.vistoria_id 
            WHERE v.id = ? AND v.status IN ('Cotacao_Aprovada', 'Concluída')
        ");
        $stmt->execute([$data['vistoria_id']]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            throw new Exception('Vistoria não encontrada ou não está em status adequado para criar proposta');
        }
        
        // Verificar permissão
        if (!$this->podeGerenciarProposta($vistoria)) {
            throw new Exception('Sem permissão para criar proposta para esta vistoria');
        }
        
        // Verificar se já existe proposta ativa
        $stmt = $this->pdo->prepare("
            SELECT id FROM propostas 
            WHERE vistoria_id = ? AND status IN ('Criada', 'Enviada', 'Aceita')
        ");
        $stmt->execute([$data['vistoria_id']]);
        if ($stmt->fetch()) {
            throw new Exception('Já existe uma proposta ativa para esta vistoria');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Criar proposta
            $token = $this->gerarToken();
            $stmt = $this->pdo->prepare("
                INSERT INTO propostas (
                    vistoria_id, 
                    valor_total, 
                    descricao_servicos, 
                    validade_dias, 
                    token_aceite, 
                    status,
                    criado_por
                ) VALUES (?, ?, ?, ?, ?, 'Criada', ?)
            ");
            
            $stmt->execute([
                $data['vistoria_id'],
                $data['valor_total'],
                $data['descricao_servicos'],
                $data['validade_dias'] ?? 30,
                $token,
                $this->usuario_id
            ]);
            
            $propostaId = $this->pdo->lastInsertId();
            
            // Se marcado para enviar por email
            if ($data['enviar_email'] ?? false) {
                $this->enviarPorEmail($propostaId, $vistoria, $token);
            }
            
            // Registrar no histórico
            $this->registrarHistorico(
                'propostas',
                $propostaId,
                null,
                'Criada',
                'Proposta criada'
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'proposta_id' => $propostaId,
                'message' => 'Proposta criada com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Enviar proposta por email
    private function enviarPorEmail($propostaId, $vistoria, $token) {
        if (empty($vistoria['email'])) {
            throw new Exception('Cliente não possui email cadastrado');
        }
        
        // Buscar dados completos da proposta
        $stmt = $this->pdo->prepare("SELECT * FROM propostas WHERE id = ?");
        $stmt->execute([$propostaId]);
        $proposta = $stmt->fetch();
        
        $enviado = $this->enviarEmail($vistoria, $proposta, $token);
        
        if ($enviado) {
            // Atualizar status da proposta
            $stmt = $this->pdo->prepare("
                UPDATE propostas 
                SET status = 'Enviada', data_envio = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$propostaId]);
            
            // Atualizar status da vistoria
            $stmt = $this->pdo->prepare("
                UPDATE vistorias 
                SET status = 'Proposta_Enviada' 
                WHERE id = ?
            ");
            $stmt->execute([$vistoria['id']]);
            
            // Registrar histórico
            $this->registrarHistorico(
                'propostas',
                $propostaId,
                'Criada',
                'Enviada',
                'Proposta enviada por email para: ' . $vistoria['email']
            );
            
            $this->registrarHistorico(
                'vistorias',
                $vistoria['id'],
                $vistoria['status'],
                'Proposta_Enviada',
                'Proposta enviada ao cliente'
            );
        } else {
            throw new Exception('Erro ao enviar email. Proposta criada mas não enviada.');
        }
    }
    
    // Função para enviar email (melhorada)
    private function enviarEmail($vistoria, $proposta, $token) {
        $destinatario = $vistoria['email'];
        $assunto = "Proposta de Mudança - " . $vistoria['cliente'];
        
        // URL base do sistema (configurar em produção)
        $baseUrl = "http://localhost/portal_cliente";
        $linkAceite = $baseUrl . "/aceitar-proposta.php?token=" . $token;
        
        $mensagem = $this->gerarHTMLEmail($vistoria, $proposta, $linkAceite);
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Sistema de Mudanças <noreply@sistema-mudancas.com>',
            'Reply-To: suporte@sistema-mudancas.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $enviado = @mail($destinatario, $assunto, $mensagem, implode("\r\n", $headers));
        
        // Registrar log de email
        $this->registrarLogEmail($destinatario, $assunto, 'proposta', $enviado);
        
        return 'Enviado';
    }
    
    // Gerar HTML do email
    private function gerarHTMLEmail($vistoria, $proposta, $linkAceite) {
        $valorFormatado = number_format($proposta['valor_total'], 2, ',', '.');
        $dataVistoria = date('d/m/Y', strtotime($vistoria['data_vistoria']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f4f4f4; padding: 20px; }
                .details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .button { background-color: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Proposta de Mudança</h1>
                </div>
                <div class='content'>
                    <p>Prezado(a) <strong>{$vistoria['cliente']}</strong>,</p>
                    <p>Conforme vistoria realizada, segue nossa proposta para sua mudança:</p>
                    
                    <div class='details'>
                        <h3>Detalhes do Serviço</h3>
                        <p><strong>Endereço:</strong> {$vistoria['endereco']}</p>
                        <p><strong>Tipo de Imóvel:</strong> {$vistoria['tipo_imovel']}</p>
                        <p><strong>Data da Vistoria:</strong> {$dataVistoria}</p>
                    </div>
                    
                    <div class='details'>
                        <h3>Valor Total: R$ {$valorFormatado}</h3>
                        <p><strong>Descrição dos Serviços:</strong></p>
                        <p>" . nl2br(htmlspecialchars($proposta['descricao_servicos'])) . "</p>
                        <p><strong>Validade:</strong> {$proposta['validade_dias']} dias</p>
                    </div>
                    
                    <center>
                        <a href='{$linkAceite}' class='button'>ACEITAR PROPOSTA</a>
                    </center>
                    
                    <p style='font-size: 12px; color: #666;'>
                        Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                        {$linkAceite}
                    </p>
                </div>
                <div class='footer'>
                    <p>Sistema de Mudanças - Todos os direitos reservados</p>
                    <p>Este é um email automático, por favor não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    // Buscar proposta
    public function buscar($vistoriaId = null, $propostaId = null) {
        if ($propostaId) {
            $stmt = $this->pdo->prepare("
                SELECT p.*, v.cliente, v.endereco, v.tipo_imovel, u.nome as criado_por_nome
                FROM propostas p
                JOIN vistorias v ON p.vistoria_id = v.id
                LEFT JOIN usuarios u ON p.criado_por = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$propostaId]);
            return $stmt->fetch();
        }
        
        if ($vistoriaId) {
            $stmt = $this->pdo->prepare("
                SELECT p.*, u.nome as criado_por_nome
                FROM propostas p
                LEFT JOIN usuarios u ON p.criado_por = u.id
                WHERE p.vistoria_id = ?
                ORDER BY p.data_criacao DESC
            ");
            $stmt->execute([$vistoriaId]);
            return $stmt->fetchAll();
        }
        
        // Listar todas (apenas para gestores)
        if (!isGestor()) {
            throw new Exception('Sem permissão para listar todas as propostas');
        }
        
        $stmt = $this->pdo->query("
            SELECT p.*, v.cliente, v.endereco, u.nome as criado_por_nome
            FROM propostas p
            JOIN vistorias v ON p.vistoria_id = v.id
            LEFT JOIN usuarios u ON p.criado_por = u.id
            ORDER BY p.data_criacao DESC
            LIMIT 100
        ");
        
        return $stmt->fetchAll();
    }
    
    // Aceitar proposta
    public function aceitar($token, $ip = null) {
        if (empty($token)) {
            throw new Exception('Token inválido');
        }
        
        // Buscar proposta pelo token
        $stmt = $this->pdo->prepare("
            SELECT p.*, v.cliente, v.cpf, v.email, v.telefone, v.endereco
            FROM propostas p
            JOIN vistorias v ON p.vistoria_id = v.id
            WHERE p.token_aceite = ? AND p.status = 'Enviada'
        ");
        $stmt->execute([$token]);
        $proposta = $stmt->fetch();
        
        if (!$proposta) {
            throw new Exception('Proposta não encontrada ou já foi processada');
        }
        
        // Verificar validade
        $dataValidade = new DateTime($proposta['data_criacao']);
        $dataValidade->add(new DateInterval('P' . $proposta['validade_dias'] . 'D'));
        if ($dataValidade < new DateTime()) {
            // Marcar como expirada
            $stmt = $this->pdo->prepare("UPDATE propostas SET status = 'Expirada' WHERE id = ?");
            $stmt->execute([$proposta['id']]);
            throw new Exception('Esta proposta expirou');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Criar ou buscar cliente
            $clienteId = $this->criarOuBuscarCliente($proposta);
            
            // Atualizar proposta
            $stmt = $this->pdo->prepare("
                UPDATE propostas 
                SET status = 'Aceita', 
                    data_aceite = NOW(), 
                    ip_aceite = ?,
                    aceita_por = ?
                WHERE id = ?
            ");
            $stmt->execute([$ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $clienteId, $proposta['id']]);
            
            // Atualizar vistoria
            $stmt = $this->pdo->prepare("
                UPDATE vistorias 
                SET status = 'Proposta_Aceita', 
                    cliente_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$clienteId, $proposta['vistoria_id']]);
            
            // Criar registro de mudança
            $stmt = $this->pdo->prepare("
                INSERT INTO mudancas (
                    vistoria_id, 
                    proposta_id, 
                    cliente_id, 
                    status,
                    observacoes
                ) VALUES (?, ?, ?, 'Aguardando_Documentos', 'Mudança criada após aceite de proposta')
            ");
            $stmt->execute([$proposta['vistoria_id'], $proposta['id'], $clienteId]);
            $mudancaId = $this->pdo->lastInsertId();
            
            // Registrar históricos
            $this->registrarHistorico(
                'propostas',
                $proposta['id'],
                'Enviada',
                'Aceita',
                'Proposta aceita pelo cliente via link'
            );
            
            $this->registrarHistorico(
                'vistorias',
                $proposta['vistoria_id'],
                'Proposta_Enviada',
                'Proposta_Aceita',
                'Cliente aceitou a proposta'
            );
            
            // Criar notificações
            $this->criarNotificacaoPropostaAceita($proposta, $mudancaId);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'mudanca_id' => $mudancaId,
                'cliente_id' => $clienteId,
                'message' => 'Proposta aceita com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Cancelar proposta
    public function cancelar($propostaId, $motivo) {
        $stmt = $this->pdo->prepare("SELECT * FROM propostas WHERE id = ?");
        $stmt->execute([$propostaId]);
        $proposta = $stmt->fetch();
        
        if (!$proposta) {
            throw new Exception('Proposta não encontrada');
        }
        
        if (!in_array($proposta['status'], ['Criada', 'Enviada'])) {
            throw new Exception('Esta proposta não pode ser cancelada');
        }
        
        // Verificar permissão
        $stmt = $this->pdo->prepare("SELECT vendedor FROM vistorias WHERE id = ?");
        $stmt->execute([$proposta['vistoria_id']]);
        $vistoria = $stmt->fetch();
        
        if (!$this->podeGerenciarProposta($vistoria)) {
            throw new Exception('Sem permissão para cancelar esta proposta');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Atualizar proposta
            $stmt = $this->pdo->prepare("
                UPDATE propostas 
                SET status = 'Cancelada', 
                    observacoes = CONCAT(COALESCE(observacoes, ''), '\nCancelada: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $propostaId]);
            
            // Voltar status da vistoria
            $stmt = $this->pdo->prepare("
                UPDATE vistorias 
                SET status = 'Cotacao_Aprovada'
                WHERE id = ? AND status = 'Proposta_Enviada'
            ");
            $stmt->execute([$proposta['vistoria_id']]);
            
            // Registrar histórico
            $this->registrarHistorico(
                'propostas',
                $propostaId,
                $proposta['status'],
                'Cancelada',
                'Motivo: ' . $motivo
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Proposta cancelada com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Criar ou buscar cliente
    private function criarOuBuscarCliente($proposta) {
        // Verificar se já existe
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$proposta['email']]);
        $cliente = $stmt->fetch();
        
        if ($cliente) {
            return $cliente['id'];
        }
        
        // Criar novo cliente
        $senhaTemporaria = $this->gerarSenhaTemporaria();
        $senhaHash = password_hash($senhaTemporaria, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (nome, email, senha, telefone, tipo, cpf) 
            VALUES (?, ?, ?, ?, 'cliente', ?)
        ");
        $stmt->execute([
            $proposta['cliente'],
            $proposta['email'],
            $senhaHash,
            $proposta['telefone'],
            $proposta['cpf']
        ]);
        
        $clienteId = $this->pdo->lastInsertId();
        
        // Enviar email com credenciais (implementar depois)
        $this->enviarCredenciaisCliente($proposta['email'], $proposta['cliente'], $senhaTemporaria);
        
        return $clienteId;
    }
    
    // Gerar senha temporária
    private function gerarSenhaTemporaria() {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
    }
    
    // Enviar credenciais do cliente
    private function enviarCredenciaisCliente($email, $nome, $senha) {
        // TODO: Implementar envio de email com credenciais
        // Por enquanto, apenas registrar no log
        $this->registrarLogEmail(
            $email,
            'Suas credenciais de acesso - Sistema de Mudanças',
            'credenciais',
            true,
            "Senha temporária: $senha"
        );
    }
    
    // Criar notificações
    private function criarNotificacaoPropostaAceita($proposta, $mudancaId) {
        // Notificar gestores
        $stmt = $this->pdo->query("SELECT id FROM usuarios WHERE tipo = 'gestor' AND ativo = 1");
        $gestores = $stmt->fetchAll();
        
        foreach ($gestores as $gestor) {
            $this->criarNotificacao(
                $gestor['id'],
                'proposta_aceita',
                'Proposta Aceita',
                "O cliente {$proposta['cliente']} aceitou a proposta #{$proposta['id']}. Mudança #{$mudancaId} criada."
            );
        }
        
        // Notificar vendedor responsável
        $stmt = $this->pdo->prepare("
            SELECT u.id 
            FROM vistorias v
            JOIN usuarios u ON v.vendedor = u.nome
            WHERE v.id = ?
        ");
        $stmt->execute([$proposta['vistoria_id']]);
        $vendedor = $stmt->fetch();
        
        if ($vendedor) {
            $this->criarNotificacao(
                $vendedor['id'],
                'proposta_aceita',
                'Sua Proposta foi Aceita',
                "O cliente {$proposta['cliente']} aceitou a proposta que você criou."
            );
        }
    }
    
    // Criar notificação
    private function criarNotificacao($usuarioId, $tipo, $titulo, $mensagem) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuarioId, $tipo, $titulo, $mensagem]);
    }
    
    // Registrar histórico
    private function registrarHistorico($tabela, $registroId, $statusAnterior, $statusNovo, $observacoes) {
        $stmt = $this->pdo->prepare("
            INSERT INTO historico_status (
                tabela, 
                registro_id, 
                status_anterior, 
                status_novo, 
                usuario_id, 
                observacoes
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tabela,
            $registroId,
            $statusAnterior,
            $statusNovo,
            $this->usuario_id,
            $observacoes
        ]);
    }
    
    // Registrar log de email
    private function registrarLogEmail($destinatario, $assunto, $tipo, $sucesso, $erro = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO logs_email (destinatario, assunto, tipo, status, erro) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $destinatario,
            $assunto,
            $tipo,
            $sucesso ? 'Enviado' : 'Erro',
            $erro
        ]);
    }
}

// Processar requisições
try {
    // Para aceite de proposta, não precisa de autenticação
    if ($method === 'PUT' && isset(json_decode(file_get_contents('php://input'), true)['token'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        $manager = new PropostaManager($pdo, ['id' => 0, 'tipo' => 'sistema', 'nome' => 'Sistema']);
        $resultado = $manager->aceitar($data['token'], $_SERVER['REMOTE_ADDR'] ?? null);
        echo json_encode($resultado);
        exit;
    }
    
    // Outras operações precisam de autenticação
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    
    $usuario = [
        'id' => $_SESSION['usuario_id'],
        'tipo' => $_SESSION['usuario_tipo'],
        'nome' => $_SESSION['usuario_nome']
    ];
    
    $manager = new PropostaManager($pdo, $usuario);
    
    switch($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $manager->criar($data);
            echo json_encode($resultado);
            break;
            
        case 'GET':
            $vistoriaId = $_GET['vistoria_id'] ?? null;
            $propostaId = $_GET['id'] ?? null;
            $propostas = $manager->buscar($vistoriaId, $propostaId);
            echo json_encode($propostas);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                throw new Exception('ID da proposta não fornecido');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $motivo = $data['motivo'] ?? 'Cancelada pelo usuário';
            $resultado = $manager->cancelar($_GET['id'], $motivo);
            echo json_encode($resultado);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>