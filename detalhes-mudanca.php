<?php
session_start();
require_once 'config.php';

// Verificar se está logado e é cliente
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

$mudancaId = intval($_GET['id'] ?? 0);
$clienteId = $_SESSION['usuario_id'];

if (!$mudancaId) {
    header('Location: portal-cliente.php');
    exit;
}

try {
    // Buscar detalhes da mudança
    $stmt = $pdo->prepare("
        SELECT m.*, 
               v.endereco, v.tipo_imovel, v.observacoes as vistoria_obs,
               p.valor_total, p.descricao_servicos, p.data_aceite,
               u.nome as coordenador_nome, u.telefone as coordenador_telefone,
               c.nome as cliente_nome,
               parceiro.nome as parceiro_nome, parceiro.telefone as parceiro_telefone
        FROM mudancas m
        LEFT JOIN vistorias v ON m.vistoria_id = v.id
        LEFT JOIN propostas p ON m.proposta_id = p.id
        LEFT JOIN usuarios u ON m.coordenador_id = u.id
        LEFT JOIN clientes c ON m.cliente_id = c.id
        LEFT JOIN usuarios uc ON c.email = uc.email
        LEFT JOIN cotacoes cot ON v.id = cot.vistoria_id
        LEFT JOIN parceiros parceiro ON cot.parceiro_aprovado_id = parceiro.id
        WHERE m.id = ? AND uc.id = ?
    ");
    $stmt->execute([$mudancaId, $clienteId]);
    $mudanca = $stmt->fetch();
    
    if (!$mudanca) {
        header('Location: portal-cliente.php');
        exit;
    }
    
    // Buscar documentos da mudança
    $stmt = $pdo->prepare("
        SELECT d.*, sd.tipo_documento, sd.descricao
        FROM documentos d
        LEFT JOIN solicitacoes_documentos sd ON d.tipo = sd.tipo_documento AND sd.mudanca_id = d.mudanca_id
        WHERE d.mudanca_id = ?
        ORDER BY d.data_upload DESC
    ");
    $stmt->execute([$mudancaId]);
    $documentos = $stmt->fetchAll();
    
    // Buscar histórico de status
    $stmt = $pdo->prepare("
        SELECT h.*, u.nome as usuario_nome
        FROM historico_status h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.tabela = 'mudancas' AND h.registro_id = ?
        ORDER BY h.data_mudanca DESC
    ");
    $stmt->execute([$mudancaId]);
    $historico = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log('Erro ao carregar detalhes da mudança: ' . $e->getMessage());
    header('Location: portal-cliente.php');
    exit;
}

// Função auxiliar para formatar status
function formatarStatus($status) {
    $statusFormatado = [
        'Aguardando_Documentos' => ['texto' => 'Aguardando Documentos', 'cor' => 'warning', 'icone' => 'clock'],
        'Documentos_Recebidos' => ['texto' => 'Documentos Recebidos', 'cor' => 'info', 'icone' => 'check-circle'],
        'Agendada' => ['texto' => 'Mudança Agendada', 'cor' => 'primary', 'icone' => 'calendar'],
        'Em_Embalagem' => ['texto' => 'Em Embalagem', 'cor' => 'info', 'icone' => 'package'],
        'Em_Transporte' => ['texto' => 'Em Transporte', 'cor' => 'info', 'icone' => 'truck'],
        'Entregue' => ['texto' => 'Entregue', 'cor' => 'success', 'icone' => 'check-square'],
        'Finalizada' => ['texto' => 'Finalizada', 'cor' => 'secondary', 'icone' => 'archive']
    ];
    
    return $statusFormatado[$status] ?? ['texto' => $status, 'cor' => 'secondary', 'icone' => 'info'];
}

$statusAtual = formatarStatus($mudanca['status']);

// Definir etapas do processo
$etapas = [
    'Aguardando_Documentos' => ['nome' => 'Documentos', 'icone' => 'file-text'],
    'Agendada' => ['nome' => 'Agendamento', 'icone' => 'calendar'],
    'Em_Embalagem' => ['nome' => 'Embalagem', 'icone' => 'package'],
    'Em_Transporte' => ['nome' => 'Transporte', 'icone' => 'truck'],
    'Entregue' => ['nome' => 'Entrega', 'icone' => 'check-circle']
];

// Determinar etapa atual
$etapaAtualIndex = array_search($mudanca['status'], array_keys($etapas));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mudança #<?php echo $mudancaId; ?> - Portal do Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-button {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-button:hover {
            color: #007bff;
        }

        /* Container Principal */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Status Header */
        .status-header {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .status-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
        }

        .status-warning { background: #fff3cd; color: #856404; }
        .status-info { background: #d1ecf1; color: #0c5460; }
        .status-primary { background: #cce5ff; color: #004085; }
        .status-success { background: #d4edda; color: #155724; }
        .status-secondary { background: #e2e3e5; color: #383d41; }

        /* Progress Timeline */
        .progress-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 20px;
        }

        .progress-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 4px;
            background: #e0e0e0;
            z-index: 0;
        }

        .timeline-step {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            color: #999;
            transition: all 0.3s;
        }

        .timeline-step.active .timeline-icon {
            background: #007bff;
            color: white;
            box-shadow: 0 2px 10px rgba(0,123,255,0.3);
        }

        .timeline-step.completed .timeline-icon {
            background: #28a745;
            color: white;
        }

        .timeline-label {
            font-size: 0.85rem;
            color: #666;
        }

        /* Informações Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .info-card h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .info-card h3 svg {
            color: #007bff;
        }

        .info-item {
            margin-bottom: 0.8rem;
            color: #666;
            font-size: 0.95rem;
        }

        .info-item strong {
            color: #333;
            display: inline-block;
            width: 120px;
        }

        /* Seções */
        .section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        /* Documentos */
        .document-list {
            display: grid;
            gap: 1rem;
        }

        .document-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .document-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        /* Histórico */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 8px;
            width: 10px;
            height: 10px;
            background: #007bff;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }

        .timeline-date {
            font-size: 0.85rem;
            color: #999;
            margin-bottom: 0.5rem;
        }

        /* Botões */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-outline {
            background: white;
            color: #007bff;
            border: 1px solid #007bff;
        }

        .btn-outline:hover {
            background: #007bff;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #999;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .progress-timeline {
                flex-direction: column;
                gap: 1rem;
            }

            .progress-timeline::before {
                display: none;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="portal-cliente.php" class="back-button">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Voltar ao Portal
            </a>
            <h1 style="font-size: 1.2rem; color: #333;">Mudança #<?php echo $mudancaId; ?></h1>
        </div>
    </header>

    <!-- Container Principal -->
    <div class="container">
        <!-- Status Header -->
        <div class="status-header">
            <div class="status-info">
                <div>
                    <h2 style="color: #333; margin-bottom: 0.5rem;">Status da Mudança</h2>
                    <span class="status-badge status-<?php echo $statusAtual['cor']; ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php if($statusAtual['icone'] == 'clock'): ?>
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            <?php elseif($statusAtual['icone'] == 'check-circle'): ?>
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            <?php elseif($statusAtual['icone'] == 'calendar'): ?>
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            <?php elseif($statusAtual['icone'] == 'package'): ?>
                                <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/>
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            <?php elseif($statusAtual['icone'] == 'truck'): ?>
                                <rect x="1" y="3" width="15" height="13"/>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                <circle cx="5.5" cy="18.5" r="2.5"/>
                                <circle cx="18.5" cy="18.5" r="2.5"/>
                            <?php endif; ?>
                        </svg>
                        <?php echo $statusAtual['texto']; ?>
                    </span>
                </div>
                <div style="text-align: right;">
                    <p style="color: #666; font-size: 0.9rem;">Valor Total</p>
                    <p style="font-size: 1.5rem; font-weight: 700; color: #007bff;">
                        R$ <?php echo number_format($mudanca['valor_total'] ?? $mudanca['valor_total'], 2, ',', '.'); ?>
                    </p>
                </div>
            </div>

            <!-- Progress Timeline -->
            <div class="progress-timeline">
                <?php 
                $i = 0;
                foreach($etapas as $key => $etapa): 
                    $isCompleted = $etapaAtualIndex !== false && $i < $etapaAtualIndex;
                    $isActive = $key === $mudanca['status'];
                ?>
                <div class="timeline-step <?php echo $isActive ? 'active' : ($isCompleted ? 'completed' : ''); ?>">
                    <div class="timeline-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php if($etapa['icone'] == 'file-text'): ?>
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            <?php elseif($etapa['icone'] == 'calendar'): ?>
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            <?php elseif($etapa['icone'] == 'package'): ?>
                                <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/>
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            <?php elseif($etapa['icone'] == 'truck'): ?>
                                <rect x="1" y="3" width="15" height="13"/>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                <circle cx="5.5" cy="18.5" r="2.5"/>
                                <circle cx="18.5" cy="18.5" r="2.5"/>
                            <?php elseif($etapa['icone'] == 'check-circle'): ?>
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div class="timeline-label"><?php echo $etapa['nome']; ?></div>
                </div>
                <?php 
                $i++;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Informações Grid -->
        <div class="info-grid">
            <!-- Informações da Mudança -->
            <div class="info-card">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                    </svg>
                    Informações da Mudança
                </h3>
                <div class="info-item">
                    <strong>Origem:</strong> <?php echo htmlspecialchars($mudanca['endereco']); ?>
                </div>
                <div class="info-item">
                    <strong>Destino:</strong> <?php //echo htmlspecialchars($mudanca['endereco_destino']); ?>
                </div>
                <div class="info-item">
                    <strong>Tipo Imóvel:</strong> <?php echo htmlspecialchars($mudanca['tipo_imovel']); ?>
                </div>
                <?php if($mudanca['observacoes']): ?>
                <div class="info-item">
                    <strong>Observações:</strong> <?php echo htmlspecialchars($mudanca['observacoes']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Datas -->
            <div class="info-card">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Datas Importantes
                </h3>
                <?php if($mudanca['data_aceite']): ?>
                <div class="info-item">
                    <strong>Contratação:</strong> <?php echo date('d/m/Y', strtotime($mudanca['data_aceite'])); ?>
                </div>
                <?php endif; ?>
                <?php if($mudanca['data_embalagem']): ?>
                <div class="info-item">
                    <strong>Embalagem:</strong> <?php echo date('d/m/Y', strtotime($mudanca['data_embalagem'])); ?>
                </div>
                <?php endif; ?>
                <?php if($mudanca['data_retirada']): ?>
                <div class="info-item">
                    <strong>Retirada:</strong> <?php echo date('d/m/Y', strtotime($mudanca['data_retirada'])); ?>
                </div>
                <?php endif; ?>
                <?php if($mudanca['data_entrega_prevista']): ?>
                <div class="info-item">
                    <strong>Entrega Prevista:</strong> <?php echo date('d/m/Y', strtotime($mudanca['data_entrega_prevista'])); ?>
                </div>
                <?php endif; ?>
                <?php if($mudanca['data_entrega_real']): ?>
                <div class="info-item">
                    <strong>Entregue em:</strong> <?php echo date('d/m/Y', strtotime($mudanca['data_entrega_real'])); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Contatos -->
            <div class="info-card">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                    Contatos
                </h3>
                <?php if($mudanca['coordenador_nome']): ?>
                <div class="info-item">
                    <strong>Coordenador:</strong> <?php echo htmlspecialchars($mudanca['coordenador_nome']); ?>
                </div>
                <?php if($mudanca['coordenador_telefone']): ?>
                <div class="info-item">
                    <strong>Telefone:</strong> <?php echo htmlspecialchars($mudanca['coordenador_telefone']); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if($mudanca['parceiro_nome']): ?>
                <div class="info-item">
                    <strong>Transportadora:</strong> <?php echo htmlspecialchars($mudanca['parceiro_nome']); ?>
                </div>
                <?php if($mudanca['parceiro_telefone']): ?>
                <div class="info-item">
                    <strong>Telefone:</strong> <?php echo htmlspecialchars($mudanca['parceiro_telefone']); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documentos -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Documentos</h2>
            </div>
            <?php if(count($documentos) > 0): ?>
                <div class="document-list">
                    <?php foreach($documentos as $doc): ?>
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div>
                                <p style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($doc['tipo']); ?></p>
                                <p style="font-size: 0.85rem; color: #666;">
                                    Enviado em <?php echo date('d/m/Y às H:i', strtotime($doc['data_upload'])); ?>
                                </p>
                            </div>
                        </div>
                        <a href="api/download-documento.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline" target="_blank">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Baixar
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Nenhum documento enviado ainda.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Histórico -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Histórico de Atualizações</h2>
            </div>
            <?php if(count($historico) > 0): ?>
                <div class="timeline">
                    <?php foreach($historico as $evento): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <?php echo date('d/m/Y às H:i', strtotime($evento['data_mudanca'])); ?>
                            </div>
                            <p style="color: #333; font-weight: 500;">
                                <?php echo htmlspecialchars($evento['observacoes']); ?>
                            </p>
                            <?php if($evento['usuario_nome']): ?>
                            <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                                Por: <?php echo htmlspecialchars($evento['usuario_nome']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Nenhuma atualização registrada ainda.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>