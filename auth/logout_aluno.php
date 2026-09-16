<?php
session_start();

// 1. Limpa todas as variáveis de sessão
$_SESSION = array();

// 2. Apaga o cookie de sessão do navegador (se existir)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destrói a sessão no servidor
session_destroy();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Saindo...</title>
    <!-- Desativa o cache da página de saída -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <script>
        // Substitui a rota atual no histórico do navegador pela tela de login.
        // Isso remove a página anterior do histórico e impede que o botão 'Voltar' funcione!
        window.location.replace('login_aluno.php');
    </script>
</head>
<body>
</body>
</html>