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

if ($modo === 'pin') {
    $pinInformado = preg_replace('/\D+/', '', $_POST['pin'] ?? '');
    if ($tipo === 'admin') {
        $stmt = $conn->prepare("SELECT id, data_nascimento FROM admins WHERE email = :email OR usuario = :email LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT id, data_nascimento FROM alunos WHERE email = :email LIMIT 1");
    }
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $pinReal = $usuario ? pinNascimento($usuario['data_nascimento'] ?? '') : '';
    if ($usuario && $pinReal !== '' && hash_equals($pinReal, $pinInformado)) {
        $hash = password_hash($pinReal, PASSWORD_DEFAULT);
        if ($tipo === 'admin') {
            $conn->prepare("UPDATE admins SET senha = ?, token_recuperacao = NULL WHERE id = ?")->execute([$hash, $usuario['id']]);
        } else {
            $conn->prepare("UPDATE alunos SET senha = ?, token_recuperacao = NULL WHERE id = ?")->execute([$hash, $usuario['id']]);
        }
        header("Location: esqueci_senha.php?tipo={$tipo}&msg=ok_pin");
        exit;
    }
    header("Location: esqueci_senha.php?tipo={$tipo}&msg=erro");
    exit;
}

if ($tipo === 'admin') {
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = :email OR usuario = :email LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = :email");
}
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    $token = bin2hex(random_bytes(50));
    if ($tipo === 'admin') {
        $stmt_update = $conn->prepare("UPDATE admins SET token_recuperacao = :token WHERE id = :id");
    } else {
        $stmt_update = $conn->prepare("UPDATE alunos SET token_recuperacao = :token WHERE id = :id");
    }
    $stmt_update->execute(['token' => $token, 'id' => $usuario['id']]);

    $link = $base . $pasta . "/redefinir_senha.php?token=" . $token . "&tipo=" . $tipo;

    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light d-flex align-items-center justify-content-center' style='height: 100vh;'>";
    echo "<div class='card p-5 text-center shadow-sm' style='max-width: 500px; border-radius: 12px;'>";
    echo "<h2 class='text-success fw-bold mb-3'>Tudo certo!</h2>";
    echo "<p class='text-muted mb-4'>Em um sistema real, um e-mail seria enviado agora. No ambiente local, clique no botão abaixo:</p>";
    echo "<a href='" . htmlspecialchars($link) . "' class='btn btn-success btn-lg fw-bold'>Simular clique no E-mail</a>";
    echo "</div></body></html>";
    exit;
}

echo "<script>alert('Se este e-mail estiver cadastrado, um link foi enviado.'); window.location.href='esqueci_senha.php?tipo={$tipo}';</script>";
