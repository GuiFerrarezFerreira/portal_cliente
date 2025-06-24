<?php
// relatorio.php - Sistema de Relatório de Vistoria
session_start();
require_once 'config.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Obter ID da vistoria
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    die("ID da vistoria não fornecido");
}

try {
    // Buscar dados completos da vistoria
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            u.nome as vendedor_nome_completo,
            u.email as vendedor_email,
            u.telefone as vendedor_telefone,
            DATE_FORMAT(v.data_vistoria, '%d/%m/%Y às %H:%i') as data_formatada,
            DATE_FORMAT(v.data_criacao, '%d/%m/%Y às %H:%i') as data_criacao_formatada,
            c.valor_aprovado as cotacao_valor,
            c.status as cotacao_status,
            p.valor_total as proposta_valor,
            p.status as proposta_status
        FROM vistorias v
        LEFT JOIN usuarios u ON v.vendedor_id = u.id
        LEFT JOIN cotacoes c ON v.id = c.vistoria_id
        LEFT JOIN propostas p ON v.id = p.vistoria_id
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $vistoria = $stmt->fetch();

    if (!$vistoria) {
        die("Vistoria não encontrada");
    }

    // Verificar permissão
    $isGestor = $_SESSION['usuario_tipo'] === 'gestor';
    $isVendedorResponsavel = $vistoria['vendedor'] === $_SESSION['usuario_nome'];

    if (!$isGestor && !$isVendedorResponsavel) {
        die("Você não tem permissão para visualizar este relatório");
    }

    // Buscar histórico de status
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            u.nome as usuario_nome
        FROM historico_status h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.tabela = 'vistorias' AND h.registro_id = ?
        ORDER BY h.data_mudanca DESC
        LIMIT 10
    ");
    $stmt->execute([$id]);
    $historico = $stmt->fetchAll();

} catch (Exception $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// Função para obter cor do status
function getStatusColor($status) {
    $cores = [
        'Pendente' => '#f39c12',
        'Concluída' => '#27ae60',
        'Enviada_Cotacao' => '#3498db',
        'Cotacao_Aprovada' => '#9b59b6',
        'Proposta_Enviada' => '#e67e22',
        'Proposta_Aceita' => '#16a085',
        'Em_Andamento' => '#2ecc71',
        'Finalizada' => '#34495e',
        'Cancelada' => '#e74c3c'
    ];
    return $cores[$status] ?? '#95a5a6';
}

// Função para formatar status
function formatarStatus($status) {
    $labels = [
        'Pendente' => 'Pendente',
        'Concluída' => 'Concluída',
        'Enviada_Cotacao' => 'Enviada para Cotação',
        'Cotacao_Aprovada' => 'Cotação Aprovada',
        'Proposta_Enviada' => 'Proposta Enviada',
        'Proposta_Aceita' => 'Proposta Aceita',
        'Em_Andamento' => 'Em Andamento',
        'Finalizada' => 'Finalizada',
        'Cancelada' => 'Cancelada'
    ];
    return $labels[$status] ?? $status;
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Vistoria #<?php echo $vistoria['id']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #f7fafc;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px 10px 0 0;
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
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .content {
            background-color: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 0 0 10px 10px;
        }
        
        .info-section {
            margin-bottom: 35px;
            padding: 25px;
            background-color: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-section h3 {
            color: #1a202c;
            margin-bottom: 20px;
            font-size: 20px;
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
            padding: 12px 0;
        }
        
        .info-item strong {
            display: block;
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #2d3748;
            font-size: 16px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .observacoes {
            background-color: #f8fafc;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 15px;
            white-space: pre-wrap;
        }
        
        .arquivo-info {
            background-color: #edf2f7;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .arquivo-info .icon {
            font-size: 24px;
        }
        
        .timeline {
            margin-top: 20px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 20px;
            border-left: 2px solid #e2e8f0;
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background-color: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e2e8f0;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #a0aec0;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background-color: #f7fafc;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .footer {
            margin-top: 40px;
            padding: 30px;
            text-align: center;
            background-color: #f8fafc;
            border-radius: 8px;
        }
        
        .footer p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .user-info {
            background-color: #edf2f7;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info .left {
            font-size: 14px;
            color: #4a5568;
        }
        
        .btn-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-print {
            background-color: #667eea;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back {
            background-color: #cbd5e0;
            color: #2d3748;
        }
        
        .btn-back:hover {
            background-color: #a0aec0;
        }
        
        .valores-section {
            background: linear-gradient(135deg, #f6f9fc 0%, #e9f2ff 100%);
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #c3dafe;
        }
        
        .valor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e7ff;
        }
        
        .valor-item:last-child {
            border-bottom: none;
        }
        
        .valor-label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .valor-numero {
            font-size: 24px;
            font-weight: 700;
            color: #1a365d;
        }
        
        @media print {
            body {
                background-color: white;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .header {
                background: none;
                color: #2d3748;
                border-bottom: 2px solid #2d3748;
            }
            
            .info-section {
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-info no-print">
            <div class="left">
                Relatório gerado por: <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong> 
                (<?php echo ucfirst($_SESSION['usuario_tipo']); ?>)
            </div>
            <div class="btn-actions">
                <button class="btn btn-print" onclick="window.print()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                    </svg>
                    Imprimir
                </button>
                <a href="index.php" class="btn btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Voltar
                </a>
            </div>
        </div>
        
        <div class="header">
            <h1>Relatório de Vistoria #<?php echo $vistoria['id']; ?></h1>
            <p>Documento oficial gerado em <?php echo date('d/m/Y às H:i'); ?></p>
        </div>
        
        <div class="content">
            <!-- Status da Vistoria -->
            <div style="text-align: center; margin-bottom: 30px;">
                <span class="status-badge" style="background-color: <?php echo getStatusColor($vistoria['status']); ?>">
                    <?php echo formatarStatus($vistoria['status']); ?>
                </span>
            </div>
            
            <!-- Dados do Cliente -->
            <div class="info-section">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Dados do Cliente
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Nome Completo</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['cliente']); ?></div>
                    </div>
                    <div class="info-item">
                        <strong>CPF</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['cpf']); ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Telefone</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['telefone']); ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Email</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['email'] ?: 'Não informado'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Dados do Imóvel -->
            <div class="info-section">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Dados do Imóvel
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Endereço</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['endereco']); ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Tipo de Imóvel</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['tipo_imovel']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Informações da Vistoria -->
            <div class="info-section">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Informações da Vistoria
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Data da Vistoria</strong>
                        <div class="info-value"><?php echo $vistoria['data_formatada']; ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Vendedor Responsável</strong>
                        <div class="info-value"><?php echo htmlspecialchars($vistoria['vendedor'] ?: 'Não informado'); ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Data de Criação</strong>
                        <div class="info-value"><?php echo $vistoria['data_criacao_formatada']; ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Código da Vistoria</strong>
                        <div class="info-value">#<?php echo str_pad($vistoria['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
                
                <?php if($vistoria['observacoes']): ?>
                <div class="observacoes">
                    <strong>Observações:</strong><br>
                    <?php echo nl2br(htmlspecialchars($vistoria['observacoes'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if($vistoria['arquivo_lista_seguro']): ?>
                <div class="arquivo-info">
                    <span class="icon">📎</span>
                    <div>
                        <strong>Lista de Seguro Anexada:</strong> <?php echo htmlspecialchars($vistoria['arquivo_lista_seguro']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Valores (se existirem) -->
            <?php if($vistoria['cotacao_valor'] || $vistoria['proposta_valor']): ?>
            <div class="info-section">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Valores
                </h3>
                <div class="valores-section">
                    <?php if($vistoria['cotacao_valor']): ?>
                    <div class="valor-item">
                        <span class="valor-label">Valor da Cotação Aprovada:</span>
                        <span class="valor-numero"><?php echo formatarMoeda($vistoria['cotacao_valor']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($vistoria['proposta_valor']): ?>
                    <div class="valor-item">
                        <span class="valor-label">Valor da Proposta:</span>
                        <span class="valor-numero"><?php echo formatarMoeda($vistoria['proposta_valor']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Histórico de Alterações -->
            <?php if(!empty($historico)): ?>
            <div class="info-section">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Histórico de Alterações
                </h3>
                <div class="timeline">
                    <?php foreach($historico as $evento): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('d/m/Y H:i', strtotime($evento['data_mudanca'])); ?>
                        </div>
                        <div class="timeline-content">
                            <?php if($evento['status_anterior'] && $evento['status_novo']): ?>
                                <strong>Status alterado:</strong> 
                                <?php echo formatarStatus($evento['status_anterior']); ?> → 
                                <?php echo formatarStatus($evento['status_novo']); ?>
                            <?php endif; ?>
                            
                            <?php if($evento['observacoes']): ?>
                                <br><?php echo htmlspecialchars($evento['observacoes']); ?>
                            <?php endif; ?>
                            
                            <?php if($evento['usuario_nome']): ?>
                                <br><small>Por: <?php echo htmlspecialchars($evento['usuario_nome']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rodapé -->
            <div class="footer">
                <p><strong>Sistema de Mudanças</strong></p>
                <p>Este documento é confidencial e de uso exclusivo da empresa</p>
                <p>Relatório gerado automaticamente - Não necessita assinatura</p>
            </div>
        </div>
    </div>
    
    <script>
        // Adicionar atalho para impressão (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>