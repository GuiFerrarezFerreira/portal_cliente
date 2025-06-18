<?php
// aceitar-proposta.php
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';
$erro = '';
$sucesso = false;
$proposta = null;

if (!$token) {
    $erro = 'Token inválido';
} else {
    // Buscar proposta
    $stmt = $pdo->prepare("
        SELECT p.*, v.cliente, v.endereco, v.tipo_imovel, v.data_vistoria
        FROM propostas p
        JOIN vistorias v ON p.vistoria_id = v.id
        WHERE p.token_aceite = ? AND p.status = 'Enviada'
    ");
    $stmt->execute([$token]);
    $proposta = $stmt->fetch();
    
    if (!$proposta) {
        $erro = 'Proposta não encontrada ou já foi processada';
    } else {
        // Verificar validade
        $dataValidade = new DateTime($proposta['data_criacao']);
        $dataValidade->add(new DateInterval('P' . $proposta['validade_dias'] . 'D'));
        if ($dataValidade < new DateTime()) {
            $erro = 'Esta proposta expirou';
        }
    }
}

// Processar aceite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proposta && !$erro) {
    $aceitar = $_POST['aceitar'] ?? '';
    
    if ($aceitar === 'sim') {
        // Fazer chamada à API para aceitar proposta
        $ch = curl_init('http://localhost/sistema-mudancas/api/propostas.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $sucesso = true;
        } else {
            $erro = 'Erro ao processar aceite. Por favor, tente novamente.';
        }
    } else {
        $erro = 'Proposta recusada';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceitar Proposta - Sistema de Mudanças</title>
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
            margin: 50px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
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
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .info-section h3 {
            color: #34495e;
            margin-bottom: 10px;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-item strong {
            display: inline-block;
            width: 150px;
            color: #555;
        }

        .valor-total {
            font-size: 24px;
            color: #2c3e50;
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #ecf0f1;
            border-radius: 5px;
        }

        .descricao {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-accept {
            background: #27ae60;
            color: white;
        }

        .btn-accept:hover {
            background: #229954;
        }

        .btn-reject {
            background: #e74c3c;
            color: white;
        }

        .btn-reject:hover {
            background: #c0392b;
        }

        .warning {
            background: #f39c12;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Sistema de Mudanças</h1>
            
            <?php if ($erro): ?>
                <div class="error"><?php echo htmlspecialchars($erro); ?></div>
            <?php elseif ($sucesso): ?>
                <div class="success">
                    <h2>Proposta Aceita com Sucesso!</h2>
                    <p>Obrigado por aceitar nossa proposta.</p>
                    <p>Em breve você receberá um email com as instruções para acessar o portal do cliente e acompanhar sua mudança.</p>
                    <p>Nossa equipe entrará em contato para os próximos passos.</p>
                </div>
            <?php elseif ($proposta): ?>
                <h2>Proposta de Mudança</h2>
                
                <div class="info-section">
                    <h3>Dados do Cliente</h3>
                    <div class="info-item">
                        <strong>Nome:</strong> <?php echo htmlspecialchars($proposta['cliente']); ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>Detalhes do Serviço</h3>
                    <div class="info-item">
                        <strong>Endereço:</strong> <?php echo htmlspecialchars($proposta['endereco']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Tipo de Imóvel:</strong> <?php echo htmlspecialchars($proposta['tipo_imovel']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Data da Vistoria:</strong> <?php echo date('d/m/Y', strtotime($proposta['data_vistoria'])); ?>
                    </div>
                </div>
                
                <div class="valor-total">
                    <strong>Valor Total:</strong> R$ <?php echo number_format($proposta['valor_total'], 2, ',', '.'); ?>
                </div>
                
                <div class="info-section">
                    <h3>Descrição dos Serviços</h3>
                    <div class="descricao">
                        <?php echo nl2br(htmlspecialchars($proposta['descricao_servicos'])); ?>
                    </div>
                </div>
                
                <?php
                $diasRestantes = $proposta['validade_dias'] - (new DateTime())->diff(new DateTime($proposta['data_criacao']))->days;
                if ($diasRestantes <= 5):
                ?>
                <div class="warning">
                    <strong>Atenção:</strong> Esta proposta expira em <?php echo $diasRestantes; ?> dias!
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="buttons">
                        <button type="submit" name="aceitar" value="sim" class="btn btn-accept">
                            Aceitar Proposta
                        </button>
                        <button type="submit" name="aceitar" value="nao" class="btn btn-reject">
                            Recusar Proposta
                        </button>
                    </div>
                </form>
                
                <div class="footer">
                    <p>Ao aceitar esta proposta, você concorda com os termos e condições do serviço.</p>
                    <p>Dúvidas? Entre em contato conosco pelo telefone (11) 1234-5678</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>