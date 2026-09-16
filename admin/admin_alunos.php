<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// =====================================================================
// PROCESSAMENTO DO FORMULÁRIO DE PAGAMENTO (MODAL)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pagamento'])) {
    
    $aluno_id = (int) $_POST['aluno_id'];
    $linhas_afetadas = 0;

    // 1. Atualiza status das matrículas de CURSOS (usando o ID da matrícula)
    if (!empty($_POST['matriculas_pagas']) && is_array($_POST['matriculas_pagas'])) {
        $stmtUpdateCurso = $conn->prepare("UPDATE matriculas SET status_pagamento = 'pago' WHERE id = ?");
        foreach ($_POST['matriculas_pagas'] as $matricula_id) {
            $stmtUpdateCurso->execute([(int)$matricula_id]);
            $linhas_afetadas += $stmtUpdateCurso->rowCount();
        }
    }

    // 2. Atualiza status das compras de E-BOOKS (usando o ID da compra)
    if (!empty($_POST['ebooks_pagos']) && is_array($_POST['ebooks_pagos'])) {
        $stmtUpdateEbook = $conn->prepare("UPDATE compras_ebooks SET status_pagamento = 'pago' WHERE id = ?");
        foreach ($_POST['ebooks_pagos'] as $compra_id) {
            $stmtUpdateEbook->execute([(int)$compra_id]);
            $linhas_afetadas += $stmtUpdateEbook->rowCount();
        }
    }

    if ($linhas_afetadas > 0) {
        header("Location: admin_alunos.php?msg=aprovado");
    } else {
        header("Location: admin_alunos.php?msg=nenhuma_alteracao");
    }
    exit;
}

// =====================================================================
// CONSULTA DOS ALUNOS
// Considera pendente apenas o que NÃO estiver pago/aprovado/confirmado
// =====================================================================
$sql = "
    SELECT 
        a.id, 
        a.nome, 
        a.email, 
        a.telefone,
        (
            (SELECT COUNT(*) FROM matriculas m WHERE m.aluno_id = a.id AND (m.status_pagamento IS NULL OR LOWER(TRIM(m.status_pagamento)) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado'))) +
            (SELECT COUNT(*) FROM compras_ebooks ce WHERE ce.aluno_id = a.id AND (ce.status_pagamento IS NULL OR LOWER(TRIM(ce.status_pagamento)) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado')))
        ) AS total_pendencias
    FROM alunos a
    ORDER BY a.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Queries preparadas para listar cursos e e-books por aluno
$stmtC = $conn->prepare("SELECT m.id AS matricula_id, m.curso_id, m.status_pagamento, m.forma_pagamento, c.titulo FROM matriculas m JOIN cursos c ON m.curso_id = c.id WHERE m.aluno_id = ?");
$stmtE = $conn->prepare("SELECT ce.id AS compra_id, ce.ebook_id, ce.status_pagamento, ce.forma_pagamento, e.titulo FROM compras_ebooks ce JOIN ebooks e ON ce.ebook_id = e.id WHERE ce.aluno_id = ?");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Alunos - Engenharia Academy ERP</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0b1437;
            --sidebar-width: 250px;
            --primary-purple: #4318ff;
            --primary-hover: #3311cc;
            --text-gray: #a3aed0;
            --badge-green: #05cd99;
            --badge-orange: #ffb800;
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
            min-height: 100vh;
        }

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

        .link-ver-site { color: #ffb800 !important; }
        .link-sair { color: #ee5d50 !important; }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        .page-title {
            font-weight: 700;
            color: #1b2559;
            font-size: 1.75rem;
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .btn-imprimir-geral {
            background-color: #ffffff;
            color: #0066ff;
            border: 1px solid #0066ff;
            font-weight: 600;
            border-radius: 12px;
            padding: 8px 18px;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-novo-aluno {
            background-color: var(--primary-purple);
            color: #ffffff;
            border: none;
            font-weight: 600;
            border-radius: 12px;
            padding: 8px 22px;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .card-tabela {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: none;
        }

        .table thead th {
            color: var(--text-gray);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
        }

        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #f4f7fe;
        }

        .badge-em-dia {
            background-color: var(--badge-green);
            color: #fff;
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.78rem;
            display: inline-block;
        }

        .badge-debito {
            background-color: var(--badge-orange);
            color: #fff;
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.78rem;
            display: inline-block;
        }

        .btn-table-detalhes {
            border: 1px solid #60a5fa;
            color: #2563eb;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-table-confirmar {
            background-color: #05cd99;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-table-relatorio {
            background-color: #ffffff;
            color: #2b3674;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-table-excluir {
            border: 1px solid #fca5a5;
            color: #ef4444;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
        }

        footer,
        .footer {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <aside class="sidebar">
            <div>
                <div class="sidebar-brand">
                    <h4>Engenharia Academy</h4>
                    <span class="erp">PAINEL ERP</span>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="admin.php">📊 Dashboard</a></li>
                    <li><a href="admin_cursos.php">📚 Cursos</a></li>
                    <li><a href="admin_ebooks.php">📖 E-books</a></li>
                    <li class="active"><a href="admin_alunos.php">👥 Alunos</a></li>
                    <li><a href="admin_duvidas.php">💬 Fórum de Dúvidas</a></li>
                    <li><a href="admin_configuracoes.php">⚙️ Configurações</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="link-ver-site"><i class="bi bi-box-arrow-up-right"></i> Ver Site Público</a>
                <a href="../auth/logout.php" class="link-sair"><i class="bi bi-box-arrow-left"></i> Encerrar Sessão</a>
            </div>
        </aside>

        <main class="main-content">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'aprovado'): ?>
                <div class="alert alert-success alert-dismissible fade show fw-semibold" role="alert">
                    ✅ Pagamento(s) confirmado(s) com sucesso no banco de dados!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'nenhuma_alteracao'): ?>
                <div class="alert alert-warning alert-dismissible fade show fw-semibold" role="alert">
                    ⚠️ Selecione ao menos um item marcado para confirmar a alteração.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="page-title">Gestão de Alunos</h2>
                    <p class="page-subtitle">Gerencie a base de alunos, status de pagamentos e relatórios.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="cadastrar_aluno.php" class="btn-novo-aluno">+ Novo Aluno</a>
                    <a href="relatorio_alunos_geral.php" target="_blank" class="btn-imprimir-geral">🖨️ Imprimir Relatório</a>
                </div>
            </div>

            <div class="card-tabela">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NOME DO ALUNO</th>
                                <th>E-MAIL</th>
                                <th>TELEFONE</th>
                                <th>STATUS FINANCEIRO</th>
                                <th></th>
                                <th>PAGAMENTO</th>
                                <th class="text-center">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alunos)): ?>
                                <?php foreach ($alunos as $aluno):
                                    $aluno_id = $aluno['id'];

                                    $stmtC->execute([$aluno_id]);
                                    $cursos_aluno = $stmtC->fetchAll(PDO::FETCH_ASSOC);

                                    $stmtE->execute([$aluno_id]);
                                    $ebooks_aluno = $stmtE->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                    <tr>
                                        <td class="fw-bold text-secondary">#<?php echo $aluno['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($aluno['nome']); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($aluno['email']); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($aluno['telefone'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($aluno['total_pendencias'] > 0): ?>
                                                <span class="badge-debito">Débito Pendente (<?php echo $aluno['total_pendencias']; ?>)</span>
                                            <?php else: ?>
                                                <span class="badge-em-dia">Em dia</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <button type="button" class="btn-table-detalhes" data-bs-toggle="modal" data-bs-target="#modalDetalhes<?php echo $aluno_id; ?>">
                                                👁️ Detalhes
                                            </button>
                                        </td>

                                        <td>
                                            <?php if ($aluno['total_pendencias'] > 0): ?>
                                                <button type="button" class="btn-table-confirmar" data-bs-toggle="modal" data-bs-target="#modalConfirmar<?php echo $aluno_id; ?>">
                                                    ✓ Confirmar
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted ps-3">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="relatorio_aluno_individual.php?id=<?php echo $aluno['id']; ?>" target="_blank" class="btn-table-relatorio">📄 Relatório</a>
                                                <a href="excluir_aluno.php?id=<?php echo $aluno['id']; ?>" class="btn-table-excluir" onclick="return confirm('Excluir aluno?');">🗑️ Excluir</a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL DETALHES -->
                                    <div class="modal fade" id="modalDetalhes<?php echo $aluno_id; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Detalhes Financeiros</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <h6 class="fw-bold text-primary mb-3">🧑‍🎓 <?php echo htmlspecialchars($aluno['nome']); ?></h6>

                                                    <h6 class="fw-bold border-bottom pb-2">📚 CURSOS ADQUIRIDOS</h6>
                                                    <?php if (empty($cursos_aluno)): ?>
                                                        <p class="text-muted small">Nenhum curso matriculado.</p>
                                                    <?php else: ?>
                                                        <ul class="list-unstyled mb-4">
                                                            <?php foreach ($cursos_aluno as $c):
                                                                $is_pago_c = in_array(strtolower(trim($c['status_pagamento'] ?? '')), ['pago', 'aprovado', 'confirmado'], true);
                                                            ?>
                                                                <li class="d-flex justify-content-between align-items-center mb-2 small">
                                                                    <span><?php echo htmlspecialchars($c['titulo']); ?></span>
                                                                    <?php if ($is_pago_c): ?>
                                                                        <span class="badge bg-success">Em dia</span>
                                                                    <?php else: ?>
                                                                        <a href="aprovar_matricula.php?id=<?php echo $c['matricula_id']; ?>&tipo=curso&ref=admin_alunos.php" 
                                                                           class="btn btn-warning btn-sm fw-bold"
                                                                           onclick="return confirm('Aprovar este curso para o aluno?')">
                                                                            ⏳ Aprovar
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>

                                                    <h6 class="fw-bold border-bottom pb-2">📖 E-BOOKS ADQUIRIDOS</h6>
                                                    <?php if (empty($ebooks_aluno)): ?>
                                                        <p class="text-muted small">Nenhum e-book comprado.</p>
                                                    <?php else: ?>
                                                        <ul class="list-unstyled mb-2">
                                                            <?php foreach ($ebooks_aluno as $e):
                                                                $is_pago_e = in_array(strtolower(trim($e['status_pagamento'] ?? '')), ['pago', 'aprovado', 'confirmado'], true);
                                                            ?>
                                                                <li class="d-flex justify-content-between align-items-center mb-2 small">
                                                                    <span><?php echo htmlspecialchars($e['titulo']); ?></span>
                                                                    <?php if ($is_pago_e): ?>
                                                                        <span class="badge bg-success">Em dia</span>
                                                                    <?php else: ?>
                                                                        <a href="aprovar_matricula.php?id=<?php echo $e['compra_id']; ?>&tipo=ebook&ref=admin_alunos.php" 
                                                                           class="btn btn-warning btn-sm fw-bold"
                                                                           onclick="return confirm('Aprovar este e-book para o aluno?')">
                                                                            ⏳ Aprovar
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL CONFIRMAR LOTE -->
                                    <?php if ($aluno['total_pendencias'] > 0): ?>
                                        <div class="modal fade" id="modalConfirmar<?php echo $aluno_id; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-light">
                                                        <h5 class="modal-title fw-bold">Confirmar Pagamentos</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form action="admin_alunos.php" method="POST">
                                                        <input type="hidden" name="aluno_id" value="<?php echo $aluno_id; ?>">
                                                        <input type="hidden" name="confirmar_pagamento" value="1">

                                                        <div class="modal-body">
                                                            <p class="text-muted small mb-3">
                                                                Marque os itens pendentes que foram quitados por <strong><?php echo htmlspecialchars($aluno['nome']); ?></strong>:
                                                            </p>

                                                            <!-- Cursos Pendentes -->
                                                            <?php foreach ($cursos_aluno as $c):
                                                                $is_pago_c = in_array(strtolower(trim($c['status_pagamento'] ?? '')), ['pago', 'aprovado', 'confirmado'], true);
                                                                if (!$is_pago_c):
                                                            ?>
                                                                    <div class="form-check mb-2">
                                                                        <input class="form-check-input" type="checkbox" name="matriculas_pagas[]" value="<?php echo $c['matricula_id']; ?>" id="mat_<?php echo $c['matricula_id']; ?>" checked>
                                                                        <label class="form-check-label" for="mat_<?php echo $c['matricula_id']; ?>">
                                                                            📚 <?php echo htmlspecialchars($c['titulo']); ?> (<?php echo htmlspecialchars($c['forma_pagamento'] ?? 'N/I'); ?>)
                                                                        </label>
                                                                    </div>
                                                            <?php endif; endforeach; ?>

                                                            <!-- E-books Pendentes -->
                                                            <?php foreach ($ebooks_aluno as $e):
                                                                $is_pago_e = in_array(strtolower(trim($e['status_pagamento'] ?? '')), ['pago', 'aprovado', 'confirmado'], true);
                                                                if (!$is_pago_e):
                                                            ?>
                                                                    <div class="form-check mb-2">
                                                                        <input class="form-check-input" type="checkbox" name="ebooks_pagos[]" value="<?php echo $e['compra_id']; ?>" id="ebook_<?php echo $e['compra_id']; ?>" checked>
                                                                        <label class="form-check-label" for="ebook_<?php echo $e['compra_id']; ?>">
                                                                            📖 <?php echo htmlspecialchars($e['titulo']); ?> (<?php echo htmlspecialchars($e['forma_pagamento'] ?? 'N/I'); ?>)
                                                                        </label>
                                                                    </div>
                                                            <?php endif; endforeach; ?>

                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-success fw-bold">✓ Salvar Confirmações</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Nenhum aluno cadastrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php include '../includes/footer.php'; ?>
</body>

</html>