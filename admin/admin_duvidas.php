<?php
/**
 * ============================================================================
 * ENGENHARIA ACADEMY - SISTEMA DE GESTÃO E-LEARNING & ERP
 * Arquivo: admin_duvidas.php
 * Finalidade: Painel de Atendimento e Moderação do Fórum de Dúvidas (Q&A).
 *             Permite que a equipe de instrutores e administradores visualize
 *             as perguntas enviadas pelos alunos nas aulas e envie respostas
 *             oficiais que aparecerão instantaneamente no ambiente do aluno.
 * ============================================================================
 */

session_start();

// CAMINHOS DE INCLUSÃO (PHP):
// Ajuste para garantir que a inclusão ocorra a partir da raiz ou pasta relativa
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/funcoes_config.php';

// 1. Verificação de Autenticação do Administrador
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

garantirTabelasTCC($conn);

$admin_id = $_SESSION['admin_id'] ?? 1;
$msg = '';
$msg_tipo = 'success';

// 2. Processamento do Formulário de Resposta (com CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'responder') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $msg = 'Erro de segurança: Token CSRF inválido.';
        $msg_tipo = 'danger';
    } else {
        $duvida_id = (int) ($_POST['duvida_id'] ?? 0);
        $resposta = trim($_POST['resposta'] ?? '');

        if ($duvida_id > 0 && !empty($resposta)) {
            $stmt = $conn->prepare("UPDATE duvidas_aulas 
                                    SET resposta = ?, respondido_por_admin_id = ?, status = 'respondida', data_resposta = NOW() 
                                    WHERE id = ?");
            $stmt->execute([$resposta, $admin_id, $duvida_id]);
            $msg = 'Resposta enviada com sucesso ao aluno!';
            $msg_tipo = 'success';
        } else {
            $msg = 'Por favor, preencha a resposta antes de enviar.';
            $msg_tipo = 'warning';
        }
    }
}

// 3. Filtros de Pesquisa
$filtro_status = $_GET['status'] ?? 'todos';
$whereClausula = "";
$params = [];

if ($filtro_status === 'pendente') {
    $whereClausula = "WHERE d.status = 'pendente'";
} elseif ($filtro_status === 'respondida') {
    $whereClausula = "WHERE d.status = 'respondida'";
}

// 4. Consulta das Dúvidas com Joins em Alunos, Cursos e Aulas
$sql = "SELECT d.*, a.nome AS aluno_nome, a.email AS aluno_email, c.titulo AS curso_titulo, au.titulo AS aula_titulo, adm.nome AS admin_nome
        FROM duvidas_aulas d
        JOIN alunos a ON a.id = d.aluno_id
        JOIN cursos c ON c.id = d.curso_id
        LEFT JOIN aulas au ON au.id = d.aula_id
        LEFT JOIN admins adm ON adm.id = d.respondido_por_admin_id
        $whereClausula
        ORDER BY d.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$duvidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores de Dúvidas
$totalPendentes = (int) $conn->query("SELECT COUNT(*) FROM duvidas_aulas WHERE status = 'pendente'")->fetchColumn();
$totalRespondidas = (int) $conn->query("SELECT COUNT(*) FROM duvidas_aulas WHERE status = 'respondida'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fórum de Dúvidas - Engenharia Academy ERP</title>
    <!-- Favicon corregido com caminho relativo absoluto para a raiz do site -->
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    
    <!-- Bootstrap 5 & Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            min-height: 100vh;
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

        /* Conteúdo Principal */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        .card-custom {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            border: none;
        }

        .badge-pendente {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
            font-weight: 600;
            border-radius: 20px;
            padding: 6px 12px;
        }

        .badge-respondida {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #d1fae5;
            font-weight: 600;
            border-radius: 20px;
            padding: 6px 12px;
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

        <!-- Barra Lateral (Sidebar) -->
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
                    <li><a href="admin_alunos.php">👥 Alunos</a></li>
                    <li class="active"><a href="admin_duvidas.php">💬 Fórum de Dúvidas</a></li>
                    <li><a href="admin_configuracoes.php">⚙️ Configurações</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="link-ver-site"><i class="bi bi-box-arrow-up-right"></i> Ver Site Público</a>
                <a href="../auth/logout.php" class="link-sair"><i class="bi bi-box-arrow-left"></i> Encerrar Sessão</a>
            </div>
        </aside>

        <!-- Área de Conteúdo -->
        <main class="main-content">

            <!-- Cabeçalho da Página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Fórum de Dúvidas & Atendimento</h2>
                    <p class="text-muted small mb-0">Responda e gerencie as dúvidas pedagógicas enviadas pelos alunos nas aulas.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="admin_duvidas.php?status=todos"
                        class="btn btn-sm btn-outline-secondary <?php echo $filtro_status === 'todos' ? 'active' : ''; ?>">Todas</a>
                    <a href="admin_duvidas.php?status=pendente"
                        class="btn btn-sm btn-outline-warning <?php echo $filtro_status === 'pendente' ? 'active' : ''; ?>">Pendentes
                        (<?php echo $totalPendentes; ?>)</a>
                    <a href="admin_duvidas.php?status=respondida"
                        class="btn btn-sm btn-outline-success <?php echo $filtro_status === 'respondida' ? 'active' : ''; ?>">Respondidas
                        (<?php echo $totalRespondidas; ?>)</a>
                </div>
            </div>

            <!-- Feedback de Ação -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?php echo $msg_tipo; ?> alert-dismissible fade show rounded-3 mb-4" role="alert">
                    <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Lista de Dúvidas dos Alunos -->
            <div class="card card-custom p-4">
                <?php if (!empty($duvidas)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Aluno</th>
                                    <th>Curso / Aula</th>
                                    <th>Pergunta</th>
                                    <th>Resposta Oficial</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($duvidas as $d): ?>
                                    <tr>
                                        <td>
                                            <?php if ($d['status'] === 'pendente'): ?>
                                                <span class="badge-pendente"><i class="bi bi-hourglass"></i> Pendente</span>
                                            <?php else: ?>
                                                <span class="badge-respondida"><i class="bi bi-check-circle"></i> Respondida</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($d['aluno_nome']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($d['aluno_email']); ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-dark small"><?php echo htmlspecialchars($d['curso_titulo']); ?></strong><br>
                                            <span class="text-muted small"><?php echo htmlspecialchars($d['aula_titulo'] ?? 'Módulo Principal'); ?></span>
                                        </td>
                                        <td style="max-width: 260px;">
                                            <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($d['pergunta'])); ?></p>
                                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo date('d/m/Y H:i', strtotime($d['data_pergunta'])); ?></span>
                                        </td>
                                        <td style="max-width: 260px;">
                                            <?php if (!empty($d['resposta'])): ?>
                                                <p class="mb-0 small text-success"><?php echo nl2br(htmlspecialchars($d['resposta'])); ?></p>
                                                <span class="text-muted" style="font-size: 0.75rem;">Por:
                                                    <?php echo htmlspecialchars($d['admin_nome'] ?? 'Admin'); ?> em
                                                    <?php echo date('d/m/Y H:i', strtotime($d['data_resposta'])); ?></span>
                                            <?php else: ?>
                                                <em class="text-muted small">Nenhuma resposta enviada ainda.</em>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-primary btn-sm rounded-3 fw-semibold"
                                                data-bs-toggle="modal" data-bs-target="#modalResponder_<?php echo $d['id']; ?>"
                                                style="background-color: #4318FF; border:none;">
                                                <i class="bi bi-reply-fill"></i>
                                                <?php echo empty($d['resposta']) ? 'Responder' : 'Editar'; ?>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal de Resposta para a Dúvida -->
                                    <div class="modal fade" id="modalResponder_<?php echo $d['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <form method="POST" action="admin_duvidas.php">
                                                    <?php echo campoCSRF(); ?>
                                                    <input type="hidden" name="acao" value="responder">
                                                    <input type="hidden" name="duvida_id" value="<?php echo $d['id']; ?>">

                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Responder Aluno</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <div class="modal-body py-4">
                                                        <div class="p-3 bg-light rounded-3 mb-3">
                                                            <strong class="text-dark small d-block mb-1">Pergunta de <?php echo htmlspecialchars($d['aluno_nome']); ?>:</strong>
                                                            <p class="mb-0 text-muted small"><?php echo nl2br(htmlspecialchars($d['pergunta'])); ?></p>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small">Sua Resposta Oficial / Explicação Técnica:</label>
                                                            <textarea name="resposta" rows="5" class="form-control"
                                                                placeholder="Escreva a orientação pedagógica para o aluno..."
                                                                required><?php echo htmlspecialchars($d['resposta'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary rounded-3 fw-bold" style="background-color: #4318FF; border:none;">
                                                            Salvar e Publicar Resposta
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-chat-square-text text-muted fs-1 mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark">Nenhuma dúvida encontrada</h5>
                        <p class="text-muted small">Não há registros de perguntas com os filtros selecionados.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Inclusão do rodapé PHP -->
    <?php 
    if (file_exists(__DIR__ . '/../includes/footer.php')) {
        include __DIR__ . '/../includes/footer.php'; 
    }
    ?>
</body>

</html>