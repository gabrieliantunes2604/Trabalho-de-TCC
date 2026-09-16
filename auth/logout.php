<?php
session_start();

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Saindo...</title>
    <script>
        // Substitui a página atual no histórico pela tela de login e previne o botão "Voltar"
        window.location.replace('login.php');
    </script>
</head>
<body>
</body>
</html>