<?php
session_start();
require_once 'config.php';

// Verificar se está logado e é cliente
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

$clienteId = $_SESSION['usuario_id'];
$clienteNome = $_SESSION['usuario_nome'];

try {
    // Buscar mudanças do cliente
    $stmt = $pdo->prepare("
        SELECT m.*, v.endereco, v.tipo_imovel,
               p.valor_total, p.status as proposta_status, p.data_aceite,
               u.nome as coordenador_nome
        FROM mudancas m
        LEFT JOIN vistorias v ON m.vistoria_id = v.id
        LEFT JOIN propostas p ON m.proposta_id = p.id
        LEFT JOIN usuarios u ON m.coordenador_id = u.id
        LEFT JOIN clientes c ON m.cliente_id = c.id
        LEFT JOIN usuarios uc ON c.email = uc.email
        WHERE uc.id = ?
        ORDER BY m.data_criacao DESC
    ");
    $stmt->execute([$clienteId]);
    $mudancas = $stmt->fetchAll();

    // Buscar propostas pendentes
    $stmt = $pdo->prepare("
        SELECT p.*, v.endereco, v.tipo_imovel
        FROM propostas p
        JOIN vistorias v ON p.vistoria_id = v.id
        JOIN clientes c ON v.id = c.vistoria_id
        JOIN usuarios u ON c.email = u.email
        WHERE u.id = ? AND p.status = 'Enviada'
        ORDER BY p.data_criacao DESC
    ");
    $stmt->execute([$clienteId]);
    $propostasPendentes = $stmt->fetchAll();

    // Buscar documentos solicitados
    $stmt = $pdo->prepare("
        SELECT sd.*, m.id as mudanca_id
        FROM solicitacoes_documentos sd
        JOIN mudancas m ON sd.mudanca_id = m.id
        JOIN clientes c ON m.cliente_id = c.id
        JOIN usuarios u ON c.email = u.email
        WHERE u.id = ? AND sd.status = 'Pendente'
        ORDER BY sd.data_solicitacao DESC
    ");
    $stmt->execute([$clienteId]);
    $documentosPendentes = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log('Erro ao carregar dados do cliente: ' . $e->getMessage());
    echo $e;
}

// Função auxiliar para formatar status
function formatarStatus($status) {
    $statusFormatado = [
        'Aguardando_Documentos' => ['texto' => 'Aguardando Documentos', 'cor' => 'warning'],
        'Documentos_Recebidos' => ['texto' => 'Documentos Recebidos', 'cor' => 'info'],
        'Agendada' => ['texto' => 'Mudança Agendada', 'cor' => 'primary'],
        'Em_Embalagem' => ['texto' => 'Em Embalagem', 'cor' => 'info'],
        'Em_Transporte' => ['texto' => 'Em Transporte', 'cor' => 'info'],
        'Entregue' => ['texto' => 'Entregue', 'cor' => 'success'],
        'Finalizada' => ['texto' => 'Finalizada', 'cor' => 'secondary']
    ];
    
    return $statusFormatado[$status] ?? ['texto' => $status, 'cor' => 'secondary'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente - Sistema de Mudanças</title>
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

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            color: #666;
            font-size: 0.9rem;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        /* Container Principal */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            background: #007bff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .card-value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }

        .card-description {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
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
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-warning { background: #fff3cd; color: #856404; }
        .status-info { background: #d1ecf1; color: #0c5460; }
        .status-primary { background: #cce5ff; color: #004085; }
        .status-success { background: #d4edda; color: #155724; }
        .status-secondary { background: #e2e3e5; color: #383d41; }
        .status-danger { background: #f8d7da; color: #721c24; }

        /* Lista de Mudanças */
        .mudanca-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .mudanca-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 10px rgba(0,123,255,0.1);
        }

        .mudanca-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .mudanca-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .mudanca-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .mudanca-timeline {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .timeline-item {
            flex: 1;
        }

        .timeline-label {
            font-size: 0.8rem;
            color: #999;
            margin-bottom: 0.3rem;
        }

        .timeline-value {
            font-weight: 500;
            color: #333;
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
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

        /* Alertas */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            padding: 2rem;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 30px;
            height: 30px;
            background: #f0f0f0;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #e0e0e0;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            margin: 1rem 0;
        }

        .upload-area:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .mudanca-timeline {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#007bff" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Portal do Cliente</span>
            </div>
            <div class="user-info">
                <span class="user-name">Olá, <?php echo htmlspecialchars($clienteNome); ?></span>
                <form action="logout.php" method="POST" style="margin: 0;">
                    <button type="submit" class="btn-logout">Sair</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Container Principal -->
    <div class="container">
        <!-- Dashboard Cards -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Mudanças Ativas</h3>
                </div>
                <div class="card-value"><?php echo count(array_filter($mudancas, function($m) { 
                    return !in_array($m['status'], ['Entregue', 'Finalizada']); 
                })); ?></div>
                <p class="card-description">Mudanças em andamento</p>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: #28a745;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Mudanças Concluídas</h3>
                </div>
                <div class="card-value"><?php echo count(array_filter($mudancas, function($m) { 
                    return in_array($m['status'], ['Entregue', 'Finalizada']); 
                })); ?></div>
                <p class="card-description">Entregas realizadas</p>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: #ffc107;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                    </div>
                    <h3 class="card-title">Documentos Pendentes</h3>
                </div>
                <div class="card-value"><?php echo count($documentosPendentes); ?></div>
                <p class="card-description">Aguardando envio</p>
            </div>
        </div>

        <?php if (count($propostasPendentes) > 0): ?>
        <!-- Propostas Pendentes -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Propostas Pendentes</h2>
            </div>
            <div class="alert alert-warning">
                <strong>Atenção!</strong> Você tem propostas aguardando sua aprovação.
            </div>
            <?php foreach ($propostasPendentes as $proposta): ?>
            <div class="mudanca-item">
                <div class="mudanca-header">
                    <div class="mudanca-info">
                        <h3>Proposta #<?php echo $proposta['id']; ?></h3>
                        <div class="mudanca-details">
                            <p><strong>Origem:</strong> <?php echo htmlspecialchars($proposta['endereco']); ?></p>
                            <p><strong>Destino:</strong> <?php //echo htmlspecialchars($proposta['endereco_destino']); ?></p>
                            <p><strong>Valor:</strong> R$ <?php echo number_format($proposta['valor_total'], 2, ',', '.'); ?></p>
                        </div>
                    </div>
                    <div>
                        <a href="aceitar-proposta.php?token=<?php echo $proposta['token_aceite']; ?>" 
                           class="btn btn-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Aceitar Proposta
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if (count($documentosPendentes) > 0): ?>
        <!-- Documentos Pendentes -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Documentos Solicitados</h2>
            </div>
            <div class="alert alert-info">
                Por favor, envie os documentos solicitados para dar continuidade ao processo.
            </div>
            <?php foreach ($documentosPendentes as $doc): ?>
            <div class="mudanca-item">
                <div class="mudanca-header">
                    <div class="mudanca-info">
                        <h3><?php echo htmlspecialchars($doc['tipo_documento']); ?></h3>
                        <div class="mudanca-details">
                            <p><?php echo htmlspecialchars($doc['descricao']); ?></p>
                            <p><small>Solicitado em: <?php echo date('d/m/Y', strtotime($doc['data_solicitacao'])); ?></small></p>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="abrirModalUpload(<?php echo $doc['id']; ?>)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Enviar Documento
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Minhas Mudanças -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Minhas Mudanças</h2>
            </div>
            <?php if (count($mudancas) > 0): ?>
                <?php foreach ($mudancas as $mudanca): 
                    $status = formatarStatus($mudanca['status']);
                ?>
                <div class="mudanca-item">
                    <div class="mudanca-header">
                        <div class="mudanca-info">
                            <h3>Mudança #<?php echo $mudanca['id']; ?></h3>
                            <span class="status-badge status-<?php echo $status['cor']; ?>">
                                <?php echo $status['texto']; ?>
                            </span>
                        </div>
                        <div>
                            <button class="btn btn-outline" onclick="verDetalhes(<?php echo $mudanca['id']; ?>)">
                                Ver Detalhes
                            </button>
                        </div>
                    </div>
                    <div class="mudanca-details">
                        <p><strong>Origem:</strong> <?php echo htmlspecialchars($mudanca['endereco']); ?></p>
                        <p><strong>Destino:</strong> <?php //echo htmlspecialchars($mudanca['endereco_destino']); ?></p>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($mudanca['valor_total'], 2, ',', '.'); ?></p>
                        <?php if ($mudanca['coordenador_nome']): ?>
                        <p><strong>Coordenador:</strong> <?php echo htmlspecialchars($mudanca['coordenador_nome']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mudanca-timeline">
                        <?php if ($mudanca['data_embalagem']): ?>
                        <div class="timeline-item">
                            <div class="timeline-label">Embalagem</div>
                            <div class="timeline-value"><?php echo date('d/m/Y', strtotime($mudanca['data_embalagem'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($mudanca['data_retirada']): ?>
                        <div class="timeline-item">
                            <div class="timeline-label">Retirada</div>
                            <div class="timeline-value"><?php echo date('d/m/Y', strtotime($mudanca['data_retirada'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($mudanca['data_entrega_prevista']): ?>
                        <div class="timeline-item">
                            <div class="timeline-label">Entrega Prevista</div>
                            <div class="timeline-value"><?php echo date('d/m/Y', strtotime($mudanca['data_entrega_prevista'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($mudanca['data_entrega_real']): ?>
                        <div class="timeline-item">
                            <div class="timeline-label">Entregue em</div>
                            <div class="timeline-value"><?php echo date('d/m/Y', strtotime($mudanca['data_entrega_real'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    <p>Você ainda não possui mudanças cadastradas.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Modal Upload Documento -->
    <div id="modalUpload" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="fecharModalUpload()">×</button>
            <h2>Enviar Documento</h2>
            <form id="formUpload" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="docId" name="doc_id">
                <div class="upload-area" id="uploadArea">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p>Clique ou arraste o arquivo aqui</p>
                    <small>PDF, JPG, PNG ou DOC (máx. 10MB)</small>
                </div>
                <input type="file" id="fileInput" name="documento" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display: none;">
                <div id="filePreview" style="display: none; margin: 1rem 0;">
                    <p>Arquivo selecionado: <strong id="fileName"></strong></p>
                </div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="fecharModalUpload()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar Documento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Upload de documentos
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const formUpload = document.getElementById('formUpload');
        let docIdAtual = null;

        function abrirModalUpload(docId) {
            docIdAtual = docId;
            document.getElementById('docId').value = docId;
            document.getElementById('modalUpload').style.display = 'flex';
        }

        function fecharModalUpload() {
            document.getElementById('modalUpload').style.display = 'none';
            fileInput.value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('uploadArea').style.display = 'block';
        }

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                mostrarPreview();
            }
        });

        fileInput.addEventListener('change', mostrarPreview);

        function mostrarPreview() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('filePreview').style.display = 'block';
                document.getElementById('uploadArea').style.display = 'none';
            }
        }

        formUpload.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!fileInput.files.length) {
                alert('Por favor, selecione um arquivo.');
                return;
            }

            const formData = new FormData();
            formData.append('documento', fileInput.files[0]);
            formData.append('doc_id', docIdAtual);

            try {
                const response = await fetch('api/cliente-upload-documento.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Documento enviado com sucesso!');
                    fecharModalUpload();
                    location.reload();
                } else {
                    alert(result.error || 'Erro ao enviar documento.');
                }
            } catch (error) {
                alert('Erro ao enviar documento. Tente novamente.');
                console.error(error);
            }
        });

        // Ver detalhes da mudança
        function verDetalhes(mudancaId) {
            window.location.href = `detalhes-mudanca.php?id=${mudancaId}`;
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalUpload').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                fecharModalUpload();
            }
        });
    </script>
</body>
</html>