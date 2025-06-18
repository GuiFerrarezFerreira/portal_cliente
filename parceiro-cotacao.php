<?php
// parceiro-cotacao.php - Interface para parceiros responderem cotações
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';
$erro = '';
$sucesso = false;
$cotacaoParceiro = null;
$vistoria = null;

if (!$token) {
    $erro = 'Token inválido';
} else {
    // Buscar cotação do parceiro
    $stmt = $pdo->prepare("
        SELECT cp.*, c.*, v.*, p.nome as parceiro_nome, p.email as parceiro_email
        FROM cotacoes_parceiros cp
        JOIN cotacoes c ON cp.cotacao_id = c.id
        JOIN vistorias v ON c.vistoria_id = v.id
        JOIN parceiros p ON cp.parceiro_id = p.id
        WHERE cp.token_resposta = ?
    ");
    $stmt->execute([$token]);
    $cotacaoParceiro = $stmt->fetch();
    
    if (!$cotacaoParceiro) {
        $erro = 'Cotação não encontrada';
    } else if ($cotacaoParceiro['valor'] !== null) {
        $erro = 'Esta cotação já foi respondida';
    } else {
        // Verificar prazo
        $dataCriacao = new DateTime($cotacaoParceiro['data_criacao']);
        $prazoFinal = clone $dataCriacao;
        $prazoFinal->add(new DateInterval('PT' . $cotacaoParceiro['prazo_resposta_horas'] . 'H'));
        
        if (new DateTime() > $prazoFinal) {
            $erro = 'O prazo para responder esta cotação expirou';
        }
    }
}

// Processar resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cotacaoParceiro && !$erro) {
    $valor = $_POST['valor'] ?? 0;
    $prazoDias = $_POST['prazo_dias'] ?? 0;
    $observacoes = $_POST['observacoes'] ?? '';
    
    if ($valor <= 0) {
        $erro = 'Por favor, informe um valor válido';
    } else if ($prazoDias <= 0) {
        $erro = 'Por favor, informe um prazo válido';
    } else {
        try {
            // Atualizar resposta
            $stmt = $pdo->prepare("
                UPDATE cotacoes_parceiros SET 
                    valor = ?,
                    prazo_dias = ?,
                    observacoes = ?,
                    data_resposta = NOW(),
                    ip_resposta = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $valor,
                $prazoDias,
                $observacoes,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $cotacaoParceiro['id']
            ]);
            
            // Verificar se todos responderam
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total, COUNT(valor) as respondidos
                FROM cotacoes_parceiros
                WHERE cotacao_id = ?
            ");
            $stmt->execute([$cotacaoParceiro['cotacao_id']]);
            $status = $stmt->fetch();
            
            // Se todos responderam, atualizar status da cotação
            if ($status['total'] == $status['respondidos']) {
                $stmt = $pdo->prepare("UPDATE cotacoes SET status = 'Cotacoes_Recebidas' WHERE id = ?");
                $stmt->execute([$cotacaoParceiro['cotacao_id']]);
                
                // Notificar responsáveis
                $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                                      SELECT responsavel_id, 'cotacoes_completas', 'Todas as Cotações Recebidas',
                                      CONCAT('Todos os parceiros responderam à cotação #', ?)
                                      FROM cotacoes WHERE id = ?");
                $stmt->execute([$cotacaoParceiro['cotacao_id'], $cotacaoParceiro['cotacao_id']]);
            }
            
            $sucesso = true;
            
        } catch (Exception $e) {
            $erro = 'Erro ao processar sua resposta. Por favor, tente novamente.';
        }
    }
}

// Buscar outras cotações do mesmo parceiro (histórico)
$historico = [];
if ($cotacaoParceiro) {
    $stmt = $pdo->prepare("
        SELECT cp.*, c.data_criacao as cotacao_data, v.cliente, v.endereco
        FROM cotacoes_parceiros cp
        JOIN cotacoes c ON cp.cotacao_id = c.id
        JOIN vistorias v ON c.vistoria_id = v.id
        WHERE cp.parceiro_id = ? AND cp.valor IS NOT NULL
        ORDER BY cp.data_resposta DESC
        LIMIT 10
    ");
    $stmt->execute([$cotacaoParceiro['parceiro_id']]);
    $historico = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cotação - Parceiros</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 30px -30px;
            text-align: center;
        }

        h1, h2 {
            margin-bottom: 20px;
        }

        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #27ae60;
            color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .info-section {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .info-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-item strong {
            color: #555;
            display: inline-block;
            min-width: 120px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="number"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="number"]:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .arquivo-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .arquivo-info a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }

        .arquivo-info a:hover {
            text-decoration: underline;
        }

        .historico {
            margin-top: 40px;
        }

        .historico-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 3px solid #3498db;
        }

        .prazo-info {
            background: #f39c12;
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
        }

        .valor-input-group {
            position: relative;
        }

        .valor-input-group::before {
            content: 'R$';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            font-weight: bold;
        }

        .valor-input-group input {
            padding-left: 40px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #7f8c8d;
            font-size: 14px;
        }

        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
            margin-bottom: 20px;
        }

        .highlight strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Sistema de Cotação</h1>
                <?php if ($cotacaoParceiro && !$erro): ?>
                    <p>Bem-vindo, <?php echo htmlspecialchars($cotacaoParceiro['parceiro_nome']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($erro): ?>
                <div class="error"><?php echo htmlspecialchars($erro); ?></div>
            <?php elseif ($sucesso): ?>
                <div class="success">
                    <h2>Cotação Enviada com Sucesso!</h2>
                    <p>Obrigado por responder nossa solicitação de cotação.</p>
                    <p>Valor informado: R$ <?php echo number_format($valor, 2, ',', '.'); ?></p>
                    <p>Prazo: <?php echo $prazoDias; ?> dias</p>
                    <p>Entraremos em contato caso sua proposta seja selecionada.</p>
                </div>
            <?php elseif ($cotacaoParceiro): ?>
                <?php
                // Calcular tempo restante
                $dataCriacao = new DateTime($cotacaoParceiro['data_criacao']);
                $prazoFinal = clone $dataCriacao;
                $prazoFinal->add(new DateInterval('PT' . $cotacaoParceiro['prazo_resposta_horas'] . 'H'));
                $agora = new DateTime();
                $intervalo = $agora->diff($prazoFinal);
                $horasRestantes = ($intervalo->days * 24) + $intervalo->h;
                ?>
                
                <div class="prazo-info">
                    <strong>Atenção:</strong> Você tem <?php echo $horasRestantes; ?> horas restantes para responder esta cotação
                </div>
                
                <h2>Solicitação de Cotação</h2>
                
                <div class="info-section">
                    <h3>Dados do Cliente</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Cliente:</strong> <?php echo htmlspecialchars($cotacaoParceiro['cliente']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Data Vistoria:</strong> <?php echo date('d/m/Y', strtotime($cotacaoParceiro['data_vistoria'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Detalhes do Serviço</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Endereço:</strong> <?php echo htmlspecialchars($cotacaoParceiro['endereco']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Tipo Imóvel:</strong> <?php echo htmlspecialchars($cotacaoParceiro['tipo_imovel']); ?>
                        </div>
                    </div>
                    
                    <?php if ($cotacaoParceiro['observacoes']): ?>
                    <div class="info-item" style="margin-top: 15px;">
                        <strong>Observações:</strong><br>
                        <?php echo nl2br(htmlspecialchars($cotacaoParceiro['observacoes'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($cotacaoParceiro['arquivo_lista_seguro']): ?>
                <div class="arquivo-info">
                    <strong>📎 Lista de Seguro:</strong> 
                    <a href="uploads/lista_seguro/<?php echo $cotacaoParceiro['arquivo_lista_seguro']; ?>" target="_blank">
                        Clique aqui para baixar o arquivo
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="highlight">
                    <strong>Importante:</strong> Analise cuidadosamente a lista de seguro anexada antes de enviar sua cotação.
                    Considere todos os itens, materiais necessários e logística envolvida.
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="valor">Valor Total da Mudança (R$):</label>
                        <div class="valor-input-group">
                            <input type="number" id="valor" name="valor" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="prazo_dias">Prazo para Execução (em dias):</label>
                        <input type="number" id="prazo_dias" name="prazo_dias" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações / Condições Especiais:</label>
                        <textarea id="observacoes" name="observacoes" 
                                  placeholder="Informe aqui quaisquer condições especiais, detalhes sobre o serviço, ou observações importantes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enviar Cotação</button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">Cancelar</button>
                </form>
                
                <?php if (count($historico) > 0): ?>
                <div class="historico">
                    <h3>Suas Últimas Cotações</h3>
                    <?php foreach ($historico as $item): ?>
                    <div class="historico-item">
                        <strong><?php echo htmlspecialchars($item['cliente']); ?></strong> - 
                        <?php echo htmlspecialchars($item['endereco']); ?><br>
                        <small>
                            Valor: R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?> | 
                            Prazo: <?php echo $item['prazo_dias']; ?> dias | 
                            Data: <?php echo date('d/m/Y', strtotime($item['data_resposta'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="footer">
                <p>Sistema de Cotação - Parceiros</p>
                <p>Em caso de dúvidas, entre em contato pelo telefone (11) 1234-5678</p>
            </div>
        </div>
    </div>
</body>
</html>