<?php
require_once 'session.php';
require_once 'config.php';

// Verificar se é cotador ou gestor
if(!isCotador() && !isGestor()) {
    header('Location: index.php');
    exit;
}

$isCotador = isCotador();
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
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
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

        h1, h2, h3 {
            margin-bottom: 20px;
        }

        .btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2980b9;
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
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-logout {
            background-color: #e74c3c;
            font-size: 14px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
        }

        .card-header h2 {
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
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
            background-color: #3498db;
        }

        .stat-card.green::before {
            background-color: #27ae60;
        }

        .stat-card.yellow::before {
            background-color: #f39c12;
        }

        .stat-card.red::before {
            background-color: #e74c3c;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 48px;
            opacity: 0.1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-aguardando_parceiros {
            background-color: #3498db;
            color: white;
        }

        .status-em_cotacao {
            background-color: #f39c12;
            color: white;
        }

        .status-cotacoes_recebidas {
            background-color: #9b59b6;
            color: white;
        }

        .status-aprovada {
            background-color: #27ae60;
            color: white;
        }

        .status-rejeitada {
            background-color: #e74c3c;
            color: white;
        }

        .status-cancelada {
            background-color: #95a5a6;
            color: white;
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
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .cotacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .prazo-alerta {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .parceiro-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .parceiro-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .parceiro-card.melhor-preco {
            border-color: #27ae60;
            background-color: #f0fdf4;
        }

        .melhor-preco-badge {
            background-color: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
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
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: bold;
        }

        .valor-destaque {
            font-size: 24px;
            color: #27ae60;
        }

        .observacoes-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            background-color: #ecf0f1;
            transition: background-color 0.3s;
        }

        .tab:hover {
            background-color: #dfe6e9;
        }

        .tab.active {
            background-color: #3498db;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

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
            color: #666;
            margin-bottom: 5px;
        }

        .filtro-item select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .spinner {
            border: 3px solid #f3f3f3;
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

        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
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
        <?php if($isCotador): ?>
        <div class="alert alert-info">
            <strong>Área do Cotador:</strong> Você pode gerenciar todas as cotações em andamento e aprovar/rejeitar propostas dos parceiros.
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
            <div class="tab active" onclick="mostrarAba('pendentes')">Cotações Pendentes</div>
            <div class="tab" onclick="mostrarAba('todas')">Todas as Cotações</div>
            <div class="tab" onclick="mostrarAba('parceiros')">Performance Parceiros</div>
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

                <table id="tabelaCotacoes">
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
                            <td colspan="8" class="text-center">
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
                            <td colspan="7" class="text-center">
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
            <div id="modalConteudo">
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
            <div id="modalAprovacaoConteudo">
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
            <form id="formRejeicao">
                <input type="hidden" id="cotacaoIdRejeitar" value="">
                <div style="margin-bottom: 20px;">
                    <label for="motivoRejeicao" style="display: block; margin-bottom: 10px; font-weight: bold;">
                        Motivo da Rejeição:
                    </label>
                    <textarea id="motivoRejeicao" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required
                              placeholder="Informe o motivo da rejeição..."></textarea>
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalRejeicao()">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Rejeição</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variáveis globais
        let cotacoes = [];
        let cotacoesPendentes = [];
        let parceirosRanking = [];
        let cotacaoAtual = null;
        const isCotador = <?php echo $isCotador ? 'true' : 'false'; ?>;
        const isGestor = <?php echo isGestor() ? 'true' : 'false'; ?>;

        // Função para mostrar aba
        function mostrarAba(aba) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Adiciona active na aba selecionada
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
                
                // Calcular estatísticas
                let total = data.length;
                let aguardando = data.filter(c => ['Aguardando_Parceiros', 'Em_Cotacao'].includes(c.status)).length;
                let aprovadas = data.filter(c => c.status === 'Aprovada').length;
                
                // Taxa de resposta
                let totalEnviadas = 0;
                let totalRespondidas = 0;
                data.forEach(cotacao => {
                    if (cotacao.estatisticas) {
                        totalEnviadas += cotacao.estatisticas.total_parceiros || 0;
                        totalRespondidas += cotacao.estatisticas.parceiros_responderam || 0;
                    }
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
                const response = await fetch('api/cotacao.php?status_not=Aprovada,Rejeitada,Cancelada');
                cotacoesPendentes = await response.json();
                
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
                    const detailResponse = await fetch(`api/cotacao.php?id=${cotacao.id}`);
                    const cotacaoCompleta = await detailResponse.json();
                    
                    html += renderizarCardCotacao(cotacaoCompleta);
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
            const stats = cotacao.estatisticas || {};
            const responderam = stats.parceiros_responderam || 0;
            const total = stats.total_parceiros || 0;
            
            return `
                <div class="card" style="margin-bottom: 20px;">
                    <div class="cotacao-header">
                        <div>
                            <h3>Cotação #${cotacao.id} - ${cotacao.cliente || 'Cliente'}</h3>
                            <p style="margin: 5px 0; color: #666;">
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
                        ${stats.menor_valor ? `
                        <div class="stat-card green">
                            <div class="stat-value">R$ ${formatarMoeda(stats.menor_valor)}</div>
                            <div class="stat-label">Menor Valor</div>
                        </div>
                        <div class="stat-card yellow">
                            <div class="stat-value">R$ ${formatarMoeda(stats.media_valor)}</div>
                            <div class="stat-label">Valor Médio</div>
                        </div>
                        <div class="stat-card red">
                            <div class="stat-value">R$ ${formatarMoeda(stats.maior_valor)}</div>
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
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner"></div></td></tr>';
            
            try {
                const response = await fetch('api/cotacao.php');
                cotacoes = await response.json();
                
                let html = '';
                cotacoes.forEach(cotacao => {
                    const statusClass = cotacao.status.toLowerCase().replace('_', '');
                    const stats = cotacao.estatisticas || {};
                    
                    html += `
                        <tr>
                            <td>#${cotacao.id}</td>
                            <td>${cotacao.cliente || 'N/A'}</td>
                            <td>${cotacao.endereco || 'N/A'}</td>
                            <td>${formatarData(cotacao.data_criacao)}</td>
                            <td><span class="status-badge status-${statusClass}">${getStatusLabel(cotacao.status)}</span></td>
                            <td>${stats.parceiros_responderam || 0}/${stats.total_parceiros || 0}</td>
                            <td>${cotacao.valor_aprovado ? 'R$ ' + formatarMoeda(cotacao.valor_aprovado) : '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="verDetalhesCotacao(${cotacao.id})">
                                    Detalhes
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html || '<tr><td colspan="8" class="text-center">Nenhuma cotação encontrada</td></tr>';
                
            } catch (error) {
                console.error('Erro ao carregar cotações:', error);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Erro ao carregar cotações</td></tr>';
            }
        }

        // Função para carregar ranking de parceiros
        async function carregarRankingParceiros() {
            const tbody = document.getElementById('rankingParceiros');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner"></div></td></tr>';
            
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
                            <td>${parceiro.total_respostas}</td>
                            <td>${parceiro.cotacoes_ganhas}</td>
                            <td>${taxaSucesso}%</td>
                            <td>R$ ${formatarMoeda(parceiro.valor_medio)}</td>
                            <td>${Math.round(parceiro.tempo_resposta_medio || 0)}h</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html || '<tr><td colspan="7" class="text-center">Nenhum parceiro encontrado</td></tr>';
                
            } catch (error) {
                console.error('Erro ao carregar ranking:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar ranking</td></tr>';
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
                                        `<span style="color: #27ae60;">✓ Respondido em ${formatarDataHora(parceiro.data_resposta)}</span>` : 
                                        '<span style="color: #f39c12;">⏳ Aguardando resposta</span>'
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
                        Você está prestes a aprovar a proposta de <strong>${parceiroNome}</strong>
                        no valor de <strong>R$ ${formatarMoeda(valor)}</strong>.
                    </div>
                    
                    <p>Esta ação não pode ser desfeita. Deseja continuar?</p>
                    
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
                        id: cotacaoId,
                        acao: 'aprovar',
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
                    alert('Erro ao aprovar cotação: ' + result.error);
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
                        id: cotacaoId,
                        acao: 'rejeitar',
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
                    alert('Erro ao rejeitar cotação: ' + result.error);
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
                        id: cotacaoId,
                        acao: 'reenviar',
                        novo_prazo_horas: 24
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    carregarCotacoesPendentes();
                } else {
                    alert('Erro: ' + result.error);
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

        // Carregar dados ao iniciar
        carregarEstatisticas();
        carregarCotacoesPendentes();
    </script>
</body>
</html>