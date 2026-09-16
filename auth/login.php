<?php
session_start(); // Inicia a memória de sessão do PHP
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

garantirEstruturaAdmins($conn);

$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    // Busca o usuário no banco (usuario ou e-mail)
    $stmt = $conn->prepare("SELECT * FROM admins WHERE usuario = :usuario OR email = :usuario LIMIT 1");
    $stmt->bindParam(':usuario', $usuario);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && senhaConfere($senha, $admin['senha'] ?? '', $admin['data_nascimento'] ?? null)) {
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_usuario'] = $admin['usuario'];

        if (ehSenhaReset($senha, $admin['data_nascimento'] ?? null)) {
            $_SESSION['forcar_troca_senha'] = true;
            echo "<script>window.location.replace('alterar_senha.php?tipo=admin');</script>";
            exit;
        }

        echo "<script>window.location.replace('../admin/admin.php');</script>";
        exit;
    } else {
        $erro = "Usuário ou senha incorretos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Restrito - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0A2540;
        }

        .card-login {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .btn-neon {
            background-color: #ff6b00;
            color: white;
            font-weight: 600;
            transition: 0.3s;
            border: none;
        }

        .btn-neon:hover {
            background-color: #e65c00;
            color: white;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <!-- A tag <main> expande a área central e alinha o card no meio -->
    <main class="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-5">

        <div class="card card-login bg-white p-5">
            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: #0A2540;">Engenharia Academy</h3>
                <p class="text-muted">Acesso Administrativo</p>
            </div>

            <?php if ($erro)
                echo "<div class='alert alert-danger text-center'>$erro</div>"; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Usuário</label>
                    <input type="text" name="usuario" class="form-control" required placeholder="admin">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Senha</label>
                    <input type="password" name="senha" class="form-control" required placeholder="******">
                </div>
                <button type="submit" class="btn btn-neon w-100 py-2 rounded-3">Entrar no Sistema</button>
            </form>
            <div class="text-center mt-3">
                <a href="esqueci_senha.php?tipo=admin" class="text-decoration-none small fw-bold">Esqueci minha
                    senha</a>
            </div>
            <div class="text-center mt-2">
                <a href="../index.php" class="text-decoration-none text-muted" style="font-size: 0.9rem;">← Voltar ao
                    site</a>
            </div>
        </div>

    </main>


    <!-- Chama o arquivo do Rodapé -->
    <?php include '../includes/footer.php'; ?>

</body>

</html>