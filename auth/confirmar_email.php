<?php
session_start();
require '../includes/conexao.php';

$mensagem = "";
$sucesso = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    // Busca o aluno pelo token
    $stmt = $conn->prepare("SELECT id, email_confirmado FROM alunos WHERE token_confirmacao = :token");
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aluno) {
        if ($aluno['email_confirmado'] == 1) {
            $mensagem = "Seu e-mail já foi verificado anteriormente. Você já pode fazer login!";
            $sucesso = true;
        } else {
            // Ativa a conta e limpa o token
            $stmtUpdate = $conn->prepare("UPDATE alunos SET email_confirmado = 1, token_confirmacao = NULL WHERE id = :id");
            $stmtUpdate->bindParam(':id', $aluno['id'], PDO::PARAM_INT);

            if ($stmtUpdate->execute()) {
                $mensagem = "E-mail confirmado com sucesso! Sua conta está ativada.";
                $sucesso = true;
            } else {
                $mensagem = "Erro ao ativar sua conta. Tente novamente mais tarde.";
            }
        }
    } else {
        $mensagem = "Link de confirmação inválido ou expirado.";
    }
} else {
    $mensagem = "Nenhum token de verificação foi fornecido.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Confirmação de E-mail - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 text-center rounded-4">
                    <h3 class="fw-bold mb-3">Validação de Conta</h3>

                    <div class="alert <?php echo $sucesso ? 'alert-success' : 'alert-danger'; ?> py-3">
                        <?php echo htmlspecialchars($mensagem); ?>
                    </div>

                    <div class="mt-3">
                        <a href="login_aluno.php" class="btn btn-primary px-4 py-2 fw-bold">Ir para o Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>