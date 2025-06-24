<?php
// logout.php - Sistema de logout seguro
session_start();

// Guardar o nome do usuário para log (opcional)
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_id = $_SESSION['usuario_id'] ?? null;

// Se houver conexão com banco de dados, registrar o logout
if (file_exists('config.php')) {
    require_once 'config.php';
    
    try {
        // Registrar logout no histórico
        if ($usuario_id) {
            $stmt = $pdo->prepare("
                INSERT INTO historico_status 
                (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes, data_mudanca) 
                VALUES ('usuarios', ?, 'logado', 'deslogado', ?, 'Logout realizado', NOW())
            ");
            $stmt->execute([$usuario_id, $usuario_id]);
            
            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$usuario_id]);
        }
    } catch (Exception $e) {
        // Se houver erro ao registrar, continua com o logout
        error_log('Erro ao registrar logout: ' . $e->getMessage());
    }
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Prevenir cache da página
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Preparar mensagem de sucesso (opcional)
$mensagem_logout = urlencode("Logout realizado com sucesso!");

// Redirecionar para a página de login com mensagem
header("Location: login.php?logout=success&msg=" . $mensagem_logout);
exit;
?>