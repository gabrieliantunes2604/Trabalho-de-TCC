<?php
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: esqueci_senha.php");
    exit;
}

$tipo = ($_POST['tipo'] ?? 'aluno') === 'admin' ? 'admin' : 'aluno';
$modo = $_POST['modo'] ?? 'email';
$email = trim($_POST['email'] ?? '');
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

if ($tipo === 'admin') {
    garantirEstruturaAdmins($conn);
}


if ($tipo === 'admin') {
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = :email OR usuario = :email LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = :email");
}
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && !empty($usuario['email'])) {    // SELECT id, email
    $token = bin2hex(random_bytes(32));
    $tabela = $tipo === 'admin' ? 'admins' : 'alunos';
    $conn->prepare("UPDATE $tabela SET token_recuperacao = :token, token_expiracao = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?")
        ->execute(['hash'('sha256', $token), $usuario['id']]);
    $link = $base . $pasta . "/redefinir_senha.php?token=" . $token . "&tipo=" . $tipo;
    @mail($usuario['email'], "Recuperação de senha", "Acesse em até 30 min: $link");
}

    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light d-flex align-items-center justify-content-center' style='height: 100vh;'>";
    echo "<div class='card p-5 text-center shadow-sm' style='max-width: 500px; border-radius: 12px;'>";
    echo "<h2 class='text-success fw-bold mb-3'>Tudo certo!</h2>";
    echo "<p class='text-muted mb-4'>Em um sistema real, um e-mail seria enviado agora. No ambiente local, clique no botão abaixo:</p>";
    echo "<a href='" . htmlspecialchars($link) . "' class='btn btn-success btn-lg fw-bold'>Simular clique no E-mail</a>";
    echo "</div></body></html>";
    exit;


echo "<script>alert('Se este e-mail estiver cadastrado, um link foi enviado.'); window.location.href='esqueci_senha.php?tipo={$tipo}';</script>";
