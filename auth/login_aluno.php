<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

// Se já estiver logado como aluno, redireciona para o painel do aluno limpando a rota anterior
if (isset($_SESSION['aluno_id'])) {
    echo "<script>window.location.replace('../auth/login_aluno.php');</script>";
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (!empty($email) && !empty($senha)) {
        try {
            $sql = "SELECT * FROM alunos WHERE email = :email";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($aluno && senhaConfere($senha, $aluno['senha'] ?? '', $aluno['data_nascimento'] ?? null)) {
                $_SESSION['aluno_id'] = $aluno['id'];
                $_SESSION['aluno_nome'] = $aluno['nome'];
                $_SESSION['aluno_email'] = $aluno['email'];

                require_once '../includes/funcoes_log.php';
                registrarLog($conn, 'login', 'Aluno realizou login no sistema', $aluno['id']);

                if (ehSenhaReset($senha, $aluno['data_nascimento'] ?? null)) {
                    $_SESSION['forcar_troca_senha'] = true;
                    echo "<script>window.location.replace('alterar_senha.php?tipo=aluno');</script>";
                    exit;
                }

                echo "<script>window.location.replace('../aluno/painel_aluno.php');</script>";
                exit;
            } else {
                $erro = "E-mail ou senha incorretos!";
            }
        } catch (PDOException $e) {
            $erro = "Erro no sistema: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Aluno - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-custom {
            background-color: #111c44;
        }

        .card-login {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }

        .btn-aluno {
            background-color: #4318FF;
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px;
            transition: 0.3s;
        }

        .btn-aluno:hover {
            background-color: #3311DB;
            color: white;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">Engenharia Academy</a>
            <a href="../index.php" class="btn btn-outline-light btn-sm rounded-3">← Voltar ao site</a>
        </div>
    </nav>

    <div class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-login p-4 p-md-5">

                    <div class="text-center mb-4">
                        <div class="fs-1 mb-2">🎓</div>
                        <h3 class="fw-bold text-dark mb-1">Área do Aluno</h3>
                        <p class="text-muted small">Acesse seus cursos e materiais exclusivos</p>
                    </div>

                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger text-center small rounded-3 mb-4"><?php echo $erro; ?></div>
                    <?php endif; ?>

                    <!-- Form com autocomplete="off" -->
                    <form action="" method="POST" autocomplete="off" id="formLogin">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">E-mail</label>
                            <input type="email" class="form-control py-2" id="email" name="email"
                                placeholder="seu.email@exemplo.com" required autocomplete="nope" value="">
                        </div>

                        <div class="mb-4">
                            <label for="senha" class="form-label fw-semibold">Senha</label>
                            <input type="password" class="form-control py-2" id="senha" name="senha"
                                placeholder="Sua senha de acesso" required autocomplete="new-password" value="">
                        </div>

                        <div class="text-end mb-3">
                            <a href="esqueci_senha.php" class="text-primary text-decoration-none small fw-bold">Esqueceu
                                sua senha?</a>
                        </div>

                        <button type="submit" class="btn btn-aluno w-100 mb-3">Entrar na Plataforma</button>

                        <div class="text-center">
                            <span class="text-muted small">Ainda não possui conta?</span>
                            <a href="cadastro.php" class="small fw-bold text-decoration-none" style="color: #4318FF;">
                                Cadastre-se gratuitamente</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Script Anti-Preenchimento Automático -->
    <script>
        // Limpa forçado os inputs assim que a página carregar
        document.addEventListener("DOMContentLoaded", function () {
            setTimeout(function () {
                let inputEmail = document.getElementById('email');
                let inputSenha = document.getElementById('senha');
                if (inputEmail) inputEmail.value = '';
                if (inputSenha) inputSenha.value = '';
            }, 100);
        });

        // Limpa caso o usuário navegue usando o botão "Voltar"
        window.addEventListener('pageshow', function () {
            let form = document.getElementById('formLogin');
            if (form) form.reset();
        });
    </script>

    <!-- Chama o arquivo do Rodapé -->
    <?php include '../includes/footer.php'; ?>

</body>

</html>