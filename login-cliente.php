<?php
session_start();

// Se já estiver logado como cliente, redireciona
if(isset($_SESSION['usuario_id']) && $_SESSION['usuario_tipo'] === 'cliente') {
    header('Location: portal-cliente.php');
    exit;
}

$erro = '';
$sucesso = '';

// Processar login
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'login') {
    require_once 'config.php';
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    //$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $cpf = $_POST['cpf'];

    if(empty($email) || empty($cpf)) {
        $erro = 'Por favor, preencha todos os campos';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido';
    } elseif(strlen($cpf) > 14) {
        $erro = 'CPF inválido';
    } else {
        try {
            // Verificar se o cliente existe
            $stmt = $pdo->prepare("
                SELECT c.*, u.id as usuario_id, u.nome as usuario_nome 
                FROM clientes c
                LEFT JOIN usuarios u ON c.email = u.email AND u.tipo = 'cliente'
                WHERE c.email = ? AND c.cpf = ?
            ");
            $stmt->execute([$email, $cpf]);
            $cliente = $stmt->fetch();

            echo $cpf;
            if($cliente) {
                if($cliente['usuario_id']) {
                    // Cliente já tem usuário, fazer login
                    $_SESSION['usuario_id'] = $cliente['usuario_id'];
                    $_SESSION['usuario_nome'] = $cliente['usuario_nome'];
                    $_SESSION['usuario_email'] = $cliente['email'];
                    $_SESSION['usuario_tipo'] = 'cliente';
                    
                    // Atualizar último acesso
                    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                    $stmt->execute([$cliente['usuario_id']]);
                    
                    // Registrar log
                    $stmt = $pdo->prepare("
                        INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                        VALUES ('usuarios', ?, 'logout', 'login', ?, ?)
                    ");
                    $stmt->execute([
                        $cliente['usuario_id'], 
                        $cliente['usuario_id'], 
                        'Login cliente realizado - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Desconhecido')
                    ]);
                    
                    header('Location: portal-cliente.php');
                    exit;
                } else {
                    // Cliente existe mas não tem usuário, criar automaticamente
                    $senhaAleatoria = bin2hex(random_bytes(16));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (nome, email, senha, tipo, ativo) 
                        VALUES (?, ?, ?, 'cliente', 1)
                    ");
                    $stmt->execute([$cliente['nome'], $email, $senhaAleatoria]);
                    $usuarioId = $pdo->lastInsertId();
                    
                    // Fazer login automático
                    $_SESSION['usuario_id'] = $usuarioId;
                    $_SESSION['usuario_nome'] = $cliente['nome'];
                    $_SESSION['usuario_email'] = $email;
                    $_SESSION['usuario_tipo'] = 'cliente';
                    
                    header('Location: portal-cliente.php');
                    exit;
                }
            } else {
                $erro = 'Cliente não encontrado. Verifique seus dados ou entre em contato com o suporte.';
            }
        } catch(PDOException $e) {
            error_log('Erro no login do cliente: ' . $e->getMessage());
            $erro = 'Erro ao processar login. Tente novamente.';
        }
    }
}

// Verificar se veio de uma proposta
$tokenProposta = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Elementos decorativos de fundo */
        .bg-decoration {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: float 20s infinite ease-in-out;
        }

        .bg-decoration:nth-child(1) {
            top: -300px;
            left: -300px;
            animation-delay: 0s;
        }

        .bg-decoration:nth-child(2) {
            bottom: -300px;
            right: -300px;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(30, 60, 114, 0.3);
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
            line-height: 1.5;
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
        input[type="text"]:focus {
            outline: none;
            border-color: #2a5298;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        .cpf-hint {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 0.5rem;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
            box-shadow: 0 10px 30px rgba(30, 60, 114, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: #a0aec0;
            font-size: 14px;
            position: relative;
        }

        .link-section {
            text-align: center;
            margin-top: 30px;
        }

        .link-section a {
            color: #2a5298;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .link-section a:hover {
            color: #1e3c72;
            text-decoration: underline;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fc8181;
        }

        .alert-success {
            background-color: #f0fff4;
            color: #276749;
            border: 1px solid #9ae6b4;
        }

        .info-box {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h3 {
            color: #0050b3;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #003a8c;
            font-size: 14px;
            margin: 0;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-decoration"></div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </div>
            <h1>Portal do Cliente</h1>
            <p>Acompanhe sua mudança em tempo real</p>
        </div>

        <?php if($erro): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>

        <?php if($sucesso): ?>
            <div class="alert alert-success"><?php echo $sucesso; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <h3>Primeira vez aqui?</h3>
            <p>Use o email e CPF cadastrados em sua vistoria para acessar o portal.</p>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="login">
            <?php if($tokenProposta): ?>
                <input type="hidden" name="token_proposta" value="<?php echo htmlspecialchars($tokenProposta); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="seu@email.com"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="cpf">CPF</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </span>
                    <input 
                        type="text" 
                        id="cpf" 
                        name="cpf" 
                        placeholder="000.000.000-00"
                        maxlength="14"
                        required
                        autocomplete="off"
                    >
                </div>
                <p class="cpf-hint">Digite apenas os números do CPF</p>
            </div>

            <button type="submit" class="btn-login">
                Acessar Portal
            </button>
        </form>

        <div class="divider">
            <span>ou</span>
        </div>

        <div class="link-section">
            <p>É funcionário? <a href="login.php">Acesse o sistema interno</a></p>
        </div>
    </div>

    <script>
        // Máscara para CPF
        document.getElementById('cpf').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            if (value.length > 0) {
                formattedValue = value.substring(0, 3);
                if (value.length > 3) {
                    formattedValue += '.' + value.substring(3, 6);
                }
                if (value.length > 6) {
                    formattedValue += '.' + value.substring(6, 9);
                }
                if (value.length > 9) {
                    formattedValue += '-' + value.substring(9, 11);
                }
            }
            
            e.target.value = formattedValue;
        });

        // Prevenir envio se CPF não estiver completo
        document.querySelector('form').addEventListener('submit', function(e) {
            const cpf = document.getElementById('cpf').value.replace(/\D/g, '');
            if (cpf.length !== 11) {
                e.preventDefault();
                alert('Por favor, digite um CPF válido com 11 dígitos.');
            }
        });
    </script>
</body>
</html>