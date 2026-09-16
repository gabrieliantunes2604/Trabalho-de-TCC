<?php
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Acesso inválido ou link expirado.");
}

$token = $_GET['token'];
$tipo = ($_GET['tipo'] ?? 'aluno') === 'admin' ? 'admin' : 'aluno';

if ($tipo === 'admin') {
    garantirEstruturaAdmins($conn);
    $stmt = $conn->prepare("SELECT id FROM admins WHERE token_recuperacao = :token");
} else {
    $stmt = $conn->prepare("SELECT id FROM alunos WHERE token_recuperacao = :token");
}
$stmt->execute(['token' => $token]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Este link é inválido ou já foi utilizado.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
    if ($tipo === 'admin') {
        $stmt_update = $conn->prepare("UPDATE admins SET senha = :senha, token_recuperacao = NULL WHERE id = :id");
        $login = 'login.php';
    } else {
        $stmt_update = $conn->prepare("UPDATE alunos SET senha = :senha, token_recuperacao = NULL WHERE id = :id");
        $login = 'login_aluno.php';
    }
    $stmt_update->execute(['senha' => $nova_senha, 'id' => $usuario['id']]);

    echo "<script>alert('Sua senha foi alterada com sucesso! Faça login para continuar.'); window.location.href='{$login}';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Senha - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100 bg-light">
    <!-- Container centralizado para a caixa do formulário -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
        <div class="card border-0 shadow-sm p-4 p-md-5" style="width: 100%; max-width: 450px;">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark">Criar Nova Senha</h3>
                <p class="text-muted">Digite sua nova senha de acesso abaixo.</p>
            </div>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Nova Senha</label>
                    <input type="password" name="nova_senha" class="form-control" placeholder="Mínimo de 6 caracteres" required>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
                    Salvar e Acessar
                </button>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>

</html>