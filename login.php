<?php
session_start();

// Se já estiver logado, redireciona
if(isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$tentativasErro = 0;

// Verificar se há muitas tentativas de login falhadas
if(isset($_SESSION['tentativas_login'])) {
    $tentativasErro = $_SESSION['tentativas_login'];
    if($tentativasErro >= 5 && isset($_SESSION['bloqueio_ate'])) {
        if(time() < $_SESSION['bloqueio_ate']) {
            $tempoRestante = $_SESSION['bloqueio_ate'] - time();
            $minutosRestantes = ceil($tempoRestante / 60);
            $erro = "Muitas tentativas de login. Tente novamente em $minutosRestantes minutos.";
        } else {
            // Resetar tentativas após o tempo de bloqueio
            unset($_SESSION['tentativas_login']);
            unset($_SESSION['bloqueio_ate']);
            $tentativasErro = 0;
        }
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && $tentativasErro < 5) {
    require_once 'config.php';
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    // Validações
    if(empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido';
    } else {
        try {
            // Buscar usuário
            $stmt = $pdo->prepare("
                SELECT id, nome, email, senha, tipo, ativo, ultimo_acesso 
                FROM usuarios 
                WHERE email = ? AND ativo = 1
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if($usuario && $senha == $usuario['senha']) {
                // Login bem-sucedido
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                
                // Limpar tentativas de login
                unset($_SESSION['tentativas_login']);
                unset($_SESSION['bloqueio_ate']);
                
                // Atualizar último acesso
                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                $stmt->execute([$usuario['id']]);
                
                // Registrar log de acesso
                $stmt = $pdo->prepare("
                    INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                    VALUES ('usuarios', ?, 'logout', 'login', ?, ?)
                ");
                $stmt->execute([
                    $usuario['id'], 
                    $usuario['id'], 
                    'Login realizado - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Desconhecido')
                ]);
                
                // Redirecionar baseado no tipo de usuário
                switch($usuario['tipo']) {
                    case 'gestor':
                    case 'vendedor':
                        header('Location: index.php');
                        break;
                    case 'cotador':
                        header('Location: cotacoes.php');
                        break;
                    case 'coordenador':
                        header('Location: mudancas.php');
                        break;
                    case 'cliente':
                        header('Location: portal-cliente.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit;
            } else {
                // Incrementar tentativas de login
                $_SESSION['tentativas_login'] = ($tentativasErro + 1);
                
                if($_SESSION['tentativas_login'] >= 5) {
                    // Bloquear por 15 minutos
                    $_SESSION['bloqueio_ate'] = time() + (15 * 60);
                    $erro = 'Muitas tentativas de login. Tente novamente em 15 minutos.';
                } else {
                    $tentativasRestantes = 5 - $_SESSION['tentativas_login'];
                    $erro = "Email ou senha inválidos. $tentativasRestantes tentativas restantes.";
                }
                
                // Registrar tentativa falhada
                if($usuario) {
                    $stmt = $pdo->prepare("
                        INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                        VALUES ('usuarios', ?, 'login_failed', 'login_failed', NULL, ?)
                    ");
                    $stmt->execute([
                        $usuario['id'],
                        'Tentativa de login falhada - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Desconhecido')
                    ]);
                }
            }
        } catch(PDOException $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $erro = 'Erro ao processar login. Tente novamente.';
        }
    }
}

// Dados de demonstração para facilitar o teste
$usuariosDemonstracao = [
    ['email' => 'gestor@sistema.com', 'senha' => 'gestor123', 'tipo' => 'Gestor'],
    ['email' => 'vendedor@sistema.com', 'senha' => 'vendedor123', 'tipo' => 'Vendedor'],
    ['email' => 'cotador@sistema.com', 'senha' => 'cotador123', 'tipo' => 'Cotador'],
    ['email' => 'coordenador@sistema.com', 'senha' => 'coordenador123', 'tipo' => 'Coordenador']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Mudanças</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animação de fundo */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .logo svg {
            width: 50px;
            height: 50px;
            fill: white;
        }

        .login-header h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 700;
        }

        .login-header p {
            color: #718096;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            pointer-events: none;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {            
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f7fafc;
        }

        input[type="email"]:focus,
        input[type="text"]:focus,        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[type="email"]:hover,
        input[type="text"]:hover,        
        input[type="password"]:hover {
            border-color: #cbd5e0;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #4a5568;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error-message {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 24px;
            animation: shake 0.5s ease-in-out;
            box-shadow: 0 4px 20px rgba(245, 101, 101, 0.3);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }


        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #718096;
            font-size: 14px;
        }

        /* Responsividade */
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .demo-user-card {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }

        /* Animação de entrada para elementos */
        .form-group {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3H5C3.89 3 3 3.89 3 5V9C3 10.11 3.89 11 5 11H19C20.11 11 21 10.11 21 9V5C21 3.89 20.11 3 19 3M19 9H5V5H19V9M19 13H5C3.89 13 3 13.89 3 15V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V15C21 13.89 20.11 13 19 13M19 19H5V15H19V19Z"/>
                </svg>
            </div>
            <h1>Sistema de Mudanças</h1>
            <p>Faça login para continuar</p>
        </div>

        <?php if($erro): ?>
            <div class="error-message">
                <strong>⚠️ <?php echo htmlspecialchars($erro); ?></strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="seu@email.com"
                        required 
                        autofocus
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        <?php echo $tentativasErro >= 5 ? 'disabled' : ''; ?>
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        placeholder="••••••••"
                        required
                        <?php echo $tentativasErro >= 5 ? 'disabled' : ''; ?>
                    >
                    <span class="password-toggle" onclick="togglePassword()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </span>
                </div>
            </div>


            <button type="submit" class="btn-login" <?php echo $tentativasErro >= 5 ? 'disabled' : ''; ?>>
                <span id="btnText">Entrar</span>
                <div class="loading-spinner" id="loadingSpinner"></div>
            </button>
        </form>

    </div>

    <script>
        // Função para alternar visibilidade da senha
        function togglePassword() {
            const senhaInput = document.getElementById('senha');
            const type = senhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
            senhaInput.setAttribute('type', type);
            
            // Atualizar ícone
            const icon = event.currentTarget;
            if (type === 'text') {
                icon.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                `;
            } else {
                icon.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                `;
            }
        }


        // Mostrar spinner ao enviar formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn-login');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('loadingSpinner');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';
        });

        // Adicionar animação ao focar nos inputs
        const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.style.transform = 'scale(1)';
            });
        });

        // Verificar se há mensagem de bloqueio e mostrar contador
        <?php if($tentativasErro >= 5 && isset($_SESSION['bloqueio_ate'])): ?>
        let tempoRestante = <?php echo $_SESSION['bloqueio_ate'] - time(); ?>;
        
        function atualizarContador() {
            if(tempoRestante > 0) {
                const minutos = Math.ceil(tempoRestante / 60);
                const errorMsg = document.querySelector('.error-message');
                if(errorMsg) {
                    errorMsg.innerHTML = `<strong>⏱️ Bloqueado por ${minutos} minutos</strong>`;
                }
                tempoRestante--;
                setTimeout(atualizarContador, 1000);
            } else {
                window.location.reload();
            }
        }
        
        atualizarContador();
        <?php endif; ?>
    </script>
</body>
</html>