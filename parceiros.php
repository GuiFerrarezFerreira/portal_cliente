<?php
require_once 'session.php';
require_once 'config.php';

// Apenas gestores podem acessar esta página
if(!isGestor()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Parceiros - Sistema de Vistoria</title>
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
            max-width: 1200px;
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

        h1 {
            font-size: 24px;
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
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #27ae60;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn-warning {
            background-color: #f39c12;
        }

        .btn-warning:hover {
            background-color: #e67e22;
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

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            position: absolute;
            right: 10px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .status-ativo {
            color: #27ae60;
            font-weight: bold;
        }

        .status-inativo {
            color: #e74c3c;
            font-weight: bold;
        }

        .token-container {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }

        .copy-btn {
            padding: 4px 8px;
            font-size: 11px;
            margin-left: 10px;
        }

        .info-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            margin-top: 5px;
        }

        .nav-links {
            margin-bottom: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #3498db;
            margin-right: 20px;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <h1>Gerenciamento de Parceiros</h1>
                <div class="user-info">
                    <span>Olá, <?php echo htmlspecialchars($usuario_nome); ?></span>
                    <span class="user-badge"><?php echo ucfirst($usuario_tipo); ?></span>
                    <a href="logout.php" class="btn btn-logout btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="nav-links">
            <a href="index.php">← Voltar ao Sistema Principal</a>
        </div>

        <!-- Estatísticas -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number" id="totalParceiros">0</div>
                <div class="stat-label">Total de Parceiros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="parceirosAtivos">0</div>
                <div class="stat-label">Parceiros Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="cotacoesEnviadas">0</div>
                <div class="stat-label">Cotações Enviadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="taxaResposta">0%</div>
                <div class="stat-label">Taxa de Resposta</div>
            </div>
        </div>

        <div class="card">
            <h2>Parceiros de Cotação</h2>
            
            <div class="info-box">
                <strong>Informação:</strong> Os parceiros cadastrados aqui recebem solicitações de cotação automaticamente quando uma vistoria é enviada para cotação.
            </div>
            
            <button class="btn btn-success" onclick="abrirModalNovo()">Novo Parceiro</button>
            
            <table id="tabelaParceiros">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Cotações</th>
                        <th>Taxa Resposta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="listaParceiros">
                    <!-- Parceiros serão carregados aqui -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Novo/Editar Parceiro -->
    <div id="modalParceiro" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitulo">Novo Parceiro</h2>
            
            <form id="formParceiro">
                <input type="hidden" id="parceiroId" value="">
                
                <div class="form-group">
                    <label for="nome">Nome da Empresa:</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="text" id="telefone" name="telefone" maxlength="15">
                </div>
                
                <div class="form-group">
                    <label for="contato_nome">Nome do Contato:</label>
                    <input type="text" id="contato_nome" name="contato_nome">
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações:</label>
                    <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="ativo" name="ativo" checked>
                        Parceiro Ativo
                    </label>
                </div>
                
                <button type="submit" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-danger" onclick="fecharModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Modal para Ver Token -->
    <div id="modalToken" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalToken()">&times;</span>
            <h2>Token de Acesso do Parceiro</h2>
            
            <div class="info-box">
                <p>Use este link para que o parceiro acesse o sistema de cotações:</p>
            </div>
            
            <div class="form-group">
                <label>Link de Acesso:</label>
                <div class="token-container">
                    <span id="linkAcesso"></span>
                    <button class="btn btn-sm copy-btn" onclick="copiarLink()">Copiar</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Token:</label>
                <div class="token-container">
                    <span id="tokenParceiro"></span>
                    <button class="btn btn-sm copy-btn" onclick="copiarToken()">Copiar</button>
                </div>
            </div>
            
            <button class="btn btn-warning" onclick="regenerarToken()">Regenerar Token</button>
            <button class="btn btn-secondary" onclick="fecharModalToken()">Fechar</button>
        </div>
    </div>

    <script>
        let parceiros = [];
        let parceiroAtualToken = null;

        // Função para carregar parceiros
        async function carregarParceiros() {
            try {
                const response = await fetch('api/parceiros.php');
                parceiros = await response.json();
                exibirParceiros();
                atualizarEstatisticas();
            } catch (error) {
                console.error('Erro ao carregar parceiros:', error);
            }
        }

        // Função para exibir parceiros na tabela
        function exibirParceiros() {
            const tbody = document.getElementById('listaParceiros');
            tbody.innerHTML = '';
            
            parceiros.forEach(parceiro => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${parceiro.id}</td>
                    <td>${parceiro.nome}</td>
                    <td>${parceiro.email}</td>
                    <td>${parceiro.telefone || 'Não informado'}</td>
                    <td class="${parceiro.ativo == 1 ? 'status-ativo' : 'status-inativo'}">
                        ${parceiro.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </td>
                    <td>${parceiro.total_cotacoes || 0}</td>
                    <td>${parceiro.taxa_resposta || '0'}%</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm" onclick="editarParceiro(${parceiro.id})">Editar</button>
                            <button class="btn btn-sm btn-secondary" onclick="verToken(${parceiro.id})">Token</button>
                            ${parceiro.ativo == 1 ? 
                                `<button class="btn btn-sm btn-danger" onclick="desativarParceiro(${parceiro.id})">Desativar</button>` :
                                `<button class="btn btn-sm btn-success" onclick="ativarParceiro(${parceiro.id})">Ativar</button>`
                            }
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Função para atualizar estatísticas
        function atualizarEstatisticas() {
            const totalParceiros = parceiros.length;
            const parceirosAtivos = parceiros.filter(p => p.ativo == 1).length;
            
            let totalCotacoes = 0;
            let totalRespostas = 0;
            
            parceiros.forEach(p => {
                totalCotacoes += parseInt(p.total_cotacoes || 0);
                totalRespostas += parseInt(p.total_respostas || 0);
            });
            
            const taxaResposta = totalCotacoes > 0 ? Math.round((totalRespostas / totalCotacoes) * 100) : 0;
            
            document.getElementById('totalParceiros').textContent = totalParceiros;
            document.getElementById('parceirosAtivos').textContent = parceirosAtivos;
            document.getElementById('cotacoesEnviadas').textContent = totalCotacoes;
            document.getElementById('taxaResposta').textContent = taxaResposta + '%';
        }

        // Função para abrir modal de novo parceiro
        function abrirModalNovo() {
            document.getElementById('modalTitulo').textContent = 'Novo Parceiro';
            document.getElementById('formParceiro').reset();
            document.getElementById('parceiroId').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('modalParceiro').style.display = 'block';
        }

        // Função para fechar modal
        function fecharModal() {
            document.getElementById('modalParceiro').style.display = 'none';
        }

        // Função para editar parceiro
        function editarParceiro(id) {
            const parceiro = parceiros.find(p => p.id == id);
            if (parceiro) {
                document.getElementById('modalTitulo').textContent = 'Editar Parceiro';
                document.getElementById('parceiroId').value = parceiro.id;
                document.getElementById('nome').value = parceiro.nome;
                document.getElementById('email').value = parceiro.email;
                document.getElementById('telefone').value = parceiro.telefone || '';
                document.getElementById('contato_nome').value = parceiro.contato_nome || '';
                document.getElementById('observacoes').value = parceiro.observacoes || '';
                document.getElementById('ativo').checked = parceiro.ativo == 1;
                document.getElementById('modalParceiro').style.display = 'block';
            }
        }

        // Função para desativar parceiro
        async function desativarParceiro(id) {
            if (confirm('Tem certeza que deseja desativar este parceiro?')) {
                try {
                    await fetch(`api/parceiros.php?id=${id}`, { method: 'DELETE' });
                    carregarParceiros();
                } catch (error) {
                    console.error('Erro ao desativar parceiro:', error);
                }
            }
        }

        // Função para ativar parceiro
        async function ativarParceiro(id) {
            const parceiro = parceiros.find(p => p.id == id);
            if (parceiro) {
                parceiro.ativo = 1;
                try {
                    await fetch(`api/parceiros.php?id=${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ativo: 1 })
                    });
                    carregarParceiros();
                } catch (error) {
                    console.error('Erro ao ativar parceiro:', error);
                }
            }
        }

        // Função para ver token
        function verToken(id) {
            const parceiro = parceiros.find(p => p.id == id);
            if (parceiro) {
                parceiroAtualToken = parceiro;
                const baseUrl = window.location.origin + window.location.pathname.replace('parceiros.php', '');
                const linkAcesso = `${baseUrl}parceiro-cotacao.php?token=${parceiro.token_acesso || 'Não gerado'}`;
                
                document.getElementById('linkAcesso').textContent = linkAcesso;
                document.getElementById('tokenParceiro').textContent = parceiro.token_acesso || 'Não gerado';
                document.getElementById('modalToken').style.display = 'block';
            }
        }

        // Função para fechar modal de token
        function fecharModalToken() {
            document.getElementById('modalToken').style.display = 'none';
            parceiroAtualToken = null;
        }

        // Função para copiar link
        function copiarLink() {
            const link = document.getElementById('linkAcesso').textContent;
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copiado para a área de transferência!');
            });
        }

        // Função para copiar token
        function copiarToken() {
            const token = document.getElementById('tokenParceiro').textContent;
            navigator.clipboard.writeText(token).then(() => {
                alert('Token copiado para a área de transferência!');
            });
        }

        // Função para regenerar token
        async function regenerarToken() {
            if (!parceiroAtualToken) return;
            
            if (confirm('Tem certeza que deseja regenerar o token? O token anterior será invalidado.')) {
                try {
                    const response = await fetch(`api/parceiros.php?id=${parceiroAtualToken.id}&action=regenerate_token`, {
                        method: 'PUT'
                    });
                    const result = await response.json();
                    
                    if (result.token) {
                        document.getElementById('tokenParceiro').textContent = result.token;
                        const baseUrl = window.location.origin + window.location.pathname.replace('parceiros.php', '');
                        const linkAcesso = `${baseUrl}parceiro-cotacao.php?token=${result.token}`;
                        document.getElementById('linkAcesso').textContent = linkAcesso;
                        
                        // Atualizar no array local
                        const index = parceiros.findIndex(p => p.id == parceiroAtualToken.id);
                        if (index !== -1) {
                            parceiros[index].token_acesso = result.token;
                        }
                        
                        alert('Token regenerado com sucesso!');
                    }
                } catch (error) {
                    console.error('Erro ao regenerar token:', error);
                    alert('Erro ao regenerar token');
                }
            }
        }

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                e.target.value = value;
            }
        });

        // Submissão do formulário
        document.getElementById('formParceiro').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const parceiroId = document.getElementById('parceiroId').value;
            
            const parceiro = {
                nome: formData.get('nome'),
                email: formData.get('email'),
                telefone: formData.get('telefone'),
                contato_nome: formData.get('contato_nome'),
                observacoes: formData.get('observacoes'),
                ativo: document.getElementById('ativo').checked ? 1 : 0
            };
            
            try {
                if (parceiroId) {
                    // Atualizar parceiro
                    await fetch(`api/parceiros.php?id=${parceiroId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(parceiro)
                    });
                } else {
                    // Criar novo parceiro
                    await fetch('api/parceiros.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(parceiro)
                    });
                }
                fecharModal();
                carregarParceiros();
            } catch (error) {
                console.error('Erro ao salvar parceiro:', error);
                alert('Erro ao salvar parceiro');
            }
        });

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                if (event.target == document.getElementById('modalParceiro')) {
                    fecharModal();
                } else if (event.target == document.getElementById('modalToken')) {
                    fecharModalToken();
                }
            }
        }

        // Carregar dados ao iniciar
        carregarParceiros();
    </script>
</body>
</html>