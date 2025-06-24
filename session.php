<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "seu_banco_de_dados";
    private $username = "seu_usuario";
    private $password = "sua_senha";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Erro de conexão: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// models/User.php
class User {
    private $conn;
    private $table_name = "usuarios";

    public $id;
    public $nome;
    public $email;
    public $senha;
    public $tipo; // 'gestor' ou 'vendedor'
    public $ativo;
    public $criado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $senha) {
        $query = "SELECT id, nome, email, senha, tipo, ativo 
                  FROM " . $this->table_name . " 
                  WHERE email = :email AND ativo = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($senha, $row['senha'])) {
                $this->id = $row['id'];
                $this->nome = $row['nome'];
                $this->email = $row['email'];
                $this->tipo = $row['tipo'];
                return true;
            }
        }
        return false;
    }

    public function createInitialUsers() {
        // Cria usuários iniciais se não existirem
        $usuarios = [
            [
                'nome' => 'Gestor Master',
                'email' => 'gestor@empresa.com',
                'senha' => password_hash('gestor123', PASSWORD_DEFAULT),
                'tipo' => 'gestor'
            ],
            [
                'nome' => 'Vendedor Teste',
                'email' => 'vendedor@empresa.com',
                'senha' => password_hash('vendedor123', PASSWORD_DEFAULT),
                'tipo' => 'vendedor'
            ]
        ];

        foreach($usuarios as $usuario) {
            $query = "INSERT IGNORE INTO " . $this->table_name . " 
                      (nome, email, senha, tipo, ativo) 
                      VALUES (:nome, :email, :senha, :tipo, 1)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":nome", $usuario['nome']);
            $stmt->bindParam(":email", $usuario['email']);
            $stmt->bindParam(":senha", $usuario['senha']);
            $stmt->bindParam(":tipo", $usuario['tipo']);
            $stmt->execute();
        }
    }
}

// session.php - Reescrito
session_start();

class SessionManager {
    private static $instance = null;
    
    private function __construct() {
        // Configurações de segurança da sessão
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Lax');
        
        // Define tempo de vida da sessão (30 minutos)
        ini_set('session.gc_maxlifetime', 1800);
        ini_set('session.cookie_lifetime', 1800);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SessionManager();
        }
        return self::$instance;
    }
    
    public function login($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_name'] = $userData['nome'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_type'] = $userData['tipo'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Regenera o ID da sessão por segurança
        session_regenerate_id(true);
    }
    
    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Verifica tempo de inatividade (30 minutos)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function getUserType() {
        return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
    }
    
    public function isGestor() {
        return $this->isLoggedIn() && $_SESSION['user_type'] === 'gestor';
    }
    
    public function isVendedor() {
        return $this->isLoggedIn() && $_SESSION['user_type'] === 'vendedor';
    }
    
    public function checkAccess($allowedTypes = []) {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
        
        if (!empty($allowedTypes) && !in_array($_SESSION['user_type'], $allowedTypes)) {
            header('Location: acesso_negado.php');
            exit();
        }
    }
    
    public function getUserData() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'nome' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'tipo' => $_SESSION['user_type']
            ];
        }
        return null;
    }
}

// login.php
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'session.php';

$session = SessionManager::getInstance();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    if ($user->login($email, $senha)) {
        $userData = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'tipo' => $user->tipo
        ];
        
        $session->login($userData);
        
        // Redireciona baseado no tipo de usuário
        if ($user->tipo === 'gestor') {
            header('Location: dashboard_gestor.php');
        } else {
            header('Location: dashboard_vendedor.php');
        }
        exit();
    } else {
        $message = 'Email ou senha inválidos!';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Vendas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        .error-message {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 5px;
        }
        
        .info-box {
            margin-top: 20px;
            padding: 15px;
            background-color: #e3f2fd;
            border-radius: 5px;
            font-size: 14px;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login do Sistema</h2>
        
        <?php if ($message): ?>
            <div class="error-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <button type="submit">Entrar</button>
        </form>
        
        <div class="info-box">
            <strong>Usuários de teste:</strong><br>
            Gestor: gestor@empresa.com / gestor123<br>
            Vendedor: vendedor@empresa.com / vendedor123
        </div>
    </div>
</body>
</html>

// dashboard_gestor.php
<?php
require_once 'session.php';

$session = SessionManager::getInstance();
$session->checkAccess(['gestor']);

$userData = $session->getUserData();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestor</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .welcome-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .menu-item {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .menu-item h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .menu-item p {
            color: #666;
        }
        
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .badge {
            background-color: #3498db;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard do Gestor</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($userData['nome']); ?></span>
            <span class="badge">Gestor</span>
            <a href="logout.php" class="logout-btn">Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-box">
            <h2>Bem-vindo, <?php echo htmlspecialchars($userData['nome']); ?>!</h2>
            <p>Você está logado como <strong>Gestor</strong>. Aqui você tem acesso completo a todas as funcionalidades do sistema.</p>
        </div>
        
        <div class="menu-grid">
            <div class="menu-item">
                <h3>Relatórios</h3>
                <p>Visualize relatórios completos de vendas e desempenho</p>
            </div>
            
            <div class="menu-item">
                <h3>Gerenciar Vendedores</h3>
                <p>Adicione, edite ou remova vendedores do sistema</p>
            </div>
            
            <div class="menu-item">
                <h3>Produtos</h3>
                <p>Gerencie o catálogo de produtos</p>
            </div>
            
            <div class="menu-item">
                <h3>Configurações</h3>
                <p>Configure parâmetros do sistema</p>
            </div>
            
            <div class="menu-item">
                <h3>Análise de Vendas</h3>
                <p>Dashboards e métricas avançadas</p>
            </div>
            
            <div class="menu-item">
                <h3>Comissões</h3>
                <p>Gerencie comissões e pagamentos</p>
            </div>
        </div>
    </div>
</body>
</html>

// dashboard_vendedor.php
<?php
require_once 'session.php';

$session = SessionManager::getInstance();
$session->checkAccess(['vendedor']);

$userData = $session->getUserData();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Vendedor</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .header {
            background-color: #27ae60;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .welcome-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            color: #27ae60;
            font-size: 32px;
            font-weight: bold;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .menu-item {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .menu-item h3 {
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .menu-item p {
            color: #666;
        }
        
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .badge {
            background-color: #f39c12;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard do Vendedor</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($userData['nome']); ?></span>
            <span class="badge">Vendedor</span>
            <a href="logout.php" class="logout-btn">Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-box">
            <h2>Bem-vindo, <?php echo htmlspecialchars($userData['nome']); ?>!</h2>
            <p>Você está logado como <strong>Vendedor</strong>. Acompanhe suas vendas e metas abaixo.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Vendas do Mês</h3>
                <div class="value">R$ 15.430</div>
            </div>
            <div class="stat-card">
                <h3>Comissão Acumulada</h3>
                <div class="value">R$ 1.543</div>
            </div>
            <div class="stat-card">
                <h3>Meta Mensal</h3>
                <div class="value">75%</div>
            </div>
            <div class="stat-card">
                <h3>Clientes Atendidos</h3>
                <div class="value">43</div>
            </div>
        </div>
        
        <div class="menu-grid">
            <div class="menu-item">
                <h3>Nova Venda</h3>
                <p>Registre uma nova venda</p>
            </div>
            
            <div class="menu-item">
                <h3>Minhas Vendas</h3>
                <p>Visualize seu histórico de vendas</p>
            </div>
            
            <div class="menu-item">
                <h3>Clientes</h3>
                <p>Gerencie seus clientes</p>
            </div>
            
            <div class="menu-item">
                <h3>Catálogo</h3>
                <p>Consulte produtos disponíveis</p>
            </div>
        </div>
    </div>
</body>
</html>

// logout.php
<?php
require_once 'session.php';

$session = SessionManager::getInstance();
$session->logout();

header('Location: login.php');
exit();
?>

// acesso_negado.php
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .error-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .back-btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Acesso Negado</h1>
        <p>Você não tem permissão para acessar esta página.</p>
        <a href="javascript:history.back()" class="back-btn">Voltar</a>
    </div>
</body>
</html>
