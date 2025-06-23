<?php
// aceitar-proposta.php
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';
$erro = '';
$sucesso = false;
$proposta = null;

if (!$token) {
    $erro = 'Token inválido ou não fornecido';
} else {
    try {
        // Buscar proposta com informações completas
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                v.cliente,
                v.cpf,
                v.telefone,
                v.email,
                v.endereco,
                v.tipo_imovel,
                v.data_vistoria,
                v.vendedor,
                v.observacoes as vistoria_observacoes,
                u.nome as criado_por_nome,
                DATEDIFF(DATE_ADD(p.data_criacao, INTERVAL p.validade_dias DAY), CURDATE()) as dias_restantes
            FROM propostas p
            JOIN vistorias v ON p.vistoria_id = v.id
            LEFT JOIN usuarios u ON p.criado_por = u.id
            WHERE p.token_aceite = ? AND p.status = 'Enviada'
        ");
        $stmt->execute([$token]);
        $proposta = $stmt->fetch();
        
        if (!$proposta) {
            // Verificar se existe mas com outro status
            $stmt = $pdo->prepare("
                SELECT p.status, p.data_aceite 
                FROM propostas p 
                WHERE p.token_aceite = ?
            ");
            $stmt->execute([$token]);
            $propostaStatus = $stmt->fetch();
            
            if ($propostaStatus) {
                if ($propostaStatus['status'] === 'Aceita') {
                    $erro = 'Esta proposta já foi aceita em ' . date('d/m/Y às H:i', strtotime($propostaStatus['data_aceite']));
                } elseif ($propostaStatus['status'] === 'Expirada') {
                    $erro = 'Esta proposta expirou';
                } elseif ($propostaStatus['status'] === 'Cancelada') {
                    $erro = 'Esta proposta foi cancelada';
                } else {
                    $erro = 'Proposta não está disponível para aceite';
                }
            } else {
                $erro = 'Proposta não encontrada';
            }
        } else {
            // Verificar validade
            if ($proposta['dias_restantes'] < 0) {
                // Atualizar status para expirada
                $stmt = $pdo->prepare("UPDATE propostas SET status = 'Expirada' WHERE id = ?");
                $stmt->execute([$proposta['id']]);
                $erro = 'Esta proposta expirou há ' . abs($proposta['dias_restantes']) . ' dias';
            }
        }
    } catch (Exception $e) {
        $erro = 'Erro ao processar solicitação. Por favor, tente novamente.';
        error_log('Erro em aceitar-proposta.php: ' . $e->getMessage());
    }
}

// Processar aceite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proposta && !$erro) {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'aceitar') {
        try {
            // Fazer chamada à API para aceitar proposta
            $data = json_encode(['token' => $token]);
            
            $ch = curl_init('http://localhost/sistema-mudancas/api/propostas.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result['success']) {
                    $sucesso = true;
                    $_SESSION['proposta_aceita'] = true;
                    $_SESSION['cliente_id'] = $result['cliente_id'] ?? null;
                    $_SESSION['mudanca_id'] = $result['mudanca_id'] ?? null;
                } else {
                    $erro = $result['error'] ?? 'Erro ao processar aceite';
                }
            } else {
                $erro = 'Erro ao processar aceite. Por favor, tente novamente.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao processar aceite. Por favor, tente novamente.';
            error_log('Erro ao aceitar proposta: ' . $e->getMessage());
        }
    } elseif ($acao === 'recusar') {
        $motivo = $_POST['motivo'] ?? 'Não informado';
        
        try {
            $pdo->beginTransaction();
            
            // Atualizar proposta
            $stmt = $pdo->prepare("
                UPDATE propostas 
                SET status = 'Rejeitada', 
                    data_rejeicao = NOW(),
                    motivo_rejeicao = ?
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $proposta['id']]);
            
            // Voltar status da vistoria
            $stmt = $pdo->prepare("
                UPDATE vistorias 
                SET status = 'Cotacao_Aprovada' 
                WHERE id = ?
            ");
            $stmt->execute([$proposta['vistoria_id']]);
            
            // Registrar histórico
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('propostas', ?, 'Enviada', 'Rejeitada', NULL, ?)
            ");
            $stmt->execute([$proposta['id'], 'Cliente recusou - Motivo: ' . $motivo]);
            
            $pdo->commit();
            
            $erro = 'Proposta recusada. Agradecemos pelo seu tempo.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao processar recusa';
        }
    }
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Função para formatar data e hora
function formatarDataHora($data) {
    return date('d/m/Y às H:i', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $proposta ? 'Proposta #' . $proposta['id'] : 'Sistema de Mudanças'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fa;
            color: #2d3748;
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin: -20px -20px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            padding: 30px;
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-enviada {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .status-aceita {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-expirada {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .error-icon {
            font-size: 24px;
        }

        .success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success::before {
            content: '✓';
            position: absolute;
            font-size: 200px;
            opacity: 0.1;
            right: -50px;
            top: -50px;
            transform: rotate(-15deg);
        }

        .success h2 {
            font-size: 28px;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .success p {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
            margin-bottom: 10px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: #4a5568;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }

        .valor-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }

        .valor-total::before {
            content: 'R$';
            position: absolute;
            font-size: 80px;
            opacity: 0.1;
            left: -20px;
            top: -20px;
        }

        .valor-label {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .valor-numero {
            font-size: 48px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .descricao-servicos {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            white-space: pre-wrap;
            line-height: 1.8;
        }

        .prazo-alerta {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .prazo-alerta-icon {
            font-size: 24px;
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.3s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-accept {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
        }

        .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .footer {
            text-align: center;
            margin-top: 50px;
            padding: 30px 0;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            position: relative;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 24px;
            color: #2d3748;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 16px;
            resize: vertical;
            transition: border-color 0.3s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .icon {
            width: 20px;
            height: 20px;
            display: inline-block;
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }
            
            .valor-numero {
                font-size: 36px;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sistema de Mudanças</h1>
            <p>Proposta de Serviço</p>
        </div>
        
        <?php if ($erro && !$sucesso): ?>
            <div class="error">
                <span class="error-icon">⚠️</span>
                <div>
                    <strong>Atenção!</strong><br>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            </div>
            
            <div class="card" style="text-align: center;">
                <p style="margin-bottom: 20px;">Se você acredita que isso é um erro, entre em contato conosco:</p>
                <p><strong>📞 Telefone:</strong> (11) 1234-5678</p>
                <p><strong>📧 Email:</strong> contato@sistemamudancas.com.br</p>
            </div>
            
        <?php elseif ($sucesso): ?>
            <div class="success">
                <h2>🎉 Proposta Aceita com Sucesso!</h2>
                <p>Obrigado por confiar em nossos serviços, <?php echo htmlspecialchars($proposta['cliente']); ?>!</p>
                <p>Em breve você receberá um email com as instruções para os próximos passos.</p>
                <p style="margin-top: 20px; font-size: 16px;">
                    <strong>Número da sua mudança:</strong> #<?php echo $_SESSION['mudanca_id'] ?? 'Em processamento'; ?>
                </p>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom: 20px;">📋 Próximos Passos</h3>
                <ol style="line-height: 2; margin-left: 20px;">
                    <li>Você receberá um email com suas credenciais de acesso ao portal do cliente</li>
                    <li>Nossa equipe entrará em contato para agendar a data da mudança</li>
                    <li>Será solicitado o envio de alguns documentos necessários</li>
                    <li>Acompanhe todo o processo através do portal do cliente</li>
                </ol>
            </div>
            
        <?php elseif ($proposta): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Proposta de Mudança #<?php echo $proposta['id']; ?></h2>
                    <span class="status-badge status-enviada">Aguardando Resposta</span>
                </div>
                
                <div class="info-section">
                    <h3>👤 Dados do Cliente</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nome</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['cliente']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">CPF</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['cpf']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telefone</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['telefone']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['email'] ?: 'Não informado'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>🏠 Detalhes do Serviço</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Endereço</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['endereco']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tipo de Imóvel</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['tipo_imovel']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Data da Vistoria</div>
                            <div class="info-value"><?php echo formatarData($proposta['data_vistoria']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Vendedor</div>
                            <div class="info-value"><?php echo htmlspecialchars($proposta['vendedor']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="valor-total">
                    <div class="valor-label">Valor Total do Serviço</div>
                    <div class="valor-numero"><?php echo formatarMoeda($proposta['valor_total']); ?></div>
                </div>
                
                <div class="info-section">
                    <h3>📄 Descrição dos Serviços</h3>
                    <div class="descricao-servicos">
                        <?php echo nl2br(htmlspecialchars($proposta['descricao_servicos'])); ?>
                    </div>
                </div>
                
                <?php if ($proposta['dias_restantes'] <= 5): ?>
                <div class="prazo-alerta">
                    <span class="prazo-alerta-icon">⏰</span>
                    <div>
                        <strong>Atenção:</strong> Esta proposta expira em <?php echo $proposta['dias_restantes']; ?> dias!
                        <br>Após este prazo, será necessário solicitar uma nova proposta.
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="formResposta">
                    <div class="buttons">
                        <button type="submit" name="acao" value="aceitar" class="btn btn-accept" onclick="return confirmarAceite()">
                            <span>✓</span> Aceitar Proposta
                        </button>
                        <button type="button" class="btn btn-reject" onclick="abrirModalRecusa()">
                            <span>✗</span> Recusar Proposta
                        </button>
                    </div>
                </form>
                
                <div class="card" style="margin-top: 30px; background-color: #f8fafc;">
                    <h4 style="margin-bottom: 15px;">ℹ️ Informações Importantes</h4>
                    <ul style="line-height: 1.8; margin-left: 20px;">
                        <li>Ao aceitar esta proposta, você concorda com os termos e condições do serviço</li>
                        <li>O pagamento deverá ser realizado conforme acordado com nosso vendedor</li>
                        <li>Você receberá acesso ao portal do cliente para acompanhar sua mudança</li>
                        <li>Nossa equipe entrará em contato em até 24 horas úteis</li>
                        <li>Validade da proposta: <?php echo $proposta['validade_dias']; ?> dias a partir de <?php echo formatarData($proposta['data_criacao']); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p><strong>Sistema de Mudanças</strong> - Cuidando da sua mudança com segurança e eficiência</p>
            <p>Dúvidas? Entre em contato: <a href="tel:1112345678">(11) 1234-5678</a> | <a href="mailto:contato@sistemamudancas.com.br">contato@sistemamudancas.com.br</a></p>
        </div>
    </div>

    <!-- Modal de Recusa -->
    <div id="modalRecusa" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Recusar Proposta</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="recusar">
                <div class="form-group">
                    <label for="motivo">Por favor, informe o motivo da recusa (opcional):</label>
                    <textarea id="motivo" name="motivo" rows="4" placeholder="Seu feedback é importante para melhorarmos nossos serviços..."></textarea>
                </div>
                <div class="buttons">
                    <button type="submit" class="btn btn-reject">Confirmar Recusa</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModalRecusa()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmarAceite() {
            if (confirm('Você confirma que deseja aceitar esta proposta?\n\nValor: <?php echo $proposta ? formatarMoeda($proposta['valor_total']) : ''; ?>')) {
                // Desabilitar botão para evitar duplo clique
                const btn = event.target;
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span> Processando...';
                return true;
            }
            return false;
        }
        
        function abrirModalRecusa() {
            document.getElementById('modalRecusa').style.display = 'block';
        }
        
        function fecharModalRecusa() {
            document.getElementById('modalRecusa').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalRecusa');
            if (event.target == modal) {
                fecharModalRecusa();
            }
        }
        
        // Adicionar efeito de scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>