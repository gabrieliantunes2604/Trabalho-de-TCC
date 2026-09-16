<?php
session_start();
require 'includes/conexao.php';
require_once 'includes/funcoes_config.php';

$curso_id = $_POST['curso_id'] ?? $_POST['id_curso'] ?? $_GET['curso_id'] ?? null;

if (!$curso_id) {
    // CORRIGIDO: Redireciona para a lista de cursos dentro da pasta aluno/
    header("Location: aluno/cursos.php");
    exit;
}

$aluno_id = null;

// Identifica Aluno Logado ou Cadastra Novo
if (isset($_SESSION['aluno_id'])) {
    $aluno_id = $_SESSION['aluno_id'];
} else {
    $email           = trim($_POST['email'] ?? '');
    $nome            = trim($_POST['nome'] ?? '');
    $senha           = trim($_POST['senha'] ?? '');
    $cpf             = trim($_POST['cpf'] ?? '');
    $telefone        = trim($_POST['telefone'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');

    if (empty($nome) || !validarEmail($email)) {
        echo "<script>alert('Por favor, informe um e-mail válido e seu nome completo.'); history.back();</script>";
        exit;
    }

    if (!empty($cpf) && !validarCPF($cpf)) {
        echo "<script>alert('CPF inválido. Por favor, verifique os dígitos digitados.'); history.back();</script>";
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aluno) {
        $aluno_id = $aluno['id'];
    } else {
        $senhaHash = password_hash($senha ?: '123456', PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16));
        $cpfValor = !empty($cpf) ? formatarCPF($cpf) : ('TMP' . strtoupper(bin2hex(random_bytes(6))));
        $dataNascValor = !empty($data_nascimento) ? $data_nascimento : null;

        $stmtInsert = $conn->prepare("INSERT INTO alunos (nome, email, senha, cpf, data_nascimento, telefone, token_confirmacao, email_confirmado) VALUES (:nome, :email, :senha, :cpf, :data_nascimento, :telefone, :token, 1)");
        $stmtInsert->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':senha' => $senhaHash,
            ':cpf' => $cpfValor,
            ':data_nascimento' => $dataNascValor,
            ':telefone' => $telefone,
            ':token' => $token
        ]);
        $aluno_id = $conn->lastInsertId();
    }

    $_SESSION['aluno_id'] = $aluno_id;
    $_SESSION['aluno_nome'] = $nome;
    $_SESSION['aluno_email'] = $email;
}

$forma_pagamento = $_POST['pagamento'] ?? $_POST['forma_pagamento'] ?? 'pix';

// REGRA DE SEGURANÇA: Registra como 'pendente' aguardando confirmação do pagamento
$status_pagamento = 'pendente'; 

// Checa se o aluno já tem essa matrícula cadastrada
$stmtCheck = $conn->prepare("SELECT id FROM matriculas WHERE aluno_id = :aluno AND curso_id = :curso");
$stmtCheck->execute([':aluno' => $aluno_id, ':curso' => $curso_id]);

if ($stmtCheck->rowCount() == 0) {
    // Grava a solicitação de matrícula pendente
    $stmtMatricula = $conn->prepare("INSERT INTO matriculas (aluno_id, curso_id, forma_pagamento, status_pagamento, data_matricula) VALUES (:aluno, :curso, :pagamento, :status, NOW())");
    $stmtMatricula->execute([
        ':aluno' => $aluno_id,
        ':curso' => $curso_id,
        ':pagamento' => $forma_pagamento,
        ':status' => $status_pagamento
    ]);
}

// CORRIGIDO: Redireciona para o painel do aluno localizado na subpasta aluno/
header("Location: aluno/painel_aluno.php");
exit;