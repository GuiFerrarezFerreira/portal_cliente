<?php
require_once 'config.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ?");
$stmt->execute([$id]);
$vistoria = $stmt->fetch();

if(!$vistoria) {
    die("Vistoria não encontrada");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Vistoria #<?php echo $vistoria['id']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #2c3e50;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .info-item {
            padding: 5px 0;
        }
        
        .info-item strong {
            display: inline-block;
            width: 150px;
        }
        
        .observacoes {
            background-color: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .btn-print {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">Imprimir Relatório</button>
    
    <div class="header">
        <h1>Relatório de Vistoria</h1>
        <p>Documento gerado em <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    
    <div class="info-section">
        <h3>Dados do Cliente</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Nome:</strong> <?php echo htmlspecialchars($vistoria['cliente']); ?>
            </div>
            <div class="info-item">
                <strong>CPF:</strong> <?php echo htmlspecialchars($vistoria['cpf']); ?>
            </div>
            <div class="info-item">
                <strong>Telefone:</strong> <?php echo htmlspecialchars($vistoria['telefone']); ?>
            </div>
            <div class="info-item">
                <strong>Vendedor:</strong> <?php echo htmlspecialchars($vistoria['vendedor'] ?: 'Não informado'); ?>
            </div>
        </div>
    </div>
    
    <div class="info-section">
        <h3>Dados do Imóvel</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Endereço:</strong> <?php echo htmlspecialchars($vistoria['endereco']); ?>
            </div>
            <div class="info-item">
                <strong>Tipo de Imóvel:</strong> <?php echo htmlspecialchars($vistoria['tipo_imovel']); ?>
            </div>
        </div>
    </div>
    
    <div class="info-section">
        <h3>Informações da Vistoria</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Código:</strong> #<?php echo $vistoria['id']; ?>
            </div>
            <div class="info-item">
                <strong>Data da Vistoria:</strong> <?php echo date('d/m/Y H:i', strtotime($vistoria['data_vistoria'])); ?>
            </div>
            <div class="info-item">
                <strong>Status:</strong> <?php echo htmlspecialchars($vistoria['status']); ?>
            </div>
            <div class="info-item">
                <strong>Data de Criação:</strong> <?php echo date('d/m/Y H:i', strtotime($vistoria['data_criacao'])); ?>
            </div>
        </div>
        
        <?php if($vistoria['observacoes']): ?>
        <div class="observacoes">
            <strong>Observações:</strong><br>
            <?php echo nl2br(htmlspecialchars($vistoria['observacoes'])); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>Sistema de Vistoria - Relatório gerado automaticamente</p>
    </div>
</body>
</html>