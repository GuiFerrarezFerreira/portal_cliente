<?php
require_once 'session.php';
require_once 'config.php';

// Verificar se é cotador ou gestor
if(!isset($_SESSION['usuario_tipo']) || ($_SESSION['usuario_tipo'] !== 'cotador' && $_SESSION['usuario_tipo'] !== 'gestor')) {
    header('Location: index.php');
    exit;
}

// Função auxiliar para verificar se é cotador
function isCotador() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'cotador';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Cotações - Sistema de Vistoria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background-color: #f5f7fa;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        h1 {
            font-size: 28px;
            font-weight: 600;
        }

        h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2d3748;
        }

        h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #4a5568;
        }

        .btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-success {
            background-color: #27ae60;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-warning {
            background-color: #f39c12;
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-info {
            background-color: #9b59b6;
        }

        .btn-info:hover {
            background-color: #8e44ad;
        }

        .btn-secondary {
            background-color: #95a5a6;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-logout {
            background-color: #e74c3c;
        }

        /* Cards e Layout */
        .card {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            margin: -25px -25px 25px -25px;
            border-radius: 10px 10px 0 0;
            background-color: #f8f9fa;
        }

        .card-header h2 {
            margin: 0;
            color: #1a202c;
        }

        /* Grid de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.blue::before {
            background: linear-gradient(90deg, #3498db, #2980b9);
        }

        .stat-card.green::before {
            background: linear-gradient(90deg, #27ae60, #229954);
        }

        .stat-card.yellow::before {
            background: linear-gradient(90deg, #f39c12, #e67e22);
        }

        .stat-card.red::before {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a202c;
        }

        .stat-label {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 48px;
            opacity: 0.1;
        }

        /* Tabelas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background-color: #f8f9fa;
            color: #4a5568;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-aguardando_parceiros {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-em_cotacao {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-cotacoes_recebidas {
            background-color: #ede9fe;
            color: #6b21a8;
        }

        .status-aprovada {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejeitada {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-cancelada {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 40px auto;
            padding: 0;
            width: 90%;
            max-width: 800px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow: hidden;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f8f9fa;
        }

        .modal-header h2 {
            margin: 0;
            color: #1a202c;
        }

        .modal-body {
            padding: 30px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s;
        }

        .close:hover {
            color: #1f2937;
        }

        /* Elementos Específicos de Cotação */
        .cotacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .prazo-alerta {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .parceiro-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background-color: white;
        }

        .parceiro-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .parceiro-card.melhor-preco {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .melhor-preco-badge {
            background-color: #10b981;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
        }

        .parceiro-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .parceiro-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }

        .valor-destaque {
            font-size: 24px;
            color: #10b981;
        }

        .observacoes-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 3px solid #3498db;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        /* Abas */
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            background-color: transparent;
            border: none;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.3s ease;
            position: relative;
        }

        .tab:hover {
            color: #3498db;
        }

        .tab.active {
            color: #3498db;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #3498db;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Filtros */
        .filtros {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filtro-item {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filtro-item label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filtro-item select {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background-color: white;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .filtro-item select:focus {
            outline: none;
            border-color: #3498db;
        }

        /* Estados Vazios */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Loading */
        .spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-info {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-warning {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .alert-danger {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .tab {
                white-space: nowrap;
            }

            .parceiro-info {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 10px;
            }
        }

        /* Animações e Transições */
        .fade-in {
            animation: fadeInContent 0.3s ease;
        }

        @keyframes fadeInContent {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbar Customizada */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <h1>Gerenciamento de Cotações</h1>
                <div class="user-info">
                    <span>Olá, <?php echo htmlspecialchars($usuario_nome); ?></span>
                    <span class="user-badge"><?php echo ucfirst($usuario_tipo); ?></span>
                    <a href="index.php" class="btn btn-secondary btn-sm">Voltar</a>
                    <a href="logout.php" class="btn btn-logout btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if(isCotador()): ?>
        <div class="alert alert-info">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div>
                <strong>Área do Cotador:</strong> Você pode gerenciar todas as cotações em andamento e aprovar/rejeitar propostas dos parceiros.
            </div>
        </div>
        <?php endif; ?>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">📋</div>
                <div class="stat-value" id="totalCotacoes">0</div>
                <div class="stat-label">Total de Cotações</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon">⏳</div>
                <div class="stat-value" id="aguardandoResposta">0</div>
                <div class="stat-label">Aguardando Resposta</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">✅</div>
                <div class="stat-value" id="aprovadas">0</div>
                <div class="stat-label">Aprovadas</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">📊</div>
                <div class="stat-value" id="taxaResposta">0%</div>
                <div class="stat-label">Taxa de Resposta</div>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs">
            <button class="tab active" onclick="mostrarAba('pendentes')">Cotações Pendentes</button>
            <button class="tab" onclick="mostrarAba('todas')">Todas as Cotações</button>
            <button class="tab" onclick="mostrarAba('parceiros')">Performance Parceiros</button>
        </div>

        <!-- Conteúdo das Abas -->
        <div id="pendentes" class="tab-content active">
            <div class="card">
                <div class="filtros">
                    <div class="filtro-item">
                        <label>Status</label>
                        <select id="filtroStatusPendentes" onchange="filtrarCotacoes()">
                            <option value="">Todos</option>
                            <option value="Aguardando_Parceiros">Aguardando Parceiros</option>
                            <option value="Em_Cotacao">Em Cotação</option>
                            <option value="Cotacoes_Recebidas">Cotações Recebidas</option>
                        </select>
                    </div>
                    <div class="filtro-item">
                        <label>Período</label>
                        <select id="filtroPeriodoPendentes" onchange="filtrarCotacoes()">
                            <option value="7">Últimos 7 dias</option>
                            <option value="30">Últimos 30 dias</option>
                            <option value="90">Últimos 90 dias</option>
                        </select>
                    </div>
                </div>

                <div id="listaCotacoesPendentes">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>

        <div id="todas" class="tab-content">
            <div class="card">
                <div class="filtros">
                    <div class="filtro-item">
                        <label>Status</label>
                        <select id="filtroStatusTodas" onchange="filtrarCotacoes()">
                            <option value="">Todos</option>
                            <option value="Aguardando_Parceiros">Aguardando Parceiros</option>
                            <option value="Em_Cotacao">Em Cotação</option>
                            <option value="Cotacoes_Recebidas">Cotações Recebidas</option>
                            <option value="Aprovada">Aprovadas</option>
                            <option value="Rejeitada">Rejeitadas</option>
                            <option value="Cancelada">Canceladas</option>
                        </select>
                    </div>
                    <div class="filtro-item">
                        <label>Período</label>
                        <select id="filtroPeriodoTodas" onchange="filtrarCotacoes()">
                            <option value="30">Últimos 30 dias</option>
                            <option value="90">Últimos 90 dias</option>
                            <option value="365">Último ano</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Endereço</th>
                            <th>Data Criação</th>
                            <th>Status</th>
                            <th>Respostas</th>
                            <th>Valor Aprovado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="listaCotacoesTodas">
                        <tr>
                            <td colspan="8" style="text-align: center;">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="parceiros" class="tab-content">
            <div class="card">
                <h3>Ranking de Parceiros - Últimos 30 dias</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Posição</th>
                            <th>Parceiro</th>
                            <th>Cotações Respondidas</th>
                            <th>Cotações Ganhas</th>
                            <th>Taxa de Sucesso</th>
                            <th>Valor Médio</th>
                            <th>Tempo Resposta Médio</th>
                        </tr>
                    </thead>
                    <tbody id="rankingParceiros">
                        <tr>
                            <td colspan="7" style="text-align: center;">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes da Cotação -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitulo">Detalhes da Cotação</h2>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalConteudo">
                <!-- Conteúdo será inserido dinamicamente -->
            </div>
        </div>
    </div>

    <!-- Modal de Aprovação -->
    <div id="modalAprovacao" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Aprovar Cotação</h2>
                <span class="close" onclick="fecharModalAprovacao()">&times;</span>
            </div>
            <div class="modal-body" id="modalAprovacaoConteudo">
                <!-- Conteúdo será inserido dinamicamente -->
            </div>
        </div>
    </div>

    <!-- Modal de Rejeição -->
    <div id="modalRejeicao" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Rejeitar Cotação</h2>
                <span class="close" onclick="fecharModalRejeicao()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formRejeicao">
                    <input type="hidden" id="cotacaoIdRejeitar" value="">
                    <div style="margin-bottom: 20px;">
                        <label for="motivoRejeicao" style="display: block; margin-bottom: 10px; font-weight: 600; color: #374151;">
                            Motivo da Rejeição:
                        </label>
                        <textarea id="motivoRejeicao" rows="4" 
                                  style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;" 
                                  placeholder="Informe o motivo da rejeição..." required></textarea>
                    </div>
                    <div class="action-buttons" style="justify-content: flex-start;">
                        <button type="button" class="btn btn-secondary" onclick="fecharModalRejeicao()">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Rejeição</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        let cotacoes = [];
        let cotacoesPendentes = [];
        let parceirosRanking = [];
        let cotacaoAtual = null;
        const isCotador = <?php echo isCotador() ? 'true' : 'false'; ?>;
        const isGestor = <?php echo isGestor() ? 'true' : 'false'; ?>;

        // Função para mostrar aba
        function mostrarAba(aba) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Ativar aba selecionada
            if (aba === 'pendentes') {
                document.querySelector('.tab:nth-child(1)').classList.add('active');
                document.getElementById('pendentes').classList.add('active');
                carregarCotacoesPendentes();
            } else if (aba === 'todas') {
                document.querySelector('.tab:nth-child(2)').classList.add('active');
                document.getElementById('todas').classList.add('active');
                carregarTodasCotacoes();
            } else if (aba === 'parceiros') {
                document.querySelector('.tab:nth-child(3)').classList.add('active');
                document.getElementById('parceiros').classList.add('active');
                carregarRankingParceiros();
            }
        }

        // Função para carregar estatísticas
        async function carregarEstatisticas() {
            try {
                const response = await fetch('api/cotacao.php');
                const data = await response.json();
                
                if (!Array.isArray(data)) {
                    console.error('Dados inválidos recebidos:', data);
                    return;
                }
                
                // Calcular estatísticas
                let total = data.length;
                let aguardando = data.filter(c => ['Aguardando_Parceiros', 'Em_Cotacao'].includes(c.status)).length;
                let aprovadas = data.filter(c => c.status === 'Aprovada').length;
                
                // Taxa de resposta
                let totalEnviadas = 0;
                let totalRespondidas = 0;
                data.forEach(cotacao => {
                    totalEnviadas += parseInt(cotacao.total_parceiros || 0);
                    totalRespondidas += parseInt(cotacao.parceiros_responderam || 0);
                });
                
                let taxaResposta = totalEnviadas > 0 ? Math.round((totalRespondidas / totalEnviadas) * 100) : 0;
                
                // Atualizar DOM
                document.getElementById('totalCotacoes').textContent = total;
                document.getElementById('aguardandoResposta').textContent = aguardando;
                document.getElementById('aprovadas').textContent = aprovadas;
                document.getElementById('taxaResposta').textContent = taxaResposta + '%';
                
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
            }
        }

        // Função para carregar cotações pendentes
        async function carregarCotacoesPendentes() {
            const container = document.getElementById('listaCotacoesPendentes');
            container.innerHTML = '<div class="spinner"></div>';
            
            try {
                const response = await fetch('api/cotacao.php');
                const todasCotacoes = await response.json();
                
                if (!Array.isArray(todasCotacoes)) {
                    throw new Error('Dados inválidos recebidos');
                }
                
                // Filtrar apenas pendentes
                cotacoesPendentes = todasCotacoes.filter(c => 
                    !['Aprovada', 'Rejeitada', 'Cancelada'].includes(c.status)
                );
                
                if (cotacoesPendentes.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <h3>Nenhuma cotação pendente</h3>
                            <p>Todas as cotações foram processadas</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                for (const cotacao of cotacoesPendentes) {
                    // Buscar detalhes completos
                    try {
                        const detailResponse = await fetch(`api/cotacao.php?id=${cotacao.id}`);
                        const cotacaoCompleta = await detailResponse.json();
                        html += renderizarCardCotacao(cotacaoCompleta || cotacao);
                    } catch (error) {
                        console.error('Erro ao buscar detalhes da cotação:', error);
                        html += renderizarCardCotacao(cotacao);
                    }
                }
                
                container.innerHTML = html;
                
            } catch (error) {
                console.error('Erro ao carregar cotações pendentes:', error);
                container.innerHTML = '<div class="alert alert-danger">Erro ao carregar cotações</div>';
            }
        }

        // Função para renderizar card de cotação
        function renderizarCardCotacao(cotacao) {
            const statusClass = cotacao.status.toLowerCase().replace('_', '');
            let prazoHtml = '';
            
            // Mostrar prazo restante se aplicável
            if (cotacao.horas_restantes > 0 && cotacao.status === 'Aguardando_Parceiros') {
                prazoHtml = `
                    <div class="prazo-alerta">
                        <span>⏰</span>
                        <span>${cotacao.horas_restantes} horas restantes para receber respostas</span>
                    </div>
                `;
            }
            
            // Estatísticas
            const responderam = cotacao.parceiros_responderam || 0;
            const total = cotacao.total_parceiros || 0;
            
            return `
                <div class="card fade-in" style="margin-bottom: 20px;">
                    <div class="cotacao-header">
                        <div>
                            <h3>Cotação #${cotacao.id} - ${cotacao.cliente || 'Cliente'}</h3>
                            <p style="margin: 5px 0; color: #6b7280;">
                                <strong>Endereço:</strong> ${cotacao.endereco || 'Não informado'}<br>
                                <strong>Criada em:</strong> ${formatarDataHora(cotacao.data_criacao)}
                            </p>
                        </div>
                        <span class="status-badge status-${statusClass}">
                            ${getStatusLabel(cotacao.status)}
                        </span>
                    </div>
                    
                    ${prazoHtml}
                    
                    <div class="stats-grid" style="margin-bottom: 20px;">
                        <div class="stat-card blue">
                            <div class="stat-value">${responderam}/${total}</div>
                            <div class="stat-label">Respostas</div>
                        </div>
                        ${cotacao.estatisticas?.menor_valor ? `
                        <div class="stat-card green">
                            <div class="stat-value">R$ ${formatarMoeda(cotacao.estatisticas.menor_valor)}</div>
                            <div class="stat-label">Menor Valor</div>
                        </div>
                        <div class="stat-card yellow">
                            <div class="stat-value">R$ ${formatarMoeda(cotacao.estatisticas.media_valor)}</div>
                            <div class="stat-label">Valor Médio</div>
                        </div>
                        <div class="stat-card red">
                            <div class="stat-value">R$ ${formatarMoeda(cotacao.estatisticas.maior_valor)}</div>
                            <div class="stat-label">Maior Valor</div>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-info" onclick="verDetalhesCotacao(${cotacao.id})">
                            Ver Detalhes
                        </button>
                        ${cotacao.status === 'Cotacoes_Recebidas' && (isCotador || isGestor) ? `
                            <button class="btn btn-warning" onclick="reenviarCotacao(${cotacao.id})">
                                Reenviar para Pendentes
                            </button>
                            <button class="btn btn-danger" onclick="abrirModalRejeicao(${cotacao.id})">
                                Rejeitar Cotação
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Função para carregar todas as cotações
        async function carregarTodasCotacoes() {
            const tbody = document.getElementById('listaCotacoesTodas');
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;"><div class="spinner"></div></td></tr>';
            
            try {
                const response = await fetch('api/cotacao.php');
                cotacoes = await response.json();
                
                if (!Array.isArray(cotacoes)) {
                    throw new Error('Dados inválidos recebidos');
                }
                
                let html = '';
                cotacoes.forEach(cotacao => {
                    const statusClass = cotacao.status.toLowerCase().replace('_', '');
                    
                    html += `
                        <tr>
                            <td>#${cotacao.id}</td>
                            <td>${cotacao.cliente || 'N/A'}</td>
                            <td>${cotacao.endereco || 'N/A'}</td>
                            <td>${formatarData(cotacao.data_criacao)}</td>
                            <td><span class="status-badge status-${statusClass}">${getStatusLabel(cotacao.status)}</span></td>
                            <td>${cotacao.parceiros_responderam || 0}/${cotacao.total_parceiros || 0}</td>
                            <td>${cotacao.valor_aprovado ? 'R$ ' + formatarMoeda(cotacao.valor_aprovado) : '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="verDetalhesCotacao(${cotacao.id})">
                                    Detalhes
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html || '<tr><td colspan="8" style="text-align: center;">Nenhuma cotação encontrada</td></tr>';
                
            } catch (error) {
                console.error('Erro ao carregar cotações:', error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #e74c3c;">Erro ao carregar cotações</td></tr>';
            }
        }

        // Função para carregar ranking de parceiros
        async function carregarRankingParceiros() {
            const tbody = document.getElementById('rankingParceiros');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;"><div class="spinner"></div></td></tr>';
            
            try {
                const response = await fetch('api/parceiros.php?ranking=1&periodo=30');
                parceirosRanking = await response.json();
                
                let html = '';
                parceirosRanking.forEach((parceiro, index) => {
                    const taxaSucesso = parceiro.total_respostas > 0 
                        ? ((parceiro.cotacoes_ganhas / parceiro.total_respostas) * 100).toFixed(1) 
                        : 0;
                    
                    html += `
                        <tr>
                            <td>${index + 1}º</td>
                            <td>${parceiro.nome}</td>
                            <td>${parceiro.total_respostas || 0}</td>
                            <td>${parceiro.cotacoes_ganhas || 0}</td>
                            <td>${taxaSucesso}%</td>
                            <td>R$ ${formatarMoeda(parceiro.valor_medio || 0)}</td>
                            <td>${Math.round(parceiro.tempo_resposta_medio || 0)}h</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html || '<tr><td colspan="7" style="text-align: center;">Nenhum parceiro encontrado</td></tr>';
                
            } catch (error) {
                console.error('Erro ao carregar ranking:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #e74c3c;">Erro ao carregar ranking</td></tr>';
            }
        }

        // Função para ver detalhes da cotação
        async function verDetalhesCotacao(cotacaoId) {
            const modal = document.getElementById('modalDetalhes');
            const conteudo = document.getElementById('modalConteudo');
            
            conteudo.innerHTML = '<div class="spinner"></div>';
            modal.style.display = 'block';
            
            try {
                const response = await fetch(`api/cotacao.php?id=${cotacaoId}`);
                cotacaoAtual = await response.json();
                
                let html = `
                    <div class="cotacao-header">
                        <div>
                            <h3>Cliente: ${cotacaoAtual.cliente || 'Não informado'}</h3>
                            <p><strong>Endereço:</strong> ${cotacaoAtual.endereco || 'Não informado'}</p>
                            <p><strong>Tipo Imóvel:</strong> ${cotacaoAtual.tipo_imovel || 'Não informado'}</p>
                        </div>
                        <span class="status-badge status-${cotacaoAtual.status.toLowerCase().replace('_', '')}">
                            ${getStatusLabel(cotacaoAtual.status)}
                        </span>
                    </div>
                `;
                
                // Mostrar parceiros e suas propostas
                if (cotacaoAtual.parceiros && cotacaoAtual.parceiros.length > 0) {
                    html += '<h3 style="margin-top: 30px;">Propostas dos Parceiros</h3>';
                    
                    // Ordenar por valor
                    const parceirosOrdenados = [...cotacaoAtual.parceiros].sort((a, b) => {
                        if (!a.valor) return 1;
                        if (!b.valor) return -1;
                        return a.valor - b.valor;
                    });
                    
                    parceirosOrdenados.forEach((parceiro, index) => {
                        const melhorPreco = index === 0 && parceiro.valor;
                        
                        html += `
                            <div class="parceiro-card ${melhorPreco ? 'melhor-preco' : ''}">
                                <div class="parceiro-header">
                                    <div>
                                        <strong>${parceiro.parceiro_nome}</strong>
                                        ${melhorPreco ? '<span class="melhor-preco-badge">MELHOR PREÇO</span>' : ''}
                                    </div>
                                    ${parceiro.data_resposta ? 
                                        `<span style="color: #10b981;">✓ Respondido em ${formatarDataHora(parceiro.data_resposta)}</span>` : 
                                        '<span style="color: #f59e0b;">⏳ Aguardando resposta</span>'
                                    }
                                </div>
                                
                                ${parceiro.valor ? `
                                    <div class="parceiro-info">
                                        <div class="info-item">
                                            <div class="info-label">Valor</div>
                                            <div class="info-value valor-destaque">R$ ${formatarMoeda(parceiro.valor)}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Prazo</div>
                                            <div class="info-value">${parceiro.prazo_dias} dias</div>
                                        </div>
                                    </div>
                                    
                                    ${parceiro.observacoes ? `
                                        <div class="observacoes-box">
                                            <strong>Observações:</strong><br>
                                            ${parceiro.observacoes}
                                        </div>
                                    ` : ''}
                                    
                                    ${cotacaoAtual.status === 'Cotacoes_Recebidas' && (isCotador || isGestor) ? `
                                        <div class="action-buttons">
                                            <button class="btn btn-success" 
                                                    onclick="abrirModalAprovacao(${cotacaoAtual.id}, ${parceiro.id}, ${parceiro.valor}, '${parceiro.parceiro_nome}')">
                                                Aprovar Esta Proposta
                                            </button>
                                        </div>
                                    ` : ''}
                                ` : ''}
                            </div>
                        `;
                    });
                } else {
                    html += '<p style="text-align: center; margin: 40px 0;">Nenhuma proposta recebida ainda.</p>';
                }
                
                conteudo.innerHTML = html;
                
            } catch (error) {
                console.error('Erro ao carregar detalhes:', error);
                conteudo.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes da cotação</div>';
            }
        }

        // Função para abrir modal de aprovação
        function abrirModalAprovacao(cotacaoId, parceiroId, valor, parceiroNome) {
            const modal = document.getElementById('modalAprovacao');
            const conteudo = document.getElementById('modalAprovacaoConteudo');
            
            conteudo.innerHTML = `
                <form id="formAprovacao">
                    <input type="hidden" id="aprovacaoCotacaoId" value="${cotacaoId}">
                    <input type="hidden" id="aprovacaoParceiroId" value="${parceiroId}">
                    <input type="hidden" id="aprovacaoValor" value="${valor}">
                    
                    <div class="alert alert-info">
                        <strong>Confirmar Aprovação</strong><br>
                        Você está prestes a aprovar a proposta de <strong>${parceiroNome}</strong>
                        no valor de <strong>R$ ${formatarMoeda(valor)}</strong>.
                    </div>
                    
                    <p style="margin-top: 20px;">Esta ação não pode ser desfeita. Deseja continuar?</p>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" onclick="fecharModalAprovacao()">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar Aprovação</button>
                    </div>
                </form>
            `;
            
            modal.style.display = 'block';
            
            // Adicionar evento ao formulário
            document.getElementById('formAprovacao').addEventListener('submit', aprovarCotacao);
        }

        // Função para aprovar cotação
        async function aprovarCotacao(e) {
            e.preventDefault();
            
            const cotacaoId = document.getElementById('aprovacaoCotacaoId').value;
            const parceiroId = document.getElementById('aprovacaoParceiroId').value;
            const valor = document.getElementById('aprovacaoValor').value;
            
            try {
                const response = await fetch('api/cotacao.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        aprovar: true,
                        cotacao_id: cotacaoId,
                        parceiro_id: parceiroId,
                        valor_aprovado: valor
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Cotação aprovada com sucesso!');
                    fecharModalAprovacao();
                    fecharModal();
                    carregarCotacoesPendentes();
                    carregarEstatisticas();
                } else {
                    alert('Erro ao aprovar cotação: ' + (result.error || 'Erro desconhecido'));
                }
                
            } catch (error) {
                console.error('Erro ao aprovar cotação:', error);
                alert('Erro ao processar aprovação');
            }
        }

        // Função para abrir modal de rejeição
        function abrirModalRejeicao(cotacaoId) {
            document.getElementById('cotacaoIdRejeitar').value = cotacaoId;
            document.getElementById('modalRejeicao').style.display = 'block';
            document.getElementById('motivoRejeicao').focus();
        }

        // Função para rejeitar cotação
        document.getElementById('formRejeicao').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const cotacaoId = document.getElementById('cotacaoIdRejeitar').value;
            const motivo = document.getElementById('motivoRejeicao').value;
            
            try {
                const response = await fetch('api/cotacao.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        rejeitar: true,
                        cotacao_id: cotacaoId,
                        motivo: motivo
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Cotação rejeitada!');
                    fecharModalRejeicao();
                    carregarCotacoesPendentes();
                    carregarEstatisticas();
                } else {
                    alert('Erro ao rejeitar cotação: ' + (result.error || 'Erro desconhecido'));
                }
                
            } catch (error) {
                console.error('Erro ao rejeitar cotação:', error);
                alert('Erro ao processar rejeição');
            }
        });

        // Função para reenviar cotação
        async function reenviarCotacao(cotacaoId) {
            if (!confirm('Deseja reenviar esta cotação para os parceiros que não responderam?')) {
                return;
            }
            
            try {
                const response = await fetch('api/cotacao.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reenviar: true,
                        cotacao_id: cotacaoId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message || 'Cotação reenviada com sucesso!');
                    carregarCotacoesPendentes();
                } else {
                    alert('Erro: ' + (result.error || 'Erro ao reenviar'));
                }
                
            } catch (error) {
                console.error('Erro ao reenviar cotação:', error);
                alert('Erro ao processar reenvio');
            }
        }

        // Funções auxiliares
        function formatarData(dataString) {
            if (!dataString) return '-';
            const data = new Date(dataString);
            return data.toLocaleDateString('pt-BR');
        }

        function formatarDataHora(dataString) {
            if (!dataString) return '-';
            const data = new Date(dataString);
            return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        function formatarMoeda(valor) {
            if (!valor) return '0,00';
            return parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function getStatusLabel(status) {
            const labels = {
                'Aguardando_Parceiros': 'Aguardando Parceiros',
                'Em_Cotacao': 'Em Cotação',
                'Cotacoes_Recebidas': 'Cotações Recebidas',
                'Aprovada': 'Aprovada',
                'Rejeitada': 'Rejeitada',
                'Cancelada': 'Cancelada'
            };
            return labels[status] || status;
        }

        // Funções de modal
        function fecharModal() {
            document.getElementById('modalDetalhes').style.display = 'none';
            cotacaoAtual = null;
        }

        function fecharModalAprovacao() {
            document.getElementById('modalAprovacao').style.display = 'none';
        }

        function fecharModalRejeicao() {
            document.getElementById('modalRejeicao').style.display = 'none';
            document.getElementById('formRejeicao').reset();
        }

        // Eventos de clique fora do modal
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                if (event.target.id === 'modalDetalhes') {
                    fecharModal();
                } else if (event.target.id === 'modalAprovacao') {
                    fecharModalAprovacao();
                } else if (event.target.id === 'modalRejeicao') {
                    fecharModalRejeicao();
                }
            }
        }

        // Função para filtrar cotações
        function filtrarCotacoes() {
            // Implementar filtros se necessário
            carregarCotacoesPendentes();
            carregarTodasCotacoes();
        }

        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarEstatisticas();
            carregarCotacoesPendentes();
        });

        // Atualizar dados a cada 30 segundos
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                carregarEstatisticas();
                const abaAtiva = document.querySelector('.tab.active');
                if (abaAtiva) {
                    const texto = abaAtiva.textContent.trim();
                    if (texto.includes('Pendentes')) {
                        carregarCotacoesPendentes();
                    } else if (texto.includes('Todas')) {
                        carregarTodasCotacoes();
                    }
                }
            }
        }, 30000);
    </script>
</body>
</html>