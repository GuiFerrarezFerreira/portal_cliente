<?php
// parceiro-cotacao.php - Interface moderna para parceiros responderem cotações
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';
$erro = '';
$sucesso = false;
$cotacaoParceiro = null;
$vistoria = null;

if (!$token) {
    $erro = 'Token inválido ou não fornecido';
} else {
    // Buscar cotação do parceiro
    $stmt = $pdo->prepare("
        SELECT cp.*, c.*, v.*, p.nome as parceiro_nome, p.email as parceiro_email
        FROM cotacoes_parceiros cp
        JOIN cotacoes c ON cp.cotacao_id = c.id
        JOIN vistorias v ON c.vistoria_id = v.id
        JOIN parceiros p ON cp.parceiro_id = p.id
        WHERE cp.token_acesso = ?
    ");
    $stmt->execute([$token]);
    $cotacaoParceiro = $stmt->fetch();
    
    if (!$cotacaoParceiro) {
        $erro = 'Cotação não encontrada ou token inválido';
    } else if ($cotacaoParceiro['valor'] !== null) {
        $erro = 'Esta cotação já foi respondida';
    } else {
        // Verificar prazo (48 horas padrão)
        $dataCriacao = new DateTime($cotacaoParceiro['data_criacao']);
        $prazoFinal = clone $dataCriacao;
        $prazoFinal->add(new DateInterval('PT48H'));
        
        if (new DateTime() > $prazoFinal) {
            $erro = 'O prazo para responder esta cotação expirou';
        }
    }
}

// Processar resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cotacaoParceiro && !$erro) {
    $valor = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor'] ?? '0');
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
                WHERE token_acesso = ?
            ");
            $stmt->execute([
                $valor,
                $prazoDias,
                $observacoes,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $cotacaoParceiro['token_acesso']
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
            error_log('Erro em parceiro-cotacao: ' . $e->getMessage());
        }
    }
}

// Buscar histórico do parceiro (últimas 5 cotações)
$historico = [];
if ($cotacaoParceiro) {
    $stmt = $pdo->prepare("
        SELECT cp.*, c.data_criacao as cotacao_data, v.cliente, v.endereco, v.tipo_imovel
        FROM cotacoes_parceiros cp
        JOIN cotacoes c ON cp.cotacao_id = c.id
        JOIN vistorias v ON c.vistoria_id = v.id
        WHERE cp.parceiro_id = ? AND cp.valor IS NOT NULL
        ORDER BY cp.data_resposta DESC
        LIMIT 5
    ");
    $stmt->execute([$cotacaoParceiro['parceiro_id']]);
    $historico = $stmt->fetchAll();
}

// Calcular tempo restante
$tempoRestante = null;
if ($cotacaoParceiro && !$erro) {
    $dataCriacao = new DateTime($cotacaoParceiro['data_criacao']);
    $prazoFinal = clone $dataCriacao;
    $prazoFinal->add(new DateInterval('PT48H'));
    $agora = new DateTime();
    
    if ($agora < $prazoFinal) {
        $intervalo = $agora->diff($prazoFinal);
        $horasRestantes = ($intervalo->days * 24) + $intervalo->h;
        $minutosRestantes = $intervalo->i;
        $tempoRestante = ['horas' => $horasRestantes, 'minutos' => $minutosRestantes];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Cotações - <?php echo $cotacaoParceiro ? htmlspecialchars($cotacaoParceiro['parceiro_nome']) : 'Sistema de Mudanças'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --info: #4299e1;
            --dark: #2d3748;
            --gray: #718096;
            --light-gray: #e2e8f0;
            --bg-light: #f7fafc;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: var(--dark);
            position: relative;
            overflow-x: hidden;
        }

        /* Elementos de fundo animados */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, transparent 70%);
            top: -200px;
            right: -200px;
            animation: float 20s ease-in-out infinite;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.6) 0%, transparent 70%);
            bottom: -150px;
            left: -150px;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(50px, -50px) rotate(90deg); }
            50% { transform: translate(-50px, -100px) rotate(180deg); }
            75% { transform: translate(-100px, -50px) rotate(270deg); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 50px;
            animation: fadeInDown 0.6s ease-out;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: var(--shadow-lg); }
            50% { transform: scale(1.05); box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.2); }
            100% { transform: scale(1); box-shadow: var(--shadow-lg); }
        }

        .logo svg {
            width: 60px;
            height: 60px;
            fill: var(--primary);
        }

        .header h1 {
            color: white;
            font-size: 36px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 18px;
        }

        /* Cards principais */
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 40px;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Estados de erro e sucesso */
        .state-container {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
            padding: 60px 20px;
        }

        .state-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }

        .error-icon {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger);
        }

        .success-icon {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--secondary);
        }

        .state-title {
            font-size: 28px;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .state-message {
            font-size: 18px;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        /* Timer de prazo */
        .timer-container {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .timer-icon {
            font-size: 40px;
            animation: tick 1s infinite;
        }

        @keyframes tick {
            0%, 100% { transform: rotate(-5deg); }
            50% { transform: rotate(5deg); }
        }

        .timer-text {
            font-size: 18px;
            font-weight: 600;
            color: #92400e;
        }

        /* Grid de informações */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .info-card h3 {
            color: var(--primary);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card-content {
            color: var(--dark);
        }

        .info-item {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .info-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .info-value {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
            flex: 1;
            margin-left: 10px;
        }

        /* Formulário estilizado */
        .form-section {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 15px;
            padding: 35px;
            margin-top: 30px;
        }

        .form-title {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 25px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .currency-input {
            position: relative;
        }

        .currency-prefix {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-weight: 600;
            font-size: 18px;
            pointer-events: none;
        }

        .currency-input input {
            padding-left: 60px;
            font-size: 24px;
            font-weight: 600;
            color: var(--secondary);
        }

        textarea.form-input {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        /* Dicas e ajuda */
        .help-text {
            color: var(--gray);
            font-size: 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Botões estilizados */
        .btn {
            padding: 15px 35px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            background: rgba(255,255,255,0.2);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--gray);
            border: 2px solid var(--light-gray);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
            border-color: var(--gray);
            color: var(--dark);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        /* Histórico estilizado */
        .history-section {
            margin-top: 50px;
        }

        .history-title {
            font-size: 24px;
            color: white;
            margin-bottom: 25px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .history-grid {
            display: grid;
            gap: 20px;
        }

        .history-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
            transition: all 0.3s ease;
            opacity: 0;
            animation: fadeInHistory 0.6s ease-out forwards;
        }

        .history-item:nth-child(1) { animation-delay: 0.1s; }
        .history-item:nth-child(2) { animation-delay: 0.2s; }
        .history-item:nth-child(3) { animation-delay: 0.3s; }
        .history-item:nth-child(4) { animation-delay: 0.4s; }
        .history-item:nth-child(5) { animation-delay: 0.5s; }

        @keyframes fadeInHistory {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .history-item:hover {
            transform: translateX(10px);
            box-shadow: var(--shadow-lg);
        }

        .history-info h4 {
            color: var(--dark);
            margin-bottom: 5px;
        }

        .history-info p {
            color: var(--gray);
            font-size: 14px;
        }

        .history-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary);
        }

        /* Badge de destaque */
        .highlight-badge {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 15px;
            animation: shine 2s infinite;
        }

        @keyframes shine {
            0% { box-shadow: 0 0 5px rgba(251, 191, 36, 0.5); }
            50% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.8); }
            100% { box-shadow: 0 0 5px rgba(251, 191, 36, 0.5); }
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 60px;
            padding: 30px 0;
            color: rgba(255,255,255,0.8);
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.3s;
        }

        .footer a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        /* Loading state */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 28px;
            }

            .main-card {
                padding: 25px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .history-item {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .timer-container {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Animação de sucesso */
        .success-animation {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            pointer-events: none;
        }

        .checkmark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            box-shadow: inset 0px 0px 0px var(--secondary);
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
            position: relative;
            top: 5px;
            right: 5px;
            margin: 0 auto;
        }

        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: var(--secondary);
            fill: #fff;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }

        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 60px var(--secondary);
            }
        }
    </style>
</head>
<body>
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3H14.82C14.4 1.84 13.3 1 12 1S9.6 1.84 9.18 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM12 3C12.55 3 13 3.45 13 4S12.55 5 12 5 11 4.55 11 4 11.45 3 12 3ZM7 7H17V5H19V19H5V5H7V7ZM12 17L7 12L8.41 10.59L12 14.17L15.59 10.58L17 12L12 17Z"/>
                </svg>
            </div>
            <h1>Portal de Cotações</h1>
            <?php if ($cotacaoParceiro && !$erro): ?>
                <p>Bem-vindo, <strong><?php echo htmlspecialchars($cotacaoParceiro['parceiro_nome']); ?></strong></p>
            <?php else: ?>
                <p>Sistema de Mudanças - Parceiros</p>
            <?php endif; ?>
        </div>

        <?php if ($erro): ?>
            <!-- Estado de Erro -->
            <div class="main-card">
                <div class="state-container">
                    <div class="state-icon error-icon">❌</div>
                    <h2 class="state-title">Ops! Algo deu errado</h2>
                    <p class="state-message"><?php echo htmlspecialchars($erro); ?></p>
                    
                    <div style="margin-top: 40px;">
                        <p style="color: var(--gray); margin-bottom: 20px;">Precisa de ajuda? Entre em contato:</p>
                        <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
                            <a href="tel:1112345678" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                📞 (11) 1234-5678
                            </a>
                            <a href="mailto:parceiros@sistemamudancas.com.br" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                ✉️ parceiros@sistemamudancas.com.br
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($sucesso): ?>
            <!-- Estado de Sucesso -->
            <div class="main-card">
                <div class="state-container">
                    <div class="state-icon success-icon">
                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    <h2 class="state-title">Cotação Enviada com Sucesso! 🎉</h2>
                    <p class="state-message">
                        Obrigado por responder nossa solicitação!<br>
                        Sua proposta foi registrada com os seguintes valores:
                    </p>
                    
                    <div style="background: var(--bg-light); border-radius: 12px; padding: 25px; margin: 30px 0;">
                        <div style="display: grid; gap: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--gray);">Valor Total:</span>
                                <span style="font-size: 28px; font-weight: 700; color: var(--secondary);">
                                    R$ <?php echo number_format($valor, 2, ',', '.'); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--gray);">Prazo de Execução:</span>
                                <span style="font-size: 20px; font-weight: 600; color: var(--dark);">
                                    <?php echo $prazoDias; ?> dias
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <p style="color: var(--gray); font-size: 16px;">
                        Entraremos em contato caso sua proposta seja selecionada.<br>
                        Fique atento ao seu email e telefone cadastrados.
                    </p>
                </div>
            </div>

        <?php elseif ($cotacaoParceiro): ?>
            <!-- Formulário de Cotação -->
            <?php if ($tempoRestante): ?>
                <div class="timer-container">
                    <div class="timer-icon">⏰</div>
                    <div class="timer-text">
                        Tempo restante: <?php echo $tempoRestante['horas']; ?>h <?php echo $tempoRestante['minutos']; ?>min
                    </div>
                </div>
            <?php endif; ?>

            <div class="main-card">
                <div class="highlight-badge">Nova Solicitação de Cotação</div>
                
                <h2 style="font-size: 28px; color: var(--dark); margin-bottom: 30px;">
                    Solicitação de Cotação #<?php echo $cotacaoParceiro['cotacao_id']; ?>
                </h2>

                <div class="info-grid">
                    <div class="info-card">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            Dados do Cliente
                        </h3>
                        <div class="info-card-content">
                            <div class="info-item">
                                <span class="info-label">Cliente:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotacaoParceiro['cliente']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">CPF:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotacaoParceiro['cpf']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telefone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotacaoParceiro['telefone']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                            </svg>
                            Informações do Serviço
                        </h3>
                        <div class="info-card-content">
                            <div class="info-item">
                                <span class="info-label">Endereço:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotacaoParceiro['endereco']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tipo de Imóvel:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotacaoParceiro['tipo_imovel']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Data da Vistoria:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($cotacaoParceiro['data_vistoria'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($cotacaoParceiro['observacoes']): ?>
                <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 12px; padding: 20px; margin: 25px 0;">
                    <h4 style="color: #92400e; margin-bottom: 10px;">📋 Observações da Vistoria</h4>
                    <p style="color: #92400e; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($cotacaoParceiro['observacoes'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($cotacaoParceiro['arquivo_lista_seguro']): ?>
                <div style="background: var(--bg-light); border: 2px dashed var(--primary); border-radius: 12px; padding: 25px; margin: 25px 0; text-align: center;">
                    <h4 style="color: var(--primary); margin-bottom: 15px;">📎 Arquivo Anexado</h4>
                    <p style="color: var(--gray); margin-bottom: 15px;">
                        Lista detalhada dos itens a serem transportados
                    </p>
                    <a href="uploads/lista_seguro/<?php echo $cotacaoParceiro['arquivo_lista_seguro']; ?>" 
                       target="_blank" 
                       class="btn btn-primary"
                       style="text-decoration: none; display: inline-block;">
                        Baixar Lista de Seguro
                    </a>
                </div>
                <?php endif; ?>

                <div class="form-section">
                    <h3 class="form-title">📝 Preencha sua Proposta</h3>
                    
                    <form method="POST" id="cotacaoForm">
                        <div class="form-group">
                            <label class="form-label" for="valor">Valor Total do Serviço</label>
                            <div class="currency-input">
                                <span class="currency-prefix">R$</span>
                                <input type="text" 
                                       id="valor" 
                                       name="valor" 
                                       class="form-input"
                                       placeholder="0,00"
                                       required
                                       autocomplete="off">
                            </div>
                            <div class="help-text">
                                💡 Inclua todos os custos: mão de obra, materiais, transporte e seguro
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prazo_dias">Prazo para Execução (em dias)</label>
                            <input type="number" 
                                   id="prazo_dias" 
                                   name="prazo_dias" 
                                   class="form-input"
                                   min="1" 
                                   max="30"
                                   placeholder="Ex: 3"
                                   required>
                            <div class="help-text">
                                📅 Considere o tempo necessário para embalagem, transporte e montagem
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="observacoes">Observações / Condições Especiais (opcional)</label>
                            <textarea id="observacoes" 
                                      name="observacoes" 
                                      class="form-input"
                                      placeholder="Descreva aqui quaisquer condições especiais, detalhes sobre o serviço, formas de pagamento aceitas, ou outras informações relevantes..."
                                      rows="4"></textarea>
                            <div class="help-text">
                                ✏️ Quanto mais detalhada sua proposta, maiores as chances de ser aceita
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span id="btnText">Enviar Proposta</span>
                                <span class="loading" id="btnLoading" style="display: none;"></span>
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="if(confirm('Tem certeza que deseja sair sem enviar sua proposta?')) window.close();">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (count($historico) > 0): ?>
            <div class="history-section">
                <h3 class="history-title">📊 Suas Últimas Cotações</h3>
                <div class="history-grid">
                    <?php foreach ($historico as $item): ?>
                    <div class="history-item">
                        <div class="history-info">
                            <h4><?php echo htmlspecialchars($item['cliente']); ?> - <?php echo htmlspecialchars($item['tipo_imovel']); ?></h4>
                            <p><?php echo htmlspecialchars($item['endereco']); ?></p>
                            <p style="font-size: 12px;">
                                <?php echo date('d/m/Y', strtotime($item['data_resposta'])); ?> • 
                                Prazo: <?php echo $item['prazo_dias']; ?> dias
                            </p>
                        </div>
                        <div class="history-value">
                            R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="footer">
            <p>
                <strong>Sistema de Mudanças</strong> - Portal de Parceiros<br>
                Dúvidas? <a href="tel:1112345678">(11) 1234-5678</a> | 
                <a href="mailto:parceiros@sistemamudancas.com.br">parceiros@sistemamudancas.com.br</a>
            </p>
        </div>
    </div>

    <script>
        // Máscara monetária
        document.getElementById('valor').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            e.target.value = value;
        });

        // Validação e animação do formulário
        document.getElementById('cotacaoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const valor = document.getElementById('valor').value.replace(/\D/g, '');
            const prazo = document.getElementById('prazo_dias').value;
            
            if (!valor || valor === '0') {
                alert('Por favor, informe um valor válido');
                document.getElementById('valor').focus();
                return;
            }
            
            if (!prazo || prazo < 1) {
                alert('Por favor, informe um prazo válido');
                document.getElementById('prazo_dias').focus();
                return;
            }
            
            // Mostrar loading
            document.getElementById('btnText').style.display = 'none';
            document.getElementById('btnLoading').style.display = 'inline-block';
            document.getElementById('submitBtn').disabled = true;
            
            // Enviar formulário
            this.submit();
        });

        // Animação de entrada dos elementos
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.info-card, .form-group').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
            observer.observe(el);
        });

        // Timer countdown
        <?php if ($tempoRestante): ?>
        let totalMinutos = <?php echo ($tempoRestante['horas'] * 60) + $tempoRestante['minutos']; ?>;
        
        function atualizarTimer() {
            if (totalMinutos > 0) {
                totalMinutos--;
                const horas = Math.floor(totalMinutos / 60);
                const minutos = totalMinutos % 60;
                
                document.querySelector('.timer-text').innerHTML = 
                    `Tempo restante: ${horas}h ${minutos}min`;
                
                if (totalMinutos <= 60) {
                    document.querySelector('.timer-container').style.background = 
                        'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)';
                    document.querySelector('.timer-container').style.borderColor = '#ef4444';
                    document.querySelector('.timer-text').style.color = '#991b1b';
                }
                
                setTimeout(atualizarTimer, 60000); // Atualizar a cada minuto
            } else {
                window.location.reload();
            }
        }
        
        // Iniciar countdown
        setTimeout(atualizarTimer, 60000);
        <?php endif; ?>
    </script>
</body>
</html>