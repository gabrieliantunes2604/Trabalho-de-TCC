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

// Busca o ID e o E-mail do utilizador
if ($tipo === 'admin') {
    $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email = :email OR usuario = :email LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT id, email FROM alunos WHERE email = :email LIMIT 1");
}
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Define um link padrão caso o utilizador não exista
$link = "esqueci_senha.php?tipo={$tipo}";

if ($usuario && !empty($usuario['email'])) {
    $token = bin2hex(random_bytes(32));
    $tabela = $tipo === 'admin' ? 'admins' : 'alunos';
    $tokenHash = hash('sha256', $token);

    // Atualiza a tabela com o token
    $stmtUpdate = $conn->prepare("UPDATE {$tabela} SET token_recuperacao = :token WHERE id = :id");
    $stmtUpdate->execute([
        'token' => $tokenHash,
        'id' => $usuario['id']
    ]);

    // Monta o link real de redefinição
    $link = $base . $pasta . "/redefinir_senha.php?token=" . $token . "&tipo=" . $tipo;

    @mail($usuario['email'], "Recuperação de senha", "Acesse em até 30 min: $link");
}

// Apresenta o ecrã de simulação local
echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light d-flex align-items-center justify-content-center' style='height: 100vh;'>";
echo "<div class='card p-5 text-center shadow-sm' style='max-width: 500px; border-radius: 12px;'>";
echo "<h2 class='text-success fw-bold mb-3'>Tudo certo!</h2>";
echo "<p class='text-muted mb-4'>Em um sistema real, um e-mail seria enviado agora. No ambiente local, clique no botão abaixo:</p>";
echo "<a href='" . htmlspecialchars($link) . "' class='btn btn-success btn-lg fw-bold'>Simular clique no E-mail</a>";
echo "</div></body></html>";
exit;