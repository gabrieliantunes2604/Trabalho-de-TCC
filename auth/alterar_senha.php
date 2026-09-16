<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

$erro = '';
$tipo = $_GET['tipo'] ?? ($_POST['tipo'] ?? '');
if (!in_array($tipo, ['aluno', 'admin'], true)) {
    if (isset($_SESSION['aluno_id'])) {
        $tipo = 'aluno';
    } elseif (!empty($_SESSION['admin_logado'])) {
        $tipo = 'admin';
    } else {
        header("Location: login_aluno.php");
        exit;
    }
}

if ($tipo === 'aluno' && empty($_SESSION['aluno_id'])) {
    header("Location: login_aluno.php");
    exit;
}
if ($tipo === 'admin' && empty($_SESSION['admin_logado'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova = $_POST['nova_senha'] ?? '';
    $confirma = $_POST['confirma_senha'] ?? '';
    if (strlen($nova) < 6) {
        $erro = 'A nova senha precisa ter pelo menos 6 caracteres.';
    } elseif ($nova !== $confirma) {
        $erro = 'A confirmação não confere.';
    } else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        if ($tipo === 'aluno') {
            $stmt = $conn->prepare("UPDATE alunos SET senha = ?, token_recuperacao = NULL WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['aluno_id']]);
            unset($_SESSION['forcar_troca_senha']);
            echo "<script>alert('Senha alterada com sucesso.'); window.location.href='../aluno/painel_aluno.php';</script>";
            exit;
        }
        garantirEstruturaAdmins($conn);
        $usuario = $_SESSION['admin_usuario'] ?? '';
        $stmt = $conn->prepare("UPDATE admins SET senha = ?, token_recuperacao = NULL WHERE usuario = ? OR email = ?");
        $stmt->execute([$hash, $usuario, $usuario]);
        unset($_SESSION['forcar_troca_senha']);
        echo "<script>alert('Senha alterada com sucesso.'); window.location.href='../admin/admin.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - Engenharia Academy</title>
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

<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card border-0 shadow-sm p-4 p-md-5" style="width: 100%; max-width: 450px; border-radius: 12px;">
        <h3 class="fw-bold text-dark">Alterar senha</h3>
        <p class="text-muted">Defina uma senha nova. O reset pela data de nascimento é só temporário, por segurança.</p>
        <?php if ($erro): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Nova senha</label>
                <input type="password" name="nova_senha" class="form-control" required minlength="6">
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Confirmar nova senha</label>
                <input type="password" name="confirma_senha" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-success w-100 fw-bold">Salvar senha</button>
        </form>
    </div>
</body>

</html>