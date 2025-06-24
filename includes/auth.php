<?php
// includes/auth.php - Funções de autenticação e controle de acesso

function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

function verificarTipo($tiposPermitidos) {
    verificarLogin();
    
    if (!is_array($tiposPermitidos)) {
        $tiposPermitidos = [$tiposPermitidos];
    }
    
    if (!in_array($_SESSION['usuario_tipo'], $tiposPermitidos)) {
        header('Location: erro-acesso.php');
        exit;
    }
}

function isGestor() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'gestor';
}

function isVendedor() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'vendedor';
}

function isCotador() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'cotador';
}

function isCoordenador() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'coordenador';
}

function isCliente() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'cliente';
}

function podeAcessarVistoria($vistoriaId, $pdo) {
    if (isGestor()) {
        return true;
    }
    
    if (isVendedor()) {
        $stmt = $pdo->prepare("SELECT vendedor FROM vistorias WHERE id = ?");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        return $vistoria && $vistoria['vendedor'] === $_SESSION['usuario_nome'];
    }
    
    if (isCliente()) {
        $stmt = $pdo->prepare("
            SELECT v.id 
            FROM vistorias v
            JOIN clientes c ON v.id = c.vistoria_id
            JOIN usuarios u ON c.email = u.email
            WHERE v.id = ? AND u.id = ?
        ");
        $stmt->execute([$vistoriaId, $_SESSION['usuario_id']]);
        return $stmt->fetch() !== false;
    }
    
    return false;
}

function podeAcessarMudanca($mudancaId, $pdo) {
    if (isGestor()) {
        return true;
    }
    
    if (isCoordenador()) {
        $stmt = $pdo->prepare("SELECT coordenador_id FROM mudancas WHERE id = ?");
        $stmt->execute([$mudancaId]);
        $mudanca = $stmt->fetch();
        
        return $mudanca && $mudanca['coordenador_id'] == $_SESSION['usuario_id'];
    }
    
    if (isCliente()) {
        $stmt = $pdo->prepare("
            SELECT m.id 
            FROM mudancas m
            JOIN clientes c ON m.cliente_id = c.id
            JOIN usuarios u ON c.email = u.email
            WHERE m.id = ? AND u.id = ?
        ");
        $stmt->execute([$mudancaId, $_SESSION['usuario_id']]);
        return $stmt->fetch() !== false;
    }
    
    return false;
}

function redirecionarPorTipo() {
    switch($_SESSION['usuario_tipo']) {
        case 'gestor':
        case 'vendedor':
            return 'index.php';
        case 'cotador':
            return 'cotacoes.php';
        case 'coordenador':
            return 'mudancas.php';
        case 'cliente':
            return 'portal-cliente.php';
        default:
            return 'login.php';
    }
}

// Middleware para páginas específicas
function apenasGestor() {
    verificarTipo(['gestor']);
}

function apenasVendedor() {
    verificarTipo(['vendedor']);
}

function apenasCotador() {
    verificarTipo(['cotador']);
}

function apenasCoordenador() {
    verificarTipo(['coordenador']);
}

function apenasCliente() {
    verificarTipo(['cliente']);
}

function gestorOuVendedor() {
    verificarTipo(['gestor', 'vendedor']);
}

function gestorOuCotador() {
    verificarTipo(['gestor', 'cotador']);
}

function gestorOuCoordenador() {
    verificarTipo(['gestor', 'coordenador']);
}

// Função para registrar atividade
function registrarAtividade($pdo, $acao, $detalhes = '') {
    if (isset($_SESSION['usuario_id'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                VALUES ('atividade', ?, '', ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['usuario_id'],
                $acao,
                $_SESSION['usuario_id'],
                $detalhes
            ]);
        } catch(PDOException $e) {
            error_log('Erro ao registrar atividade: ' . $e->getMessage());
        }
    }
}
?>