<?php
session_start();
require 'includes/conexao.php';

$curso_id = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT);

if (!$curso_id) {
    header("Location: cursos.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    header("Location: cursos.php");
    exit;
}

// 1. Verifica se o aluno já possui o curso matriculado
$ja_possui = false;
$aluno_nome = '';
$aluno_email = '';

if (isset($_SESSION['aluno_id'])) {
    $aluno_id = $_SESSION['aluno_id'];

    // Busca dados do aluno
    $stmtA = $conn->prepare("SELECT nome, email FROM alunos WHERE id = ?");
    $stmtA->execute([$aluno_id]);
    $aluno = $stmtA->fetch(PDO::FETCH_ASSOC);
    if ($aluno) {
        $aluno_nome = $aluno['nome'];
        $aluno_email = $aluno['email'];
    }

    // Checa se já existe matrícula
    $stmtM = $conn->prepare("SELECT status_pagamento FROM matriculas WHERE aluno_id = ? AND curso_id = ?");
    $stmtM->execute([$aluno_id, $curso_id]);
    $matricula = $stmtM->fetch(PDO::FETCH_ASSOC);

    if ($matricula) {
        $ja_possui = true;
        $status_existente = $matricula['status_pagamento'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($curso['titulo']); ?></title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7fe;
            color: #2b3674;
        }

        .card-checkout {
            border: none;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="py-5">

    <div class="container" style="max-width: 800px;">
        <div class="mb-4">
            <a href="detalhes_curso.php?id=<?php echo $curso_id; ?>" class="btn btn-outline-secondary rounded-3">←
                Voltar para o curso</a>
        </div>

        <div class="card card-checkout p-4 p-md-5">
            <h3 class="fw-bold mb-4" style="color: #111c44;">Finalizar Matrícula</h3>

            <div class="p-3 mb-4 rounded-3" style="background-color: #f8f9fa; border-left: 4px solid #4318FF;">
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($curso['titulo']); ?></h5>
                <span class="fs-5 fw-bold text-success">R$
                    <?php echo number_format($curso['preco'], 2, ',', '.'); ?></span>
            </div>

            <!-- CASO 1: ALUNO JÁ TEM O CURSO -->
            <?php if ($ja_possui): ?>
                <div class="alert alert-warning p-4 rounded-3 text-center">
                    <h4 class="fw-bold mb-2">🎓 Você já possui este curso!</h4>
                    <p class="mb-3">Sua inscrição já está cadastrada no sistema (Status:
                        <strong><?php echo strtoupper($status_existente); ?></strong>).</p>
                    <!-- CORRIGIDO: Redireciona para aluno/painel_aluno.php -->
                    <a href="aluno/painel_aluno.php" class="btn btn-primary fw-bold px-4 py-2"
                        style="background-color: #4318FF; border: none;">
                        Ir para Meu Painel
                    </a>
                </div>

                <!-- CASO 2: ALUNO AINDA NÃO TEM O CURSO -->
            <?php else: ?>
                <form action="processa_pagamento.php" method="POST">
                    <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">

                    <?php if (!isset($_SESSION['aluno_id'])): ?>
                        <h5 class="fw-bold mb-3">Seus Dados</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control py-2" required
                                    placeholder="Digite seu nome completo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control py-2" required placeholder="seu@email.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Crie uma Senha <span class="text-danger">*</span></label>
                                <input type="password" name="senha" minlength="6" class="form-control py-2" required
                                    placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">CPF <span class="text-danger">*</span></label>
                                <input type="text" name="cpf" id="cpfCheckout" maxlength="14" class="form-control py-2" required
                                    placeholder="000.000.000-00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Telefone / WhatsApp <span
                                        class="text-danger">*</span></label>
                                <input type="tel" name="telefone" id="telCheckout" maxlength="15" class="form-control py-2"
                                    required placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Data de Nascimento <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="data_nascimento" class="form-control py-2" required>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-4">
                            Comprando como: <strong><?php echo htmlspecialchars($aluno_nome); ?></strong>
                            (<?php echo htmlspecialchars($aluno_email); ?>)<br>
                            <!-- CORRIGIDO: Redireciona para auth/logout_aluno.php -->
                            <a href="auth/logout_aluno.php" class="alert-link small">Não é você? Sair da conta</a>
                        </div>
                    <?php endif; ?>

                    <h5 class="fw-bold mb-3">Forma de Pagamento</h5>
                    <div class="mb-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="pagamento" id="pix" value="pix" checked>
                            <label class="form-check-label fw-semibold" for="pix">
                                ⚡ PIX
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
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cpfInput = document.getElementById('cpfCheckout');
            const telInput = document.getElementById('telCheckout');

            if (cpfInput) {
                cpfInput.addEventListener('input', function (e) {
                    let v = e.target.value.replace(/\D/g, '');
                    if (v.length > 11) v = v.substring(0, 11);
                    if (v.length > 9) {
                        v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                    } else if (v.length > 6) {
                        v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    } else if (v.length > 3) {
                        v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                    }
                    e.target.value = v;
                });
            }

            if (telInput) {
                telInput.addEventListener('input', function (e) {
                    let v = e.target.value.replace(/\D/g, '');
                    if (v.length > 11) v = v.substring(0, 11);
                    if (v.length > 10) {
                        v = v.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    } else if (v.length > 6) {
                        v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                    } else if (v.length > 2) {
                        v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
                    } else if (v.length > 0) {
                        v = v.replace(/^(\d{0,2})/, '($1');
                    }
                    e.target.value = v;
                });
            }
        });
    </script>

</body>

</html>