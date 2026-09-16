<?php
session_start();
require 'includes/conexao.php';

// Proteção: verifica se o aluno está logado
if (!isset($_SESSION['aluno_id'])) {
    $curso_id = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'curso_id', FILTER_VALIDATE_INT);
    // CORRIGIDO: Redireciona para auth/login_aluno.php
    header("Location: auth/login_aluno.php" . ($curso_id ? "?redirect=confirmar_matricula.php?curso_id=" . $curso_id : ""));
    exit;
}

$curso_id = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'curso_id', FILTER_VALIDATE_INT);

if (!$curso_id) {
    // CORRIGIDO: Redireciona para aluno/cursos.php (ou cursos.php na raiz/aluno conforme a estrutura)
    header("Location: aluno/cursos.php");
    exit;
}

// Busca os dados do curso
$stmtC = $conn->prepare("SELECT * FROM cursos WHERE id = ?");
$stmtC->execute([$curso_id]);
$curso = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    // CORRIGIDO: Redireciona para aluno/cursos.php
    header("Location: aluno/cursos.php");
    exit;
}

// Busca os dados do aluno
$stmtA = $conn->prepare("SELECT * FROM alunos WHERE id = ?");
$stmtA->execute([$_SESSION['aluno_id']]);
$aluno = $stmtA->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Matrícula - <?php echo htmlspecialchars($curso['titulo']); ?></title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7fe;
            color: #2b3674;
        }

        .card-confirmacao {
            border: none;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="py-5">

    <div class="container" style="max-width: 700px;">
        <div class="mb-4">
            <!-- CORRIGIDO: Redireciona para aluno/detalhes_curso.php -->
            <a href="aluno/detalhes_curso.php?id=<?php echo $curso_id; ?>" class="btn btn-outline-secondary rounded-3">←
                Voltar para o curso</a>
        </div>

        <div class="card card-confirmacao p-4 p-md-5">
            <h3 class="fw-bold mb-4" style="color: #111c44;">Confirmar Matrícula</h3>

            <!-- Resumo do Curso -->
            <div class="p-3 mb-4 rounded-3" style="background-color: #f8f9fa; border-left: 4px solid #4318FF;">
                <span class="text-muted small fw-semibold">Curso Selecionado:</span>
                <h5 class="fw-bold mb-1 mt-1"><?php echo htmlspecialchars($curso['titulo']); ?></h5>
                <span class="fs-5 fw-bold text-success">R$
                    <?php echo number_format($curso['preco'], 2, ',', '.'); ?></span>
            </div>

            <!-- Identificação do Aluno -->
            <div class="alert alert-light border mb-4">
                <span class="text-muted small d-block mb-1">Aluno identificado:</span>
                <strong><?php echo htmlspecialchars($aluno['nome']); ?></strong>
                <span class="text-muted">(<?php echo htmlspecialchars($aluno['email']); ?>)</span>
            </div>

            <!-- Form de Finalização -->
            <!-- CORRIGIDO: Redireciona para aluno/processa_pagamento.php -->
            <form action="aluno/processa_pagamento.php" method="POST">
                <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">

                <h5 class="fw-bold mb-3">Escolha a Forma de Pagamento</h5>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="pagamento" id="pix" value="pix" checked>
                        <label class="form-check-label fw-semibold" for="pix">
                            ⚡ PIX (Aguardando confirmação)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="pagamento" id="cartao" value="cartao">
                        <label class="form-check-label fw-semibold" for="cartao">
                            💳 Cartão de Crédito
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold rounded-3 py-3"
                    style="background-color: #05CD99; border: none;">
                    Confirmar Matrícula
                </button>
            </form>
        </div>
    </div>

</body>

</html>