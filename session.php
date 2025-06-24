<?php
// session.php - Verificação de sessão e controle de acesso
session_start();

// Verificar se está logado
if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Funções de controle de acesso
function isGestor() {
    return $_SESSION['usuario_tipo'] === 'gestor';
}

function isVendedor() {
    return $_SESSION['usuario_tipo'] === 'vendedor';
}

function podeExcluir() {
    return isGestor();
}

function podeEditarTodos() {
    return isGestor();
}

function podeVerTodos() {
    return isGestor();
}

function podeGerenciarVendedores() {
    return isGestor();
}

// Função para verificar se pode editar uma vistoria específica
function podeEditarVistoria($vistoria) {
    if(isGestor()) {
        return true;
    }
    
    // Vendedor só pode editar suas próprias vistorias
    if(isVendedor() && $vistoria['vendedor'] === $_SESSION['usuario_nome']) {
        return true;
    }
    
    return false;
}

// Função para obter filtro SQL baseado no tipo de usuário
function getFiltroVistorias() {
    if(isGestor()) {
        return ""; // Gestor vê todas
    } else {
        // Vendedor vê apenas as suas
        return " WHERE vendedor = '" . $_SESSION['usuario_nome'] . "'";
    }
}

// Variáveis globais para uso nas páginas
$usuario_nome = $_SESSION['usuario_nome'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$usuario_id = $_SESSION['usuario_id'];
?>