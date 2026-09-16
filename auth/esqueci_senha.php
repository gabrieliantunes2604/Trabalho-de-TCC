<?php
$tipo = ($_GET['tipo'] ?? 'aluno') === 'admin' ? 'admin' : 'aluno';
$loginVolta = $tipo === 'admin' ? 'login.php' : 'login_aluno.php';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <main class="d-flex flex-column justify-content-center align-items-center flex-grow-1 py-5">
        <div class="card p-4 p-sm-5 shadow-sm border-0" style="max-width: 450px; width: 100%; border-radius: 12px;">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark">Recuperar Senha</h3>
                <p class="text-muted mb-0"><?php echo $tipo === 'admin' ? 'Acesso administrativo' : 'Área do aluno'; ?>
                </p>
            </div>

            <?php if ($msg === 'ok_pin'): ?>
                <div class="alert alert-success small">Senha temporária definida: use a data de nascimento (somente números)
                    no login e depois altere a senha.</div>
            <?php elseif ($msg === 'erro'): ?>
                <div class="alert alert-danger small">Dados não conferem. Verifique e-mail e data de nascimento.</div>
            <?php endif; ?>

            <h6 class="fw-bold">Reset pela data de nascimento</h6>
            <p class="text-muted small">Se o admin já fez o reset (ou se você souber a data cadastrada), informe e-mail
                e nascimento só com números.</p>
            <form action="processa_recuperacao.php" method="POST" class="mb-4">
                <input type="hidden" name="modo" value="pin">
                <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Data de nascimento (somente números)</label>
                    <input type="text" name="pin" class="form-control" placeholder="Ex: 19082000" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold"
                    style="background-color: #4A00E0; border: none;">
                    Solicitar reset
                </button>
            </form>

            <hr>
            <h6 class="fw-bold">Ou receber link por e-mail</h6>
            <form action="processa_recuperacao.php" method="POST">
                <input type="hidden" name="modo" value="email">
                <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                <div class="mb-4">
                    <label class="form-label fw-bold">E-mail</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                        placeholder="seu.email@exemplo.com" required>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-lg w-100 fw-bold">
                    Enviar Link de Recuperação
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="<?php echo $loginVolta; ?>" class="text-muted text-decoration-none">
                    &larr; Voltar para o login
                </a>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>

</html>