<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

// Se o aluno já estiver logado, redireciona para o painel
if (isset($_SESSION['aluno_id'])) {
    header("Location: ../aluno/painel_aluno.php");
    exit;
}

$erro = "";
$sucesso = "";
$curso_id = $_GET['curso_id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    $cpf = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');

    // 1. Validação de campos obrigatórios
    if (empty($nome) || empty($email) || empty($senha) || empty($cpf) || empty($telefone) || empty($data_nascimento)) {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!validarEmail($email)) {
        $erro = "O e-mail informado não possui um formato válido.";
    } elseif (!validarCPF($cpf)) {
        $erro = "O CPF informado é inválido. Verifique os números digitados.";
    } elseif (strlen($senha) < 6) {
        $erro = "A senha deve conter pelo menos 6 caracteres.";
    } elseif ($senha !== $confirma_senha) {
        $erro = "A confirmação de senha não confere com a senha digitada.";
    } elseif (pinNascimento($data_nascimento) === '') {
        $erro = "Data de nascimento inválida.";
    } else {
        $cpfLimpo = preg_replace('/\D+/', '', $cpf);
        $cpfFormatado = formatarCPF($cpfLimpo);

        // 2. Verifica duplicidade de E-mail
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = "Já existe uma conta cadastrada com este e-mail.";
        } else {
            // 3. Verifica duplicidade de CPF
            $stmt = $conn->prepare("SELECT id FROM alunos WHERE cpf = ? OR cpf = ?");
            $stmt->execute([$cpfFormatado, $cpfLimpo]);
            if ($stmt->fetch()) {
                $erro = "Já existe uma conta cadastrada com este CPF.";
            } else {
                // 4. Criação do hash e inserção
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(16));

                $stmtInsert = $conn->prepare("
                    INSERT INTO alunos (nome, email, senha, cpf, data_nascimento, telefone, token_confirmacao, email_confirmado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmtInsert->execute([
                    $nome,
                    $email,
                    $senhaHash,
                    $cpfFormatado,
                    $data_nascimento,
                    $telefone,
                    $token
                ]);

                $novo_id = $conn->lastInsertId();

                // Log de auditoria
                require_once '../includes/funcoes_log.php';
                registrarLog($conn, 'cadastro', 'Novo aluno cadastrado diretamente pelo site', $novo_id);

                // Inicia sessão
                $_SESSION['aluno_id'] = $novo_id;
                $_SESSION['aluno_nome'] = $nome;
                $_SESSION['aluno_email'] = $email;

                if (!empty($curso_id)) {
                    header("Location: ../checkout.php?curso_id=" . (int) $curso_id);
                    exit;
                }

                header("Location: ../aluno/painel_aluno.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta de Aluno - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .card-cadastro {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }

        .btn-cadastro {
            background-color: #4318FF;
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px;
            transition: 0.3s;
            border: none;
        }

        .btn-cadastro:hover {
            background-color: #3311DB;
            color: white;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">Engenharia Academy</a>
            <a href="login_aluno.php" class="btn btn-outline-light btn-sm rounded-3">Já tenho conta (Entrar)</a>
        </div>
    </nav>

    <div class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card card-cadastro p-4 p-md-5">

                    <div class="text-center mb-4">
                        <div class="fs-1 mb-2">🧑‍🎓</div>
                        <h3 class="fw-bold text-dark mb-1">Criar Conta de Aluno</h3>
                        <p class="text-muted small">Preencha seus dados para ter acesso à plataforma</p>
                    </div>

                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger text-center small rounded-3 mb-4">
                            <?php echo htmlspecialchars($erro); ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" id="formCadastro">
                        <?php if (!empty($curso_id)): ?>
                            <input type="hidden" name="curso_id" value="<?php echo (int) $curso_id; ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Nome Completo <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" name="nome"
                                    value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required
                                    placeholder="Digite seu nome completo">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control py-2" name="email"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required
                                    placeholder="seu@email.com">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">CPF <span class="text-danger">*</span></label>
                                <input type="text" class="form-control py-2" id="cpfInput" name="cpf" maxlength="14"
                                    value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>" required
                                    placeholder="000.000.000-00">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Telefone / WhatsApp <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control py-2" id="telInput" name="telefone" maxlength="15"
                                    value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>" required
                                    placeholder="(00) 00000-0000">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Data de Nascimento <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control py-2" name="data_nascimento"
                                    value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Senha (mínimo 6 caracteres) <span
                                        class="text-danger">*</span></label>
                                <input type="password" class="form-control py-2" name="senha" minlength="6" required
                                    placeholder="Crie uma senha">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirmar Senha <span
                                        class="text-danger">*</span></label>
                                <input type="password" class="form-control py-2" name="confirma_senha" minlength="6"
                                    required placeholder="Repita a senha">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-cadastro w-100 py-3">Finalizar Cadastro</button>
                        </div>

                        <div class="text-center mt-3">
                            <span class="text-muted small">Já possui uma conta?</span>
                            <a href="login_aluno.php" class="small fw-bold text-decoration-none"
                                style="color: #4318FF;"> Fazer Login</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Script de Máscara de CPF e Telefone -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cpfInput = document.getElementById('cpfInput');
            const telInput = document.getElementById('telInput');

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

    <?php include '../includes/footer.php'; ?>
</body>

</html>