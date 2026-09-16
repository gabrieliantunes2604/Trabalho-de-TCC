<?php
session_start();
require 'includes/conexao.php';
require_once 'includes/funcoes_config.php';

$mensagem = "";
$ebook = null;
$compra_concluida = false;

// Controle do passo atual no fluxo (1 = Identificação, 2 = Pagamento, 3 = Conclusão)
$etapa = 1;

if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM ebooks WHERE id = :id");
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    $ebook = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$ebook) {
    header("Location: index.php");
    exit;
}

// Avança para a etapa de pagamento se o usuário confirmar a identificação
if (isset($_POST['avancar_pagamento'])) {
    if (!isset($_SESSION['aluno_id'])) {
        $email_destino = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $nome_destino = trim($_POST['nome'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $data_nascimento = trim($_POST['data_nascimento'] ?? '');

        if (empty($nome_destino) || !validarEmail($email_destino)) {
            $mensagem = "<div class='alert alert-danger text-center'>Por favor, informe seu nome completo e um e-mail válido.</div>";
            $etapa = 1;
        } elseif (!empty($cpf) && !validarCPF($cpf)) {
            $mensagem = "<div class='alert alert-danger text-center'>O CPF informado é inválido.</div>";
            $etapa = 1;
        } else {
            $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = :email");
            $stmt->bindParam(':email', $email_destino);
            $stmt->execute();
            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$aluno) {
                $token = bin2hex(random_bytes(16));
                $senhaHash = password_hash($senha ?: '123456', PASSWORD_DEFAULT);
                $cpfValor = !empty($cpf) ? formatarCPF($cpf) : ('TMP' . strtoupper(bin2hex(random_bytes(6))));
                $dataNascValor = !empty($data_nascimento) ? $data_nascimento : null;

                $stmtInsert = $conn->prepare("INSERT INTO alunos (nome, email, senha, cpf, data_nascimento, telefone, token_confirmacao, email_confirmado) VALUES (:nome, :email, :senha, :cpf, :data_nascimento, :telefone, :token, 1)");
                $stmtInsert->bindParam(':nome', $nome_destino);
                $stmtInsert->bindParam(':email', $email_destino);
                $stmtInsert->bindParam(':senha', $senhaHash);
                $stmtInsert->bindParam(':cpf', $cpfValor);
                $stmtInsert->bindParam(':data_nascimento', $dataNascValor);
                $stmtInsert->bindParam(':telefone', $telefone);
                $stmtInsert->bindParam(':token', $token);
                $stmtInsert->execute();
                $aluno_id = $conn->lastInsertId();
            } else {
                $aluno_id = $aluno['id'];
            }

            $_SESSION['aluno_id'] = $aluno_id;
            $_SESSION['aluno_nome'] = $nome_destino;
            $_SESSION['aluno_email'] = $email_destino;
            $etapa = 2;
        }
    } else {
        $etapa = 2;
    }
}

// Processa a compra (Etapa 2 -> Etapa 3)
if (isset($_POST['finalizar_compra'])) {
    $aluno_id = $_SESSION['aluno_id'] ?? null;
    $forma_pagamento = $_POST['pagamento'] ?? 'pix';

    if ($aluno_id) {
        $stmtCheck = $conn->prepare("SELECT id FROM compras_ebooks WHERE aluno_id = :aluno AND ebook_id = :ebook");
        $stmtCheck->bindParam(':aluno', $aluno_id);
        $stmtCheck->bindParam(':ebook', $ebook['id']);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() > 0) {
            $mensagem = "<div class='alert alert-warning text-center fw-bold'>Você já solicitou ou comprou este e-book! <br><a href='aluno/painel_aluno.php' class='text-primary text-decoration-underline'>Clique aqui para acessar seu painel</a>.</div>";
            $etapa = 2;
        } else {
            $stmtCompra = $conn->prepare("INSERT INTO compras_ebooks (aluno_id, ebook_id, forma_pagamento, status_pagamento) VALUES (:aluno, :ebook, :pagamento, 'pendente')");
            $stmtCompra->bindParam(':aluno', $aluno_id);
            $stmtCompra->bindParam(':ebook', $ebook['id']);
            $stmtCompra->bindParam(':pagamento', $forma_pagamento);
            $stmtCompra->execute();

            $compra_concluida = true;
            $etapa = 3;

            require_once 'includes/funcoes_log.php';
            registrarLog($conn, 'compra_ebook', "Solicitou compra do e-book: {$ebook['titulo']} (ID: {$ebook['id']}) via {$forma_pagamento}", $aluno_id);

            $email_destino = $_SESSION['aluno_email'] ?? '';
            $nome_destino = $_SESSION['aluno_nome'] ?? '';

            if ($email_destino) {
                $assunto = "Pedido Recebido - " . $ebook['titulo'];
                $corpo = "Olá " . $nome_destino . ",\n\n";
                $corpo .= "Recebemos o seu pedido do e-book '" . $ebook['titulo'] . "' via " . strtoupper($forma_pagamento) . ".\n\n";
                $corpo .= "Assim que o pagamento for confirmado, o e-book estará disponível no seu painel para download!\n";
                $corpo .= "Acesse: http://" . $_SERVER['HTTP_HOST'] . "/aluno/painel_aluno.php\n\n";
                $corpo .= "Atenciosamente,\nEquipe Engenharia Academy";

                $headers = "From: suporte@" . $_SERVER['HTTP_HOST'] . "\r\n" .
                    "Reply-To: suporte@" . $_SERVER['HTTP_HOST'] . "\r\n" .
                    "X-Mailer: PHP/" . phpversion();

                @mail($email_destino, $assunto, $corpo, $headers);
            }

            $mensagem = "<div class='alert alert-info text-center'>Pedido realizado com sucesso! Enviamos um e-mail de confirmação. <a href='aluno/painel_aluno.php' class='fw-bold text-primary'>Acompanhar no meu painel</a>.</div>";
        }
    } else {
        $etapa = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Comprar E-book - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light d-flex flex-column min-vh-100">
    <div class="container my-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 rounded-4">

                    <!-- STEPPER DINÂMICO DE PROGRESSO -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center position-relative">
                            <div class="progress position-absolute top-50 start-0 translate-middle-y w-100"
                                style="height: 3px; z-index: 1;">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                    style="width: <?php echo $etapa == 1 ? '0%' : ($etapa == 2 ? '50%' : '100%'); ?>;"></div>
                            </div>

                            <!-- PASSO 1 -->
                            <div class="text-center position-relative" style="z-index: 2;">
                                <span class="badge rounded-circle bg-primary text-white p-2 px-3">1</span>
                                <div class="small fw-bold mt-1 text-primary">Identificação</div>
                            </div>

                            <!-- PASSO 2 -->
                            <div class="text-center position-relative" style="z-index: 2;">
                                <span class="badge rounded-circle <?php echo $etapa >= 2 ? 'bg-primary' : 'bg-secondary'; ?> text-white p-2 px-3">2</span>
                                <div class="small <?php echo $etapa >= 2 ? 'fw-bold text-primary' : 'text-muted'; ?> mt-1">Pagamento</div>
                            </div>

                            <!-- PASSO 3 -->
                            <div class="text-center position-relative" style="z-index: 2;">
                                <span class="badge rounded-circle <?php echo $etapa == 3 ? 'bg-primary' : 'bg-secondary'; ?> text-white p-2 px-3">3</span>
                                <div class="small <?php echo $etapa == 3 ? 'fw-bold text-primary' : 'text-muted'; ?> mt-1">Conclusão</div>
                            </div>
                        </div>
                    </div>

                    <h3 class="fw-bold mb-3">
                        <?php 
                            if ($etapa == 1) echo "Identificação";
                            elseif ($etapa == 2) echo "Pagamento";
                            else echo "Pedido Concluído";
                        ?>
                    </h3>

                    <?php echo $mensagem; ?>

                    <!-- DETALHES DO PRODUTO (PASSO 1 E PASSO 2) -->
                    <?php if ($etapa < 3): ?>
                        <div class="p-3 mb-4 rounded-3 text-white" style="background-color: #111c44;">
                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($ebook['titulo']); ?></h5>
                            <h4 class="text-warning mb-0">R$ <?php echo number_format($ebook['preco'], 2, ',', '.'); ?></h4>
                        </div>
                    <?php endif; ?>

                    <!-- ==================== ETAPA 1: IDENTIFICAÇÃO ==================== -->
                    <?php if ($etapa == 1): ?>
                        <form action="" method="POST">
                            <?php if (isset($_SESSION['aluno_id'])): ?>
                                <div class="alert alert-info mb-4" style="background-color: #e0f7fa; border-color: #b2ebf2; color: #006064;">
                                    Você está comprando com a conta de <b><?php echo htmlspecialchars($_SESSION['aluno_nome'] ?? 'Aluno'); ?></b>.<br>
                                    <a href="auth/logout_aluno.php" class="text-decoration-underline" style="color: #00838f; font-size: 0.9em;">Não é você? Sair da conta.</a>
                                </div>
                            <?php else: ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Nome Completo <span class="text-danger">*</span></label>
                                        <input type="text" name="nome" class="form-control" required placeholder="Digite seu nome">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" required placeholder="seu@email.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Senha para Acesso <span class="text-danger">*</span></label>
                                        <input type="password" name="senha" minlength="6" class="form-control" required placeholder="Mínimo 6 caracteres">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">CPF <span class="text-danger">*</span></label>
                                        <input type="text" name="cpf" id="cpfEbook" maxlength="14" class="form-control" required placeholder="000.000.000-00">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Telefone / WhatsApp <span class="text-danger">*</span></label>
                                        <input type="tel" name="telefone" id="telEbook" maxlength="15" class="form-control" required placeholder="(00) 00000-0000">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Data de Nascimento <span class="text-danger">*</span></label>
                                        <input type="date" name="data_nascimento" class="form-control" required>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button type="submit" name="avancar_pagamento" class="btn btn-primary w-100 py-2 fw-bold">Continuar para Pagamento</button>
                        </form>
                    <?php endif; ?>

                    <!-- ==================== ETAPA 2: PAGAMENTO ==================== -->
                    <?php if ($etapa == 2): ?>
                        <div class="alert alert-light border mb-4">
                            <strong>Comprando como:</strong> <?php echo htmlspecialchars($_SESSION['aluno_nome'] ?? ''); ?> (<?php echo htmlspecialchars($_SESSION['aluno_email'] ?? ''); ?>)
                        </div>

                        <form action="" method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Forma de Pagamento</label>
                                <select name="pagamento" class="form-select" required>
                                    <option value="pix">Pix</option>
                                    <option value="cartao">Cartão de Crédito</option>
                                </select>
                            </div>

                            <button type="submit" name="finalizar_compra" class="btn btn-primary w-100 py-2 fw-bold">Finalizar Compra</button>
                        </form>
                    <?php endif; ?>

                    <!-- ==================== ETAPA 3: CONCLUSÃO ==================== -->
                    <?php if ($etapa == 3): ?>
                        <div class="text-center py-4">
                            <div class="mb-3 text-success">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l5-5.25a.75.75 0 0 0-.019-1.06z"/>
                                </svg>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cpfInput = document.getElementById('cpfEbook');
            const telInput = document.getElementById('telEbook');

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

    <?php include 'includes/footer.php'; ?>
</body>

</html>