<?php
// Desativa totalmente o cache HTTP no navegador
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Se não estiver logado como aluno, expulsa para o login do aluno
if (!isset($_SESSION['aluno_id'])) { // ou a variável de sessão que você usa para o aluno
    header("Location: login_aluno.php");
    exit;
}

require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

// Proteção da página: verifica se o aluno está logado
if (!isset($_SESSION['aluno_id'])) {
    header("Location: login_aluno.php");
    exit;
}

$aluno_id = $_SESSION['aluno_id'];

// Busca os dados do aluno
$stmt = $conn->prepare("SELECT nome FROM alunos WHERE id = ?");
$stmt->execute([$aluno_id]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);
$nome_aluno = $aluno['nome'] ?? 'Aluno';
$primeiro_nome = explode(' ', trim($nome_aluno))[0];

// Busca os Cursos do aluno
$stmtC = $conn->prepare("
    SELECT m.curso_id, m.status_pagamento, m.forma_pagamento, m.data_matricula, c.titulo, c.descricao 
    FROM matriculas m 
    JOIN cursos c ON m.curso_id = c.id 
    WHERE m.aluno_id = ?
");
$stmtC->execute([$aluno_id]);
$cursos_aluno = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// Busca os E-books do aluno (Ajustado para não pedir e.capa)
$stmtE = $conn->prepare("
    SELECT ce.ebook_id, ce.status_pagamento, ce.forma_pagamento, e.titulo 
    FROM compras_ebooks ce 
    JOIN ebooks e ON ce.ebook_id = e.id 
    WHERE ce.aluno_id = ?
");
$stmtE->execute([$aluno_id]);
$ebooks_aluno = $stmtE->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f4f7fe;
            --bg-dark: #0b1437;
            --primary-purple: #4318ff;
            --primary-hover: #3311db;
            --green-success: #05cd99;
            --yellow-warning: #ffb800;
            --text-main: #2b3674;
            --text-muted: #a3aed0;
            --danger-red: #ee5d50;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
        }

        .navbar-top {
            background-color: var(--bg-dark);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand-text {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.25rem;
            margin: 0;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-novos-cursos {
            background-color: var(--primary-purple);
            color: #ffffff;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-novos-cursos:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        .nav-greeting {
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .btn-sair-conta {
            border: 1px solid #3b4564;
            color: var(--danger-red);
            background: transparent;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-sair-conta:hover {
            border-color: var(--danger-red);
            background-color: rgba(238, 93, 80, 0.1);
            color: var(--danger-red);
        }

        .main-container {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-banner {
            background-color: var(--bg-dark);
            color: #ffffff;
            border-radius: 16px;
            padding: 35px 40px;
            margin-bottom: 40px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .header-banner h2 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .header-banner p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .section-title {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 25px;
            font-size: 1.4rem;
        }

        .card-item {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .badge-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 6px;
        }

        .badge-pendente {
            background-color: var(--yellow-warning);
            color: #2b3674;
        }

        .badge-liberado {
            background-color: var(--green-success);
            color: #fff;
        }

        .badge-ebook {
            background-color: #007bff;
            color: #fff;
        }

        .matricula-data {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .item-title {
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .item-desc {
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .progress-wrapper {
            margin-bottom: 20px;
        }

        .progress-bar-custom {
            height: 6px;
            border-radius: 10px;
            background-color: #e9ecef;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--green-success);
            border-radius: 10px;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .btn-acao {
            font-weight: 600;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn-bloqueado {
            background-color: #a3aed0;
            color: #ffffff;
            cursor: not-allowed;
            border: none;
        }

        .btn-acessar-curso {
            background-color: var(--primary-purple);
            color: #ffffff;
        }

        .btn-acessar-curso:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        .btn-baixar-ebook {
            background-color: var(--green-success);
            color: #ffffff;
        }

        .btn-baixar-ebook:hover {
            background-color: #04b082;
            color: #ffffff;
        }

        .text-feedback {
            font-size: 0.8rem;
            text-align: center;
            margin-top: 10px;
            font-weight: 600;
            color: var(--danger-red);
        }
    </style>

    <script>
        // Se o usuário clicar em "Voltar" no navegador e a página vier da memória cache (persisted)
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload(); // Força o navegador a consultar o PHP novamente
            }
        });
    </script>


</head>

<body>

    <nav class="navbar-top">
        <h1 class="navbar-brand-text">Engenharia Academy</h1>
        <div class="nav-actions">
            <a href="../index.php" class="btn-novos-cursos">+ Novos Cursos e E-books</a>
            <span class="nav-greeting">Olá, <?php echo htmlspecialchars($primeiro_nome); ?></span>
            <a href="../auth/logout_aluno.php" class="btn-sair-conta">Sair da Conta</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="header-banner">
            <h2>Bem-vindo(a), <?php echo htmlspecialchars($nome_aluno); ?>! 👋</h2>
            <p>Continue seus estudos de onde parou e baixe seus materiais.</p>
        </div>

        <!-- SESSÃO DE CURSOS -->
        <h3 class="section-title">Meus Cursos Adquiridos</h3>
        <div class="row">
            <?php if (empty($cursos_aluno)): ?>
                <div class="col-12">
                    <p class="text-muted">Você ainda não adquiriu nenhum curso.</p>
                </div>
            <?php else: ?>
                <?php foreach ($cursos_aluno as $curso):
                    $status = strtolower($curso['status_pagamento'] ?? 'pendente');
                    $is_liberado = ($status === 'confirmado' || $status === 'pago' || $status === 'aprovado');
                    $data_matricula = !empty($curso['data_matricula']) ? date('d/m/Y', strtotime($curso['data_matricula'])) : date('d/m/Y');
                    $progresso = $is_liberado ? obterProgressoCurso($conn, $aluno_id, $curso['curso_id']) : 0;
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-item">
                            <div class="card-header-flex">
                                <?php if ($is_liberado): ?>
                                    <?php if ($progresso === 100): ?>
                                        <span class="badge-status badge-liberado">Concluído (100%)</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-liberado">Acesso Liberado</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge-status badge-pendente">Pagamento Pendente</span>
                                <?php endif; ?>
                                <span class="matricula-data">Matrícula: <?php echo $data_matricula; ?></span>
                            </div>

                            <h4 class="item-title"><?php echo htmlspecialchars($curso['titulo']); ?></h4>
                            <p class="item-desc">
                                <?php echo htmlspecialchars($curso['descricao'] ?? 'Acesse o conteúdo das aulas.'); ?></p>

                            <div class="progress-wrapper">
                                <div class="progress-bar-custom">
                                    <div class="progress-fill" style="width: <?php echo $progresso; ?>%;"></div>
                                </div>
                                <div class="progress-text">
                                    <span>Progresso</span>
                                    <span><?php echo $progresso; ?>% concluído</span>
                                </div>
                            </div>

                            <?php if ($is_liberado): ?>
                                <?php if ($progresso === 100): ?>
                                    <div class="d-flex gap-2">
                                        <a href="player_aulas.php?id=<?php echo $curso['curso_id']; ?>"
                                            class="btn-acao btn-acessar-curso w-50">▶ Acessar</a>
                                        <a href="certificado.php?curso_id=<?php echo $curso['curso_id']; ?>" target="_blank"
                                            class="btn-acao btn-baixar-ebook w-50">🎓 Certificado</a>
                                    </div>
                                <?php else: ?>
                                    <a href="player_aulas.php?id=<?php echo $curso['curso_id']; ?>" class="btn-acao btn-acessar-curso">▶
                                        Assistir Aulas</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn-acao btn-bloqueado" disabled>🔒 Bloqueado (Pendente)</button>
                                <div class="text-feedback">Aguardando confirmação do pagamento.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- SESSÃO DE E-BOOKS -->
        <h3 class="section-title mt-4">Meus E-books e Materiais</h3>
        <div class="row pb-5">
            <?php if (empty($ebooks_aluno)): ?>
                <div class="col-12">
                    <p class="text-muted">Você ainda não adquiriu nenhum e-book.</p>
                </div>
            <?php else: ?>
                <?php foreach ($ebooks_aluno as $ebook):
                    $status = strtolower($ebook['status_pagamento'] ?? 'pendente');
                    $is_liberado = ($status === 'confirmado' || $status === 'pago' || $status === 'aprovado');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-item">
                            <div class="card-header-flex">
                                <span class="badge-status badge-ebook">E-book Comprado</span>
                            </div>

                            <h4 class="item-title"><?php echo htmlspecialchars($ebook['titulo']); ?></h4>
                            <p class="item-desc">Material complementar em formato digital.</p>

                            <?php if ($is_liberado): ?>
                                <a href="download_ebook.php?id=<?php echo $ebook['ebook_id']; ?>"
                                    class="btn-acao btn-baixar-ebook">⬇ Baixar Material</a>
                            <?php else: ?>
                                <button class="btn-acao btn-bloqueado" disabled>🔒 Bloqueado (Pendente)</button>
                                <div class="text-feedback">Aguardando confirmação do pagamento.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>