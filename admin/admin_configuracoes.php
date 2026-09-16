<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

garantirEstruturaAdmins($conn);

$msg = '';
$msg_tipo = 'success';
$admin_logado_usuario = $_SESSION['admin_usuario'] ?? '';

function cpfUnicoTemp()
{
    return 'TMP' . strtoupper(bin2hex(random_bytes(6)));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'add_aluno') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $nasc = trim($_POST['data_nascimento'] ?? '');

            if ($nome === '' || $email === '' || $nasc === '') {
                throw new Exception('Preencha nome, e-mail e data de nascimento do aluno.');
            }

            $pin = pinNascimento($nasc);
            if ($pin === '') {
                throw new Exception('Data de nascimento inválida.');
            }

            $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe um aluno com este e-mail.');
            }

            if ($cpf === '') {
                $cpf = cpfUnicoTemp();
            }

            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO alunos (nome, email, senha, cpf, data_nascimento, telefone, email_confirmado) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nome, $email, $hash, $cpf, $nasc, $telefone]);
            $msg = 'Aluno cadastrado. Senha inicial: data de nascimento (somente números).';
        }

        if ($acao === 'del_aluno') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Aluno inválido.');
            }
            $conn->prepare("DELETE FROM matriculas WHERE aluno_id = ?")->execute([$id]);
            $conn->prepare("DELETE FROM compras_ebooks WHERE aluno_id = ?")->execute([$id]);
            try {
                $conn->prepare("DELETE FROM logs_acesso WHERE aluno_id = ?")->execute([$id]);
            } catch (PDOException $e) {
                // tabela de log pode não existir
            }
            $conn->prepare("DELETE FROM alunos WHERE id = ?")->execute([$id]);
            $msg = 'Aluno excluído.';
        }

        if ($acao === 'reset_aluno') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT data_nascimento FROM alunos WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Aluno não encontrado.');
            }
            $pin = pinNascimento($row['data_nascimento']);
            if ($pin === '') {
                throw new Exception('Este aluno não tem data de nascimento cadastrada. Atualize o cadastro antes do reset.');
            }
            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE alunos SET senha = ?, token_recuperacao = NULL WHERE id = ?")->execute([$hash, $id]);
            $msg = 'Senha do aluno redefinida para a data de nascimento (somente números).';
        }

        if ($acao === 'edit_aluno') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $nasc = trim($_POST['data_nascimento'] ?? '');

            if ($id <= 0) {
                throw new Exception('Aluno inválido.');
            }

            if ($nome === '' || $email === '') {
                throw new Exception('Preencha nome e e-mail do aluno.');
            }

            $stmt = $conn->prepare("SELECT * FROM alunos WHERE id = ?");
            $stmt->execute([$id]);
            $alunoAtual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$alunoAtual) {
                throw new Exception('Aluno não encontrado.');
            }

            // Verifica se o novo e-mail já pertence a outro aluno
            $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe outro aluno cadastrado com este e-mail.');
            }

            // Tratamento do CPF
            if ($cpf !== '') {
                $stmt = $conn->prepare("SELECT id FROM alunos WHERE cpf = ? AND id != ?");
                $stmt->execute([$cpf, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe outro aluno cadastrado com este CPF.');
                }
            } else {
                $cpf = !empty($alunoAtual['cpf']) ? $alunoAtual['cpf'] : cpfUnicoTemp();
            }

            // Tratamento da data de nascimento
            $dataNascimentoValida = null;
            if ($nasc !== '') {
                if (pinNascimento($nasc) === '') {
                    throw new Exception('Data de nascimento inválida.');
                }
                $dataNascimentoValida = $nasc;
            } else {
                $dataNascimentoValida = $alunoAtual['data_nascimento'];
            }

            $stmt = $conn->prepare("UPDATE alunos SET nome = ?, email = ?, telefone = ?, cpf = ?, data_nascimento = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $telefone, $cpf, $dataNascimentoValida, $id]);
            $msg = 'Dados do aluno atualizados com sucesso.';
        }

        if ($acao === 'add_admin') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $nasc = trim($_POST['data_nascimento'] ?? '');
            $senhaCustom = trim($_POST['senha'] ?? '');

            if ($nome === '' || $email === '' || $nasc === '') {
                throw new Exception('Preencha nome, e-mail e data de nascimento do administrador.');
            }

            // Se a senha personalizada foi digitada, valida o tamanho mínimo
            if ($senhaCustom !== '') {
                if (strlen($senhaCustom) < 6) {
                    throw new Exception('A senha informada deve ter pelo menos 6 caracteres.');
                }
                $hash = password_hash($senhaCustom, PASSWORD_DEFAULT);
                $msg_senha = 'Senha digitada no cadastro.';
            } else {
                // Caso contrário, usa a data de nascimento como padrão
                $pin = pinNascimento($nasc);
                if ($pin === '') {
                    throw new Exception('Data de nascimento inválida.');
                }
                $hash = password_hash($pin, PASSWORD_DEFAULT);
                $msg_senha = 'Senha inicial: data de nascimento (somente números).';
            }

            $stmt = $conn->prepare("SELECT id FROM admins WHERE usuario = ? OR email = ?");
            $stmt->execute([$email, $email]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe um administrador com este e-mail/usuário.');
            }

            $stmt = $conn->prepare("INSERT INTO admins (usuario, senha, nome, email, telefone, cpf, data_nascimento) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $hash, $nome, $email, $telefone, $cpf, $nasc]);
            $msg = 'Administrador cadastrado com sucesso. ' . $msg_senha;
        }

        if ($acao === 'del_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT usuario, email FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $alvo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$alvo) {
                throw new Exception('Administrador não encontrado.');
            }
            if ($alvo['usuario'] === $admin_logado_usuario || $alvo['email'] === $admin_logado_usuario) {
                throw new Exception('Você não pode excluir o administrador que está logado.');
            }
            $total = (int) $conn->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            if ($total <= 1) {
                throw new Exception('Não é possível excluir o último administrador.');
            }
            $conn->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
            $msg = 'Administrador excluído.';
        }

        if ($acao === 'reset_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT data_nascimento FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Administrador não encontrado.');
            }
            $pin = pinNascimento($row['data_nascimento']);
            if ($pin === '') {
                throw new Exception('Este administrador não tem data de nascimento cadastrada.');
            }
            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE admins SET senha = ?, token_recuperacao = NULL WHERE id = ?")->execute([$hash, $id]);
            $msg = 'Senha do administrador redefinida para a data de nascimento (somente números).';
        }

        if ($acao === 'edit_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $nasc = trim($_POST['data_nascimento'] ?? '');

            if ($id <= 0) {
                throw new Exception('Administrador inválido.');
            }

            if ($nome === '' || $email === '') {
                throw new Exception('Preencha nome e e-mail do administrador.');
            }

            $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $adminAtual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$adminAtual) {
                throw new Exception('Administrador não encontrado.');
            }

            $stmt = $conn->prepare("SELECT id FROM admins WHERE (usuario = ? OR email = ?) AND id != ?");
            $stmt->execute([$email, $email, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe outro administrador com este e-mail/usuário.');
            }

            $dataNascimentoValida = null;
            if ($nasc !== '') {
                if (pinNascimento($nasc) === '') {
                    throw new Exception('Data de nascimento inválida.');
                }
                $dataNascimentoValida = $nasc;
            } else {
                $dataNascimentoValida = $adminAtual['data_nascimento'];
            }

            $stmt = $conn->prepare("UPDATE admins SET nome = ?, email = ?, usuario = ?, telefone = ?, cpf = ?, data_nascimento = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $email, $telefone, $cpf, $dataNascimentoValida, $id]);

            if ($adminAtual['usuario'] === $admin_logado_usuario || $adminAtual['email'] === $admin_logado_usuario) {
                $_SESSION['admin_usuario'] = $email;
                $admin_logado_usuario = $email;
            }

            $msg = 'Dados do administrador atualizados com sucesso.';
        }

        if ($acao === 'minha_senha') {
            $atual = $_POST['senha_atual'] ?? '';
            $nova = $_POST['nova_senha'] ?? '';
            $confirma = $_POST['confirma_senha'] ?? '';
            if (strlen($nova) < 6) {
                throw new Exception('A nova senha precisa ter pelo menos 6 caracteres.');
            }
            if ($nova !== $confirma) {
                throw new Exception('A confirmação da senha não confere.');
            }
            $stmt = $conn->prepare("SELECT * FROM admins WHERE usuario = ? OR email = ? LIMIT 1");
            $stmt->execute([$admin_logado_usuario, $admin_logado_usuario]);
            $eu = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$eu || !senhaConfere($atual, $eu['senha'] ?? '', $eu['data_nascimento'] ?? null)) {
                throw new Exception('Senha atual incorreta.');
            }
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE admins SET senha = ? WHERE id = ?")->execute([$hash, $eu['id']]);
            $msg = 'Sua senha foi alterada.';
        }
    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    $msg_tipo = 'danger';
}

$alunos = $conn->query("SELECT id, nome, email, telefone, cpf, data_nascimento FROM alunos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT id, usuario, nome, email, telefone, cpf, data_nascimento FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
if ($mes < 1 || $mes > 12) {
    $mes = (int) date('n');
}
if ($ano < 2000 || $ano > 2100) {
    $ano = (int) date('Y');
}

$sqlFatCursos = "
    SELECT COALESCE(SUM(c.preco), 0) AS total, COUNT(m.id) AS qtd
    FROM matriculas m
    JOIN cursos c ON c.id = m.curso_id
    WHERE LOWER(IFNULL(m.status_pagamento, '')) IN ('pago', 'aprovado', 'confirmado')
      AND MONTH(m.data_matricula) = :mes AND YEAR(m.data_matricula) = :ano
";
$stmtFatC = $conn->prepare($sqlFatCursos);
$stmtFatC->execute([':mes' => $mes, ':ano' => $ano]);
$fatCursos = $stmtFatC->fetch(PDO::FETCH_ASSOC);

$sqlFatEbooks = "
    SELECT COALESCE(SUM(e.preco), 0) AS total, COUNT(ce.id) AS qtd
    FROM compras_ebooks ce
    JOIN ebooks e ON e.id = ce.ebook_id
    WHERE LOWER(IFNULL(ce.status_pagamento, '')) IN ('pago', 'aprovado', 'confirmado')
      AND MONTH(COALESCE(ce.pago_em, ce.data_compra)) = :mes AND YEAR(COALESCE(ce.pago_em, ce.data_compra)) = :ano
";
$stmtFatE = $conn->prepare($sqlFatEbooks);
$stmtFatE->execute([':mes' => $mes, ':ano' => $ano]);
$fatEbooks = $stmtFatE->fetch(PDO::FETCH_ASSOC);

$totalMes = (float) $fatCursos['total'] + (float) $fatEbooks['total'];
$meses_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Engenharia Academy ERP</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0b1437;
            --sidebar-width: 250px;
            --primary-purple: #4318ff;
            --text-gray: #a3aed0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7fe;
            color: #2b3674;
            margin: 0;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-width: 100vh;
        }


        /* Sidebar Fixa */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 25px 15px;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 0 10px 25px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand h4 {
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            color: #fff;
        }

        .sidebar-brand span.erp {
            color: var(--primary-purple);
            font-weight: 700;
            display: block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0 0 0;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu a {
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu li.active a {
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-weight: 600;
            border-left: 4px solid var(--primary-purple);
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .link-ver-site {
            color: #ffb800 !important;
        }

        .link-sair {
            color: #ee5d50 !important;
        }

        /* Compensação para a área principal não ficar atrás da sidebar */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 14px rgba(0, 0, 0, 0.05);
            background: #fff;
            padding: 20px;
            margin-bottom: 24px;
        }

        .btn-primary-custom {
            background-color: #4318FF;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            color: #fff;
        }

        .btn-primary-custom:hover {
            background-color: #3311DB;
            color: #fff;
        }

        .table thead th {
            color: #a3aed0;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pago {
            background-color: #05CD99;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
        }

        footer,
        .footer {
            margin-left: 250px;
            /*Largura da sidebar*/
            width: calc(100% - 250px);
            /*Ocupa o restante da tela*/
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Barra Lateral (Sidebar) -->
        <aside class="sidebar">
            <div>
                <div class="sidebar-brand">
                    <h4>Engenharia Academy</h4>
                    <span class="erp">PAINEL ERP</span>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="admin.php" class="active">📊 Dashboard</a></li>
                    <li><a href="admin_cursos.php">📚 Cursos</a></li>
                    <li><a href="admin_ebooks.php">📖 E-books</a></li>
                    <li><a href="admin_alunos.php">👥 Alunos</a></li>
                    <li><a href="admin_duvidas.php">💬 Fórum de Dúvidas</a></li>
                    <li><a href="configuracoes.php">⚙️ Configurações</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="link-ver-site"><i class="bi bi-box-arrow-up-right"></i> Ver Site Público</a>
                <a href="../auth/logout.php" class="link-sair"><i class="bi bi-box-arrow-left"></i> Encerrar Sessão</a>
            </div>
        </aside>

        <!-- Conteúdo principal -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold m-0 text-dark">Configurações</h2>
                    <p class="text-muted small m-0">Administração de pessoas, senhas e relatórios.</p>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_tipo === 'danger' ? 'danger' : 'success'; ?> fw-semibold">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <div class="card-custom">
                <h5 class="fw-bold mb-1">Administradores / Secretarias</h5>
                <p class="text-muted small">Cadastre a equipe. Deixe a senha em branco para usar a data de nascimento
                    por padrão (ex: 19082000).</p>
                <form method="POST" class="row g-3 mb-4">
                    <input type="hidden" name="acao" value="add_admin">
                    <div class="col-md-4"><label class="form-label fw-semibold">Nome</label><input type="text"
                            name="nome" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">E-mail (login)</label><input
                            type="email" name="email" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Telefone</label><input type="text"
                            name="telefone" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">CPF</label><input type="text" name="cpf"
                            class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Data de nascimento</label><input
                            type="date" name="data_nascimento" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Senha (opcional)</label><input
                            type="password" name="senha" class="form-control" placeholder="Definir senha personalizada"
                            minlength="6"></div>
                    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary-custom w-100"
                            type="submit">+ Adicionar admin</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>CPF</th>
                                <th>Nascimento</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$admins): ?>
                                <tr>
                                    <td colspan="6" class="text-muted">Nenhum administrador.</td>
                                </tr>
                            <?php else:
                                foreach ($admins as $ad): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($ad['nome'] ?: $ad['usuario']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($ad['email'] ?: $ad['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($ad['telefone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($ad['cpf'] ?? ''); ?></td>
                                        <td><?php echo !empty($ad['data_nascimento']) ? date('d/m/Y', strtotime($ad['data_nascimento'])) : '—'; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarAdmin<?php echo (int) $ad['id']; ?>">✏️
                                                Editar</button>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Resetar senha para a data de nascimento (somente números)?');">
                                                <input type="hidden" name="acao" value="reset_admin">
                                                <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
                                                <button class="btn btn-sm btn-outline-primary fw-semibold" type="submit">Reset
                                                    senha</button>
                                            </form>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Excluir este administrador?');">
                                                <input type="hidden" name="acao" value="del_admin">
                                                <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Modal Editar Admin -->
                                    <div class="modal fade" id="modalEditarAdmin<?php echo (int) $ad['id']; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">✏️ Editar Administrador</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Fechar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="acao" value="edit_admin">
                                                        <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nome</label>
                                                            <input type="text" name="nome" class="form-control"
                                                                value="<?php echo htmlspecialchars($ad['nome'] ?? ''); ?>"
                                                                required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">E-mail (login)</label>
                                                            <input type="email" name="email" class="form-control"
                                                                value="<?php echo htmlspecialchars($ad['email'] ?: $ad['usuario']); ?>"
                                                                required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Telefone</label>
                                                            <input type="text" name="telefone" class="form-control"
                                                                value="<?php echo htmlspecialchars($ad['telefone'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">CPF</label>
                                                            <input type="text" name="cpf" class="form-control"
                                                                value="<?php echo htmlspecialchars($ad['cpf'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Data de nascimento</label>
                                                            <input type="date" name="data_nascimento" class="form-control"
                                                                value="<?php echo htmlspecialchars($ad['data_nascimento'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary-custom">Salvar
                                                            Alterações</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-custom">
                <h5 class="fw-bold mb-1">Alunos</h5>
                <p class="text-muted small">Inclusão e exclusão. O reset deixa a senha igual à data de nascimento
                    (somente números).</p>
                <form method="POST" class="row g-3 mb-4">
                    <input type="hidden" name="acao" value="add_aluno">
                    <div class="col-md-4"><label class="form-label fw-semibold">Nome</label><input type="text"
                            name="nome" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">E-mail</label><input type="email"
                            name="email" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Telefone</label><input type="text"
                            name="telefone" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">CPF</label><input type="text" name="cpf"
                            class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Data de nascimento</label><input
                            type="date" name="data_nascimento" class="form-control" required></div>
                    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary-custom w-100"
                            type="submit">+ Adicionar aluno</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>CPF</th>
                                <th>Nascimento</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$alunos): ?>
                                <tr>
                                    <td colspan="6" class="text-muted">Nenhum aluno cadastrado.</td>
                                </tr>
                            <?php else:
                                foreach ($alunos as $al): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($al['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($al['email']); ?></td>
                                        <td><?php echo htmlspecialchars($al['telefone'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($al['cpf'] ?? ''); ?></td>
                                        <td><?php echo !empty($al['data_nascimento']) ? date('d/m/Y', strtotime($al['data_nascimento'])) : '—'; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarAluno<?php echo (int) $al['id']; ?>">✏️
                                                Editar</button>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Resetar senha para a data de nascimento (somente números)?');">
                                                <input type="hidden" name="acao" value="reset_aluno">
                                                <input type="hidden" name="id" value="<?php echo (int) $al['id']; ?>">
                                                <button class="btn btn-sm btn-outline-primary fw-semibold" type="submit">Reset
                                                    senha</button>
                                            </form>
                                            <form method="POST" class="d-inline"
                                                onsubmit="return confirm('Excluir este aluno e os vínculos de matrícula/compra?');">
                                                <input type="hidden" name="acao" value="del_aluno">
                                                <input type="hidden" name="id" value="<?php echo (int) $al['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Modal Editar Aluno -->
                                    <div class="modal fade" id="modalEditarAluno<?php echo (int) $al['id']; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">✏️ Editar Aluno</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Fechar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="acao" value="edit_aluno">
                                                        <input type="hidden" name="id" value="<?php echo (int) $al['id']; ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nome Completo</label>
                                                            <input type="text" name="nome" class="form-control"
                                                                value="<?php echo htmlspecialchars($al['nome']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">E-mail</label>
                                                            <input type="email" name="email" class="form-control"
                                                                value="<?php echo htmlspecialchars($al['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Telefone</label>
                                                            <input type="text" name="telefone" class="form-control"
                                                                value="<?php echo htmlspecialchars($al['telefone'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">CPF</label>
                                                            <input type="text" name="cpf" class="form-control"
                                                                value="<?php echo htmlspecialchars($al['cpf'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Data de Nascimento</label>
                                                            <input type="date" name="data_nascimento" class="form-control"
                                                                value="<?php echo htmlspecialchars($al['data_nascimento'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary-custom">Salvar
                                                            Alterações</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card-custom h-100">
                        <h5 class="fw-bold">Relatório de alunos / cursos</h5>
                        <p class="text-muted small">Lista cada curso e os alunos matriculados nele.</p>
                        <a href="relatorio_cursos_alunos.php" target="_blank" class="btn btn-primary-custom">Abrir
                            relatório</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-custom h-100">
                        <h5 class="fw-bold">Relatório financeiro do mês</h5>
                        <p class="text-muted small mb-3">Cursos e e-books com pagamento confirmado.</p>
                        <form method="GET" class="row g-2 align-items-end mb-3">
                            <div class="col-5">
                                <label class="form-label small fw-semibold">Mês</label>
                                <select name="mes" class="form-select">
                                    <?php foreach ($meses_pt as $n => $nomeMes): ?>
                                        <option value="<?php echo $n; ?>" <?php echo $n === $mes ? 'selected' : ''; ?>>
                                            <?php echo $nomeMes; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Ano</label>
                                <input type="number" name="ano" class="form-control" value="<?php echo (int) $ano; ?>">
                            </div>
                            <div class="col-3">
                                <button class="btn btn-outline-primary w-100 fw-semibold" type="submit">Ver</button>
                            </div>
                        </form>
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Cursos
                                (<?php echo (int) $fatCursos['qtd']; ?>)</span><strong>R$
                                <?php echo number_format((float) $fatCursos['total'], 2, ',', '.'); ?></strong></div>
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">E-books
                                (<?php echo (int) $fatEbooks['qtd']; ?>)</span><strong>R$
                                <?php echo number_format((float) $fatEbooks['total'], 2, ',', '.'); ?></strong></div>
                        <div class="d-flex justify-content-between mt-2"><span class="fw-semibold">Total
                                <?php echo $meses_pt[$mes]; ?>/<?php echo $ano; ?></span><span class="badge-pago">R$
                                <?php echo number_format($totalMes, 2, ',', '.'); ?></span></div>
                        <a href="relatorio_financeiro_mes.php?mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>"
                            target="_blank" class="btn btn-primary-custom mt-3">Abrir relatório completo</a>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <h5 class="fw-bold mb-1">Alterar minha senha</h5>
                <p class="text-muted small">Depois de um reset, troque a senha temporária (data de nascimento) por uma
                    senha sua.</p>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="acao" value="minha_senha">
                    <div class="col-md-4"><label class="form-label fw-semibold">Senha atual</label><input
                            type="password" name="senha_atual" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nova senha</label><input type="password"
                            name="nova_senha" class="form-control" minlength="6" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Confirmar nova senha</label><input
                            type="password" name="confirma_senha" class="form-control" minlength="6" required></div>
                    <div class="col-12"><button class="btn btn-primary-custom" type="submit">Salvar nova senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>