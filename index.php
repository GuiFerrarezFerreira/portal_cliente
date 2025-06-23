<?php
require_once 'session.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Vistoria</title>
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
            text-align: center;
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

        .btn-info {
            background-color: #9b59b6;
        }

        .btn-info:hover {
            background-color: #8e44ad;
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
        input[type="password"],
        input[type="number"],                
        input[type="datetime-local"],
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

        /* Status badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-pendente {
            background-color: #f39c12;
            color: white;
        }

        .status-concluída {
            background-color: #27ae60;
            color: white;
        }

        .status-enviada_cotacao {
            background-color: #3498db;
            color: white;
        }

        .status-cotacao_aprovada {
            background-color: #9b59b6;
            color: white;
        }

        .status-proposta_enviada {
            background-color: #e67e22;
            color: white;
        }

        .status-proposta_aceita {
            background-color: #16a085;
            color: white;
        }

        .status-em_andamento {
            background-color: #2ecc71;
            color: white;
        }

        .status-finalizada {
            background-color: #34495e;
            color: white;
        }

        .status-cancelada {
            background-color: #e74c3c;
            color: white;
        }

        /* Estilos do Calendário */
        .calendar-container {
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #34495e;
            color: white;
            border-radius: 5px;
        }

        .calendar-nav {
            cursor: pointer;
            padding: 5px 15px;
            background-color: #2c3e50;
            border-radius: 3px;
            transition: background-color 0.3s;
        }

        .calendar-nav:hover {
            background-color: #1a252f;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: bold;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 3px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .calendar-day:hover {
            background-color: #e8f4f8;
            transform: scale(1.05);
        }

        .calendar-day.other-month {
            color: #bdc3c7;
            background-color: #f8f9fa;
        }

        .calendar-day.today {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }

        .calendar-day.has-vistoria {
            background-color: #e8f5e9;
            border: 2px solid #27ae60;
        }

        .calendar-day.has-vistoria.today {
            background-color: #2980b9;
            border: 2px solid #27ae60;
        }

        .vistoria-count {
            position: absolute;
            bottom: 2px;
            right: 5px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .vistorias-dia-list {
            margin-top: 15px;
        }

        .vistoria-item {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
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

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .file-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin: 10px 0;
        }

        .upload-area:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }

        /* Acesso rápido para gestores */
        .quick-access {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
        }

        .quick-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .quick-card h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .quick-card p {
            color: #7f8c8d;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* Status info cards */
        .status-info-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }

        .status-info-card h4 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .status-info-card p {
            margin-bottom: 5px;
            color: #555;
        }

        /* Loading spinner */
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
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <h1>Sistema de Vistoria</h1>
                <div class="user-info">
                    <span>Olá, <?php echo htmlspecialchars($usuario_nome); ?></span>
                    <span class="user-badge"><?php echo ucfirst($usuario_tipo); ?></span>
                    <a href="logout.php" class="btn btn-logout btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if(isVendedor()): ?>
        <div class="alert-info">
            <strong>Atenção:</strong> Você está visualizando apenas suas próprias vistorias.
        </div>
        <?php endif; ?>

        <?php if(isGestor()): ?>
        <!-- Acesso Rápido para Gestores -->
        <div class="quick-access">
            <div class="quick-card">
                <h3>📊 Cotações</h3>
                <p>Gerenciar cotações e aprovar propostas dos parceiros</p>
                <a href="cotacoes.php" class="btn btn-info">Acessar Cotações</a>
            </div>
            <div class="quick-card">
                <h3>🤝 Parceiros</h3>
                <p>Cadastrar e gerenciar parceiros de cotação</p>
                <a href="parceiros.php" class="btn btn-warning">Gerenciar Parceiros</a>
            </div>
            <div class="quick-card">
                <h3>📈 Relatórios</h3>
                <p>Visualizar relatórios e estatísticas do sistema</p>
                <button class="btn btn-secondary" onclick="alert('Em desenvolvimento')">Ver Relatórios</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Abas -->
        <div class="tabs">
            <div class="tab active" onclick="mostrarAba('calendario')">Calendário</div>
            <div class="tab" onclick="mostrarAba('lista')">Lista de Vistorias</div>
            <?php if(isGestor()): ?>
            <div class="tab" onclick="mostrarAba('vendedores')">Vendedores</div>
            <?php endif; ?>
        </div>

        <!-- Conteúdo do Calendário -->
        <div id="calendario" class="tab-content active">
            <div class="card calendar-container">
                <h2>Calendário de Vistorias</h2>
                <div class="calendar-header">
                    <span class="calendar-nav" onclick="mudarMes(-1)">◀ Anterior</span>
                    <h3 id="mesAno"></h3>
                    <span class="calendar-nav" onclick="mudarMes(1)">Próximo ▶</span>
                </div>
                <div class="calendar-grid" id="calendario-grid">
                    <!-- Calendário será gerado aqui -->
                </div>
            </div>
        </div>

        <!-- Conteúdo da Lista -->
        <div id="lista" class="tab-content">
            <div class="card">
                <h2>Gerenciamento de Vistorias</h2>
                <button class="btn btn-success" onclick="abrirModalNova()">Nova Vistoria</button>
                
                <table id="tabelaVistorias">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Imóvel</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="listaVistorias">
                        <!-- Vistorias serão carregadas aqui -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conteúdo de Vendedores (apenas para gestor) -->
        <?php if(isGestor()): ?>
        <div id="vendedores" class="tab-content">
            <div class="card">
                <h2>Gerenciamento de Vendedores</h2>
                <button class="btn btn-success" onclick="abrirModalNovoVendedor()">Novo Vendedor</button>
                
                <table id="tabelaVendedores">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="listaVendedores">
                        <!-- Vendedores serão carregados aqui -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Nova/Editar Vistoria -->
    <div id="modalVistoria" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2 id="modalTitulo">Nova Vistoria</h2>
            
            <form id="formVistoria">
                <input type="hidden" id="vistoriaId" value="">
                
                <div class="form-group">
                    <label for="cliente">Cliente:</label>
                    <input type="text" id="cliente" name="cliente" required>
                </div>
                
                <div class="form-group">
                    <label for="cpf">CPF:</label>
                    <input type="text" id="cpf" name="cpf" maxlength="14" required>
                </div>
                
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="text" id="telefone" name="telefone" maxlength="15" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="vendedor">Vendedor:</label>
                    <?php if(isGestor()): ?>
                    <select id="vendedor" name="vendedor" required>
                        <option value="">Selecione um vendedor...</option>
                        <!-- Vendedores serão carregados aqui -->
                    </select>
                    <?php else: ?>
                    <input type="text" id="vendedor" name="vendedor" value="<?php echo htmlspecialchars($usuario_nome); ?>" readonly>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="endereco">Endereço do Imóvel:</label>
                    <input type="text" id="endereco" name="endereco" required>
                </div>
                
                <div class="form-group">
                    <label for="tipo_imovel">Tipo de Imóvel:</label>
                    <select id="tipo_imovel" name="tipo_imovel" required>
                        <option value="">Selecione...</option>
                        <option value="Casa">Casa</option>
                        <option value="Apartamento">Apartamento</option>
                        <option value="Comercial">Comercial</option>
                        <option value="Terreno">Terreno</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="data_vistoria">Data da Vistoria:</label>
                    <input type="datetime-local" id="data_vistoria" name="data_vistoria" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="Pendente">Pendente</option>
                        <option value="Concluída">Concluída</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações:</label>
                    <textarea id="observacoes" name="observacoes" rows="4"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-danger" onclick="fecharModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Modal para Upload de Lista de Seguro -->
    <div id="modalUpload" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalUpload()">&times;</span>
            <h2>Upload de Lista de Seguro</h2>
            
            <div id="uploadInfo"></div>
            
            <div class="upload-area" id="uploadArea">
                <p>Arraste o arquivo aqui ou clique para selecionar</p>
                <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display: none;">
            </div>
            
            <div id="filePreview" style="display: none;">
                <div class="file-info">
                    <span id="fileName"></span>
                    <button class="btn btn-sm btn-danger" onclick="removerArquivo()">Remover</button>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn btn-success" onclick="fazerUpload()">Enviar Arquivo</button>
                <button class="btn btn-secondary" onclick="fecharModalUpload()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal para Cotação -->
    <div id="modalCotacao" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalCotacao()">&times;</span>
            <h2>Status da Cotação</h2>
            
            <div id="cotacaoContent">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- Modal para Proposta -->
    <div id="modalProposta" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalProposta()">&times;</span>
            <h2>Criar Proposta</h2>
            
            <form id="formProposta">
                <input type="hidden" id="propostaVistoriaId" value="">
                
                <div class="form-group">
                    <label for="valorTotal">Valor Total (R$):</label>
                    <input type="number" id="valorTotal" name="valor_total" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="descricaoServicos">Descrição dos Serviços:</label>
                    <textarea id="descricaoServicos" name="descricao_servicos" rows="6" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="validadeDias">Validade da Proposta (dias):</label>
                    <input type="number" id="validadeDias" name="validade_dias" value="30" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enviarEmail" name="enviar_email" checked>
                        Enviar proposta por email ao cliente
                    </label>
                </div>
                
                <button type="submit" class="btn btn-success">Criar e Enviar Proposta</button>
                <button type="button" class="btn btn-secondary" onclick="fecharModalProposta()">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Modal para Vistorias do Dia -->
    <div id="modalVistoriasDia" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalDia()">&times;</span>
            <h2 id="modalDiaTitulo">Vistorias do Dia</h2>
            <div id="vistoriasDiaContent">
                <!-- Conteúdo será inserido aqui -->
            </div>
        </div>
    </div>

    <!-- Modal para Vendedor (apenas gestor) -->
    <?php if(isGestor()): ?>
    <div id="modalVendedor" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalVendedor()">&times;</span>
            <h2 id="modalTituloVendedor">Novo Vendedor</h2>
            
            <form id="formVendedor">
                <input type="hidden" id="vendedorId" value="">
                
                <div class="form-group">
                    <label for="vendedor_nome">Nome:</label>
                    <input type="text" id="vendedor_nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="vendedor_email">Email:</label>
                    <input type="email" id="vendedor_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="vendedor_telefone">Telefone:</label>
                    <input type="text" id="vendedor_telefone" name="telefone" maxlength="15" required>
                </div>
                
                <div class="form-group">
                    <label for="vendedor_senha">Senha:</label>
                    <input type="password" id="vendedor_senha" name="senha" placeholder="Deixe em branco para manter a atual">
                </div>
                
                <div class="form-group">
                    <label for="vendedor_ativo">
                        <input type="checkbox" id="vendedor_ativo" name="ativo" checked>
                        Ativo
                    </label>
                </div>
                
                <button type="submit" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-danger" onclick="fecharModalVendedor()">Cancelar</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Variáveis globais
        let vistorias = [];
        let vendedores = [];
        let mesAtual = new Date().getMonth();
        let anoAtual = new Date().getFullYear();
        const usuarioTipo = '<?php echo $usuario_tipo; ?>';
        const usuarioNome = '<?php echo $usuario_nome; ?>';
        const isGestor = <?php echo isGestor() ? 'true' : 'false'; ?>;
        let arquivoSelecionado = null;
        let vistoriaAtualUpload = null;

        const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                       'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        // Mapeamento de status
        const statusDisplay = {
            'Pendente': 'Pendente',
            'Concluída': 'Concluída',
            'Enviada_Cotacao': 'Enviada para Cotação',
            'Cotacao_Aprovada': 'Cotação Aprovada',
            'Proposta_Enviada': 'Proposta Enviada',
            'Proposta_Aceita': 'Proposta Aceita',
            'Em_Andamento': 'Em Andamento',
            'Finalizada': 'Finalizada',
            'Cancelada': 'Cancelada'
        };

        // Função para normalizar datas
        function normalizarData(data) {
            if (!data) return null;
            
            // Se for uma string datetime-local ou com hora
            if (typeof data === 'string' && data.includes('T')) {
                return data.split('T')[0];
            }
            
            // Se já for uma string no formato yyyy-mm-dd
            if (typeof data === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data)) {
                return data;
            }
            
            // Para outros casos, tenta converter
            const dataObj = new Date(data);
            if (!isNaN(dataObj)) {
                const ano = dataObj.getFullYear();
                const mes = String(dataObj.getMonth() + 1).padStart(2, '0');
                const dia = String(dataObj.getDate()).padStart(2, '0');
                return `${ano}-${mes}-${dia}`;
            }
            
            return data;
        }

        // Função para mostrar data com horas
        function dataHoras(data) {
            const [dvist, horario] = data.split(' ');
            const [ano, mes, dia] = dvist.split('-');
            const [h1, h2, h3] = horario.split(':');            
            return `${dia}/${mes}/${ano} às ${h1}:${h2}`;
        }

        // Função para visualizar relatório
        function visualizarRelatorio(id) {
            window.open(`relatorio.php?id=${id}`, '_blank');
        }

        // Função para carregar vendedores
        async function carregarVendedores() {
            try {
                const response = await fetch('api/vendedores.php');
                vendedores = await response.json();
                
                if(isGestor) {
                    const selectVendedor = document.getElementById('vendedor');
                    if(selectVendedor && selectVendedor.tagName === 'SELECT') {
                        selectVendedor.innerHTML = '<option value="">Selecione um vendedor...</option>';
                        
                        vendedores.filter(v => v.ativo == 1).forEach(vendedor => {
                            const option = document.createElement('option');
                            option.value = vendedor.nome;
                            option.textContent = vendedor.nome;
                            selectVendedor.appendChild(option);
                        });
                    }
                    
                    // Carregar vendedores na tabela
                    exibirVendedores();
                }
            } catch (error) {
                console.error('Erro ao carregar vendedores:', error);
            }
        }

        // Função para exibir vendedores na tabela (apenas gestor)
        function exibirVendedores() {
            if(!isGestor) return;
            
            const tbody = document.getElementById('listaVendedores');
            if(!tbody) return;
            
            tbody.innerHTML = '';
            
            vendedores.forEach(vendedor => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${vendedor.id}</td>
                    <td>${vendedor.nome}</td>
                    <td>${vendedor.email}</td>
                    <td>${vendedor.telefone || 'Não informado'}</td>
                    <td>${vendedor.ativo == 1 ? '<span style="color: green;">Ativo</span>' : '<span style="color: red;">Inativo</span>'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm" onclick="editarVendedor(${vendedor.id})">Editar</button>
                            ${vendedor.ativo == 1 ? 
                                `<button class="btn btn-sm btn-danger" onclick="desativarVendedor(${vendedor.id})">Desativar</button>` :
                                `<button class="btn btn-sm btn-success" onclick="ativarVendedor(${vendedor.id})">Ativar</button>`
                            }
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Funções para vendedores (apenas gestor)
        function abrirModalNovoVendedor() {
            if(!isGestor) return;
            
            document.getElementById('modalTituloVendedor').textContent = 'Novo Vendedor';
            document.getElementById('formVendedor').reset();
            document.getElementById('vendedorId').value = '';
            document.getElementById('vendedor_ativo').checked = true;
            document.getElementById('modalVendedor').style.display = 'block';
        }

        function fecharModalVendedor() {
            document.getElementById('modalVendedor').style.display = 'none';
        }

        function editarVendedor(id) {
            if(!isGestor) return;
            
            const vendedor = vendedores.find(v => v.id == id);
            if(vendedor) {
                document.getElementById('modalTituloVendedor').textContent = 'Editar Vendedor';
                document.getElementById('vendedorId').value = vendedor.id;
                document.getElementById('vendedor_nome').value = vendedor.nome;
                document.getElementById('vendedor_email').value = vendedor.email;
                document.getElementById('vendedor_telefone').value = vendedor.telefone || '';
                document.getElementById('vendedor_senha').value = '';
                document.getElementById('vendedor_ativo').checked = vendedor.ativo == 1;
                document.getElementById('modalVendedor').style.display = 'block';
            }
        }

        async function desativarVendedor(id) {
            if(!isGestor) return;
            
            if(confirm('Tem certeza que deseja desativar este vendedor?')) {
                try {
                    await fetch(`api/vendedores.php?id=${id}`, { method: 'DELETE' });
                    carregarVendedores();
                } catch (error) {
                    console.error('Erro ao desativar vendedor:', error);
                }
            }
        }

        async function ativarVendedor(id) {
            if(!isGestor) return;
            
            const vendedor = vendedores.find(v => v.id == id);
            if(vendedor) {
                vendedor.ativo = 1;
                try {
                    await fetch(`api/vendedores.php?id=${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(vendedor)
                    });
                    carregarVendedores();
                } catch (error) {
                    console.error('Erro ao ativar vendedor:', error);
                }
            }
        }

        // Submissão do formulário de vendedor
        if(isGestor && document.getElementById('formVendedor')) {
            document.getElementById('formVendedor').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const vendedorId = document.getElementById('vendedorId').value;
                
                const vendedor = {
                    nome: formData.get('nome'),
                    email: formData.get('email'),
                    telefone: formData.get('telefone'),
                    senha: formData.get('senha'),
                    ativo: document.getElementById('vendedor_ativo').checked ? 1 : 0
                };
                
                try {
                    if(vendedorId) {
                        // Atualizar vendedor
                        await fetch(`api/vendedores.php?id=${vendedorId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(vendedor)
                        });
                    } else {
                        // Criar novo vendedor
                        await fetch('api/vendedores.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(vendedor)
                        });
                    }
                    fecharModalVendedor();
                    carregarVendedores();
                } catch (error) {
                    console.error('Erro ao salvar vendedor:', error);
                }
            });
        }

        // Função para mostrar aba
        function mostrarAba(aba) {
            // Remove active de todas as abas
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Adiciona active na aba selecionada
            if (aba === 'calendario') {
                document.querySelector('.tab:nth-child(1)').classList.add('active');
                document.getElementById('calendario').classList.add('active');
                renderizarCalendario();
            } else if (aba === 'lista') {
                document.querySelector('.tab:nth-child(2)').classList.add('active');
                document.getElementById('lista').classList.add('active');
            } else if (aba === 'vendedores' && isGestor) {
                document.querySelector('.tab:nth-child(3)').classList.add('active');
                document.getElementById('vendedores').classList.add('active');
            }
        }

        // Função para renderizar o calendário
        function renderizarCalendario() {
            const primeiroDia = new Date(anoAtual, mesAtual, 1);
            const ultimoDia = new Date(anoAtual, mesAtual + 1, 0);
            const diasNoMes = ultimoDia.getDate();
            const primeiroDiaSemana = primeiroDia.getDay();
            
            // Atualizar cabeçalho
            document.getElementById('mesAno').textContent = `${meses[mesAtual]} ${anoAtual}`;
            
            // Limpar grid
            const grid = document.getElementById('calendario-grid');
            grid.innerHTML = '';
            
            // Adicionar cabeçalhos dos dias
            diasSemana.forEach(dia => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = dia;
                grid.appendChild(header);
            });
            
            // Dias do mês anterior
            const diasMesAnterior = new Date(anoAtual, mesAtual, 0).getDate();
            for (let i = primeiroDiaSemana - 1; i >= 0; i--) {
                const dia = document.createElement('div');
                dia.className = 'calendar-day other-month';
                dia.textContent = diasMesAnterior - i;
                grid.appendChild(dia);
            }
            
            // Dias do mês atual
            const hoje = new Date();
            for (let dia = 1; dia <= diasNoMes; dia++) {
                const diaElement = document.createElement('div');
                diaElement.className = 'calendar-day';
                diaElement.textContent = dia;
                
                // Verificar se é hoje
                if (anoAtual === hoje.getFullYear() && 
                    mesAtual === hoje.getMonth() && 
                    dia === hoje.getDate()) {
                    diaElement.classList.add('today');
                }
                
                // Verificar vistorias neste dia
                const dataStr = `${anoAtual}-${String(mesAtual + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                const vistoriasDia = vistorias.filter(v => normalizarData(v.data_vistoria) === dataStr);
                
                if (vistoriasDia.length > 0) {
                    diaElement.classList.add('has-vistoria');
                    const count = document.createElement('span');
                    count.className = 'vistoria-count';
                    count.textContent = vistoriasDia.length;
                    diaElement.appendChild(count);
                }
                
                // Adicionar evento de clique
                diaElement.addEventListener('click', () => mostrarVistoriasDia(dia, mesAtual, anoAtual));
                
                grid.appendChild(diaElement);
            }
            
            // Dias do próximo mês
            const diasRestantes = 42 - (primeiroDiaSemana + diasNoMes);
            for (let dia = 1; dia <= diasRestantes; dia++) {
                const diaElement = document.createElement('div');
                diaElement.className = 'calendar-day other-month';
                diaElement.textContent = dia;
                grid.appendChild(diaElement);
            }
        }

        // Função para mudar mês
        function mudarMes(direcao) {
            mesAtual += direcao;
            if (mesAtual > 11) {
                mesAtual = 0;
                anoAtual++;
            } else if (mesAtual < 0) {
                mesAtual = 11;
                anoAtual--;
            }
            renderizarCalendario();
        }

        // Função para mostrar vistorias do dia
        function mostrarVistoriasDia(dia, mes, ano) {
            const dataStr = `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
            const vistoriasDia = vistorias.filter(v => normalizarData(v.data_vistoria) === dataStr);
            
            const modal = document.getElementById('modalVistoriasDia');
            const titulo = document.getElementById('modalDiaTitulo');
            const content = document.getElementById('vistoriasDiaContent');
            
            titulo.textContent = `Vistorias - ${dia} de ${meses[mes]} de ${ano}`;
            
            if (vistoriasDia.length === 0) {
                content.innerHTML = '<p>Nenhuma vistoria agendada para este dia.</p>';
            } else {
                content.innerHTML = '<div class="vistorias-dia-list">';
                vistoriasDia.forEach(vistoria => {
                    const podeEditar = isGestor || (vistoria.vendedor === usuarioNome);
                    const statusClass = vistoria.status.toLowerCase().replace('_', '');
                    content.innerHTML += `
                        <div class="vistoria-item">
                            <h4>${vistoria.cliente}</h4>
                            <p><strong>Vendedor:</strong> ${vistoria.vendedor || 'Não informado'}</p>
                            <p><strong>Endereço:</strong> ${vistoria.endereco}</p>
                            <p><strong>Data:</strong> ${vistoria.data_vistoria2}</p>                        
                            <p><strong>Tipo:</strong> ${vistoria.tipo_imovel}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${statusClass}">${statusDisplay[vistoria.status] || vistoria.status}</span></p>
                            ${vistoria.observacoes ? `<p><strong>Observações:</strong> ${vistoria.observacoes}</p>` : ''}
                            <div class="action-buttons">
                                ${podeEditar ? `<button class="btn btn-sm" onclick="editarVistoria('${vistoria.id}')">Editar</button>` : ''}
                                <button class="btn btn-sm btn-info" onclick="visualizarRelatorio(${vistoria.id})">Relatório</button>
                                ${renderizarBotoesAcao(vistoria)}
                            </div>
                        </div>
                    `;
                });
                content.innerHTML += '</div>';
            }
            
            content.innerHTML += '<button class="btn btn-success" onclick="abrirModalNovaData(\'' + dataStr + '\')">Nova Vistoria neste Dia</button>';
            
            modal.style.display = 'block';
        }

        // Função para fechar modal de vistorias do dia
        function fecharModalDia() {
            document.getElementById('modalVistoriasDia').style.display = 'none';
        }

        // Função para abrir modal com data pré-selecionada
        function abrirModalNovaData(data) {
            fecharModalDia();
            abrirModalNova();
            document.getElementById('data_vistoria').value = data;
        }

        // Função para carregar vistorias
        async function carregarVistorias() {
            try {
                const response = await fetch('api/vistorias.php');
                const vistoriasRaw = await response.json();
                
                // Normalizar as datas ao carregar
                vistorias = vistoriasRaw.map(vistoria => ({
                    ...vistoria,
                    data_vistoria: normalizarData(vistoria.data_vistoria),
                    data_vistoria2: dataHoras(vistoria.data_vistoria),                    
                }));
                
                exibirVistorias();
                renderizarCalendario();
            } catch (error) {
                console.error('Erro ao carregar vistorias:', error);
            }
        }

        // Função para renderizar botões de ação baseado no status
        function renderizarBotoesAcao(vistoria) {
            let botoes = '';
            const podeEditar = isGestor || (vistoria.vendedor === usuarioNome);
            
            switch(vistoria.status) {
                case 'Concluída':
                    if (podeEditar) {
                        if (vistoria.arquivo_lista_seguro) {
                            botoes += `<button class="btn btn-sm btn-secondary" onclick="abrirModalUpload(${vistoria.id})">Ver Arquivo</button>`;
                            if (isGestor) {
                                botoes += `<button class="btn btn-sm btn-warning" onclick="enviarParaCotacao(${vistoria.id})">Enviar para Cotação</button>`;
                            }
                        } else {
                            botoes += `<button class="btn btn-sm btn-warning" onclick="abrirModalUpload(${vistoria.id})">Anexar Lista Seguro</button>`;
                        }
                    }
                    break;
                    
                case 'Enviada_Cotacao':
                    if (isGestor) {
                        botoes += `<button class="btn btn-sm btn-info" onclick="verStatusCotacao(${vistoria.id})">Ver Cotação</button>`;
                    }
                    break;
                    
                case 'Cotacao_Aprovada':
                    if (podeEditar) {
                        botoes += `<button class="btn btn-sm btn-success" onclick="criarProposta(${vistoria.id})">Criar Proposta</button>`;
                    }
                    break;
                    
                case 'Proposta_Enviada':
                    botoes += `<button class="btn btn-sm btn-info" onclick="verProposta(${vistoria.id})">Ver Proposta</button>`;
                    break;
                    
                case 'Proposta_Aceita':
                case 'Em_Andamento':
                case 'Finalizada':
                    botoes += `<button class="btn btn-sm btn-secondary" onclick="verDetalhes(${vistoria.id})">Ver Detalhes</button>`;
                    break;
            }
            
            if (isGestor) {
                botoes += `<button class="btn btn-sm btn-danger" onclick="excluirVistoria(${vistoria.id})">Excluir</button>`;
            }
            
            return botoes;
        }

        // Função para exibir vistorias na tabela
        function exibirVistorias() {
            const tbody = document.getElementById('listaVistorias');
            tbody.innerHTML = '';
            
            vistorias.forEach(vistoria => {
                const statusClass = vistoria.status.toLowerCase().replace('_', '');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${vistoria.id}</td>
                    <td>${vistoria.cliente}</td>
                    <td>${vistoria.vendedor || 'Não informado'}</td>
                    <td>${vistoria.endereco}</td>
                    <td>${vistoria.data_vistoria2}</td>
                    <td><span class="status-badge status-${statusClass}">${statusDisplay[vistoria.status] || vistoria.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            ${renderizarBotoesAcao(vistoria)}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Função para abrir modal de nova vistoria
        function abrirModalNova() {
            document.getElementById('modalTitulo').textContent = 'Nova Vistoria';
            document.getElementById('formVistoria').reset();
            document.getElementById('vistoriaId').value = '';
            
            // Se for vendedor, preencher o campo vendedor com seu nome
            if(!isGestor) {
                document.getElementById('vendedor').value = usuarioNome;
            }
            
            document.getElementById('modalVistoria').style.display = 'block';
            
            // Carregar vendedores se for gestor
            if(isGestor) {
                carregarVendedores();
            }
        }

        // Função para fechar modal
        function fecharModal() {
            document.getElementById('modalVistoria').style.display = 'none';
        }

        // Função para editar vistoria
        function editarVistoria(id) {
            fecharModalDia(); // Fechar modal do dia se estiver aberto
            const vistoria = vistorias.find(v => v.id == id);
            if (vistoria) {
                // Verificar permissão
                if(!isGestor && vistoria.vendedor !== usuarioNome) {
                    alert('Você não tem permissão para editar esta vistoria.');
                    return;
                }
                
                // Carregar vendedores primeiro se for gestor
                const preencherFormulario = () => {
                    document.getElementById('modalTitulo').textContent = 'Editar Vistoria';
                    document.getElementById('vistoriaId').value = vistoria.id;
                    document.getElementById('cliente').value = vistoria.cliente;
                    document.getElementById('cpf').value = vistoria.cpf;
                    document.getElementById('telefone').value = vistoria.telefone;
                    document.getElementById('email').value = vistoria.email || '';
                    document.getElementById('vendedor').value = vistoria.vendedor || '';
                    document.getElementById('endereco').value = vistoria.endereco;
                    document.getElementById('tipo_imovel').value = vistoria.tipo_imovel;
                    
                    // Converter data para formato datetime-local
                    const dataOriginal = vistoria.data_vistoria;
                    if (dataOriginal) {
                        // Se não tiver hora, adicionar 00:00
                        if (!dataOriginal.includes('T') && !dataOriginal.includes(' ')) {
                            document.getElementById('data_vistoria').value = dataOriginal + 'T00:00';
                        } else {
                            document.getElementById('data_vistoria').value = dataOriginal.replace(' ', 'T');
                        }
                    }
                    
                    // Limitar opções de status baseado no status atual
                    const selectStatus = document.getElementById('status');
                    selectStatus.innerHTML = '';
                    
                    if (['Pendente', 'Concluída', 'Cancelada'].includes(vistoria.status)) {
                        selectStatus.innerHTML = `
                            <option value="Pendente" ${vistoria.status === 'Pendente' ? 'selected' : ''}>Pendente</option>
                            <option value="Concluída" ${vistoria.status === 'Concluída' ? 'selected' : ''}>Concluída</option>
                            <option value="Cancelada" ${vistoria.status === 'Cancelada' ? 'selected' : ''}>Cancelada</option>
                        `;
                    } else {
                        // Status avançados não podem ser alterados manualmente
                        selectStatus.innerHTML = `<option value="${vistoria.status}" selected>${statusDisplay[vistoria.status] || vistoria.status}</option>`;
                        selectStatus.disabled = true;
                    }
                    
                    document.getElementById('observacoes').value = vistoria.observacoes || '';
                    document.getElementById('modalVistoria').style.display = 'block';
                };
                
                if(isGestor) {
                    carregarVendedores().then(preencherFormulario);
                } else {
                    preencherFormulario();
                }
            }
        }

        // Função para excluir vistoria
        async function excluirVistoria(id) {
            if(!isGestor) {
                alert('Você não tem permissão para excluir vistorias.');
                return;
            }
            
            if (confirm('Tem certeza que deseja excluir esta vistoria?')) {
                try {
                    await fetch(`api/vistorias.php?id=${id}`, { method: 'DELETE' });
                    carregarVistorias();
                } catch (error) {
                    console.error('Erro ao excluir vistoria:', error);
                }
            }
        }

        // Funções de Upload
        function abrirModalUpload(vistoriaId) {
            vistoriaAtualUpload = vistoriaId;
            const vistoria = vistorias.find(v => v.id == vistoriaId);
            
            if (!vistoria) return;
            
            const uploadInfo = document.getElementById('uploadInfo');
            uploadInfo.innerHTML = `
                <div class="alert-info">
                    <strong>Vistoria:</strong> #${vistoria.id} - ${vistoria.cliente}<br>
                    <strong>Status:</strong> ${statusDisplay[vistoria.status]}
                </div>
            `;
            
            // Verificar se já tem arquivo
            if (vistoria.arquivo_lista_seguro) {
                document.getElementById('uploadArea').style.display = 'none';
                document.getElementById('filePreview').style.display = 'block';
                document.getElementById('fileName').textContent = vistoria.arquivo_lista_seguro;
            } else {
                document.getElementById('uploadArea').style.display = 'block';
                document.getElementById('filePreview').style.display = 'none';
            }
            
            document.getElementById('modalUpload').style.display = 'block';
        }
        
        function fecharModalUpload() {
            document.getElementById('modalUpload').style.display = 'none';
            vistoriaAtualUpload = null;
            arquivoSelecionado = null;
            document.getElementById('fileInput').value = '';
        }
        
        // Setup do upload
        document.getElementById('uploadArea').addEventListener('click', () => {
            document.getElementById('fileInput').click();
        });
        
        document.getElementById('uploadArea').addEventListener('dragover', (e) => {
            e.preventDefault();
            e.currentTarget.style.backgroundColor = '#f0f0f0';
        });
        
        document.getElementById('uploadArea').addEventListener('dragleave', (e) => {
            e.currentTarget.style.backgroundColor = '';
        });
        
        document.getElementById('uploadArea').addEventListener('drop', (e) => {
            e.preventDefault();
            e.currentTarget.style.backgroundColor = '';
            
            if (e.dataTransfer.files.length > 0) {
                arquivoSelecionado = e.dataTransfer.files[0];
                mostrarPreviewArquivo();
            }
        });
        
        document.getElementById('fileInput').addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                arquivoSelecionado = e.target.files[0];
                mostrarPreviewArquivo();
            }
        });
        
        function mostrarPreviewArquivo() {
            if (!arquivoSelecionado) return;
            
            document.getElementById('uploadArea').style.display = 'none';
            document.getElementById('filePreview').style.display = 'block';
            document.getElementById('fileName').textContent = arquivoSelecionado.name;
        }
        
        async function fazerUpload() {
            if (!arquivoSelecionado || !vistoriaAtualUpload) {
                alert('Selecione um arquivo primeiro');
                return;
            }
            
            const formData = new FormData();
            formData.append('arquivo', arquivoSelecionado);
            formData.append('vistoria_id', vistoriaAtualUpload);
            
            try {
                const response = await fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Arquivo enviado com sucesso!');
                    fecharModalUpload();
                    carregarVistorias();
                } else {
                    alert('Erro ao enviar arquivo: ' + result.error);
                }
            } catch (error) {
                console.error('Erro ao fazer upload:', error);
                alert('Erro ao enviar arquivo');
            }
        }
        
        async function removerArquivo() {
            if (!vistoriaAtualUpload) return;
            
            if (confirm('Tem certeza que deseja remover este arquivo?')) {
                try {
                    const response = await fetch('api/upload.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ vistoria_id: vistoriaAtualUpload })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Arquivo removido com sucesso!');
                        fecharModalUpload();
                        carregarVistorias();
                    } else {
                        alert('Erro ao remover arquivo');
                    }
                } catch (error) {
                    console.error('Erro ao remover arquivo:', error);
                }
            }
        }

        // Funções de Cotação
        async function enviarParaCotacao(vistoriaId) {
            if (confirm('Enviar esta vistoria para cotação?')) {
                try {
                    const response = await fetch('api/cotacao.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ vistoria_id: vistoriaId })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Vistoria enviada para cotação com sucesso!');
                        carregarVistorias();
                    } else {
                        alert('Erro: ' + result.error);
                    }
                } catch (error) {
                    console.error('Erro ao enviar para cotação:', error);
                }
            }
        }
        
        async function verStatusCotacao(vistoriaId) {
            document.getElementById('modalCotacao').style.display = 'block';
            const content = document.getElementById('cotacaoContent');
            content.innerHTML = '<div class="spinner"></div>';
            
            try {
                const response = await fetch(`api/cotacao.php?vistoria_id=${vistoriaId}`);
                const cotacao = await response.json();
                
                if (cotacao) {
                    let html = '<div class="status-info-card">';
                    html += `<h4>Status: ${cotacao.status}</h4>`;
                    
                    if (cotacao.valor_aprovado) {
                        html += `<p><strong>Valor Aprovado:</strong> R$ ${parseFloat(cotacao.valor_aprovado).toFixed(2).replace('.', ',')}</p>`;
                    }
                    
                    if (cotacao.parceiros && cotacao.parceiros.length > 0) {
                        html += '<h4>Cotações Recebidas:</h4>';
                        html += '<table style="width: 100%; margin-top: 10px;">';
                        html += '<tr><th>Parceiro</th><th>Valor</th><th>Prazo</th></tr>';
                        
                        cotacao.parceiros.forEach(p => {
                            html += '<tr>';
                            html += `<td>${p.parceiro_nome}</td>`;
                            html += `<td>R$ ${p.valor ? parseFloat(p.valor).toFixed(2).replace('.', ',') : 'Aguardando'}</td>`;
                            html += `<td>${p.prazo_dias ? p.prazo_dias + ' dias' : '-'}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</table>';
                    } else {
                        html += '<p>Aguardando respostas dos parceiros...</p>';
                    }
                    
                    html += '</div>';
                    
                    if (isGestor) {
                        html += '<div style="margin-top: 20px; text-align: center;">';
                        html += '<a href="cotacoes.php" class="btn btn-info">Gerenciar Cotações</a>';
                        html += '</div>';
                    }
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p>Nenhuma cotação encontrada para esta vistoria.</p>';
                }
            } catch (error) {
                console.error('Erro ao buscar cotação:', error);
                content.innerHTML = '<p>Erro ao carregar informações da cotação.</p>';
            }
        }
        
        function fecharModalCotacao() {
            document.getElementById('modalCotacao').style.display = 'none';
        }

        // Funções de Proposta
        function criarProposta(vistoriaId) {
            const vistoria = vistorias.find(v => v.id == vistoriaId);
            if (!vistoria) return;
            
            document.getElementById('propostaVistoriaId').value = vistoriaId;
            
            // Preencher valor com o valor aprovado da cotação
            if (vistoria.valor_aprovado) {
                document.getElementById('valorTotal').value = vistoria.valor_aprovado;
            }
            
            // Preencher descrição padrão
            document.getElementById('descricaoServicos').value = `Serviço de mudança residencial conforme vistoria realizada.
            
Inclui:
- Embalagem profissional de todos os itens
- Desmontagem e montagem de móveis
- Transporte seguro com caminhão apropriado
- Seguro durante o transporte
- Equipe especializada

Endereço: ${vistoria.endereco}
Tipo de imóvel: ${vistoria.tipo_imovel}`;
            
            document.getElementById('modalProposta').style.display = 'block';
        }
        
        function fecharModalProposta() {
            document.getElementById('modalProposta').style.display = 'none';
            document.getElementById('formProposta').reset();
        }
        
        document.getElementById('formProposta').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                vistoria_id: document.getElementById('propostaVistoriaId').value,
                valor_total: formData.get('valor_total'),
                descricao_servicos: formData.get('descricao_servicos'),
                validade_dias: formData.get('validade_dias'),
                enviar_email: document.getElementById('enviarEmail').checked
            };
            
            try {
                const response = await fetch('api/propostas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Proposta criada e enviada com sucesso!');
                    fecharModalProposta();
                    carregarVistorias();
                } else {
                    alert('Erro ao criar proposta: ' + result.error);
                }
            } catch (error) {
                console.error('Erro ao criar proposta:', error);
                alert('Erro ao criar proposta');
            }
        });
        
        async function verProposta(vistoriaId) {
            try {
                const response = await fetch(`api/propostas.php?vistoria_id=${vistoriaId}`);
                const propostas = await response.json();
                
                if (propostas && propostas.length > 0) {
                    const proposta = propostas[0]; // Pegar a mais recente
                    let mensagem = `Proposta #${proposta.id}\n\n`;
                    mensagem += `Valor: R$ ${parseFloat(proposta.valor_total).toFixed(2).replace('.', ',')}\n`;
                    mensagem += `Status: ${proposta.status}\n`;
                    mensagem += `Validade: ${proposta.validade_dias} dias\n`;
                    
                    if (proposta.data_envio) {
                        mensagem += `Enviada em: ${new Date(proposta.data_envio).toLocaleDateString('pt-BR')}\n`;
                    }
                    
                    if (proposta.data_aceite) {
                        mensagem += `Aceita em: ${new Date(proposta.data_aceite).toLocaleDateString('pt-BR')}\n`;
                    }
                    
                    alert(mensagem);
                } else {
                    alert('Proposta não encontrada');
                }
            } catch (error) {
                console.error('Erro ao buscar proposta:', error);
            }
        }
        
        function verDetalhes(vistoriaId) {
            // Por enquanto, apenas mostrar o relatório
            visualizarRelatorio(vistoriaId);
        }

        // Máscaras
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                e.target.value = value;
            }
        });

        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                e.target.value = value;
            }
        });

        // Máscara para telefone do vendedor
        if(isGestor && document.getElementById('vendedor_telefone')) {
            document.getElementById('vendedor_telefone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    e.target.value = value;
                }
            });
        }

        // Submissão do formulário
        document.getElementById('formVistoria').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const vistoriaId = document.getElementById('vistoriaId').value;
            
            // Re-habilitar status se estava desabilitado
            const selectStatus = document.getElementById('status');
            selectStatus.disabled = false;
            
            const vistoria = {
                cliente: formData.get('cliente'),
                cpf: formData.get('cpf'),
                telefone: formData.get('telefone'),
                email: formData.get('email'),
                vendedor: formData.get('vendedor'),
                endereco: formData.get('endereco'),
                tipo_imovel: formData.get('tipo_imovel'),
                data_vistoria: formData.get('data_vistoria'),
                status: formData.get('status'),
                observacoes: formData.get('observacoes')
            };
            
            try {
                if (vistoriaId) {
                    // Atualizar vistoria existente
                    await fetch(`api/vistorias.php?id=${vistoriaId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(vistoria)
                    });
                } else {
                    // Criar nova vistoria
                    await fetch('api/vistorias.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(vistoria)
                    });
                }
                fecharModal();
                carregarVistorias();
            } catch (error) {
                console.error('Erro ao salvar vistoria:', error);
            }
        });

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                if (event.target == document.getElementById('modalVistoria')) {
                    fecharModal();
                } else if (event.target == document.getElementById('modalVistoriasDia')) {
                    fecharModalDia();
                } else if (event.target == document.getElementById('modalUpload')) {
                    fecharModalUpload();
                } else if (event.target == document.getElementById('modalCotacao')) {
                    fecharModalCotacao();
                } else if (event.target == document.getElementById('modalProposta')) {
                    fecharModalProposta();
                } else if (isGestor && event.target == document.getElementById('modalVendedor')) {
                    fecharModalVendedor();
                }
            }
        }

        // Carregar dados ao iniciar
        carregarVistorias();
        carregarVendedores();
    </script>
</body>
</html>