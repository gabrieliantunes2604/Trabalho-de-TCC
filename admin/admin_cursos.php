<?php
session_start();
require '../includes/conexao.php';

// Verifica se está logado
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $video_url = $_POST['video_url'];

    // Captura o tipo de aula
    $tipo_aula = $_POST['tipo_aula'] ?? 'video';

    // Tratamento das checkbox de vitrine
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $promocao = isset($_POST['promocao']) ? 1 : 0;

    // Tratamento de Valores Corrigido (Remove ponto de milhar e troca vírgula por ponto)
    $preco = !empty($_POST['preco']) ? str_replace(',', '.', str_replace('.', '', $_POST['preco'])) : 0;
    $preco_antigo = !empty($_POST['preco_antigo']) ? str_replace(',', '.', str_replace('.', '', $_POST['preco_antigo'])) : null;

    $id = !empty($_POST['id']) ? $_POST['id'] : null;

    // Se estiver editando, busca os dados atuais para poder apagar os arquivos antigos se forem substituídos
    $curso_atual = null;
    if ($id) {
        $stmt_atual = $conn->prepare("SELECT imagem, arquivo_pdf, arquivo_planilha FROM cursos WHERE id = :id");
        $stmt_atual->bindParam(':id', $id);
        $stmt_atual->execute();
        $curso_atual = $stmt_atual->fetch(PDO::FETCH_ASSOC);
    }

    // Upload da IMAGEM
    $caminho_imagem = "";
    $nova_img_enviada = false;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $pasta_img = "../uploads/";
        if (!is_dir($pasta_img))
            mkdir($pasta_img, 0777, true);
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $novo_nome_img = "curso_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $pasta_img . $novo_nome_img)) {
            $caminho_imagem = $pasta_img . $novo_nome_img;
            $nova_img_enviada = true;

            // Remove a imagem antiga se existir ao editar
            if ($curso_atual && !empty($curso_atual['imagem']) && file_exists($curso_atual['imagem'])) {
                unlink($curso_atual['imagem']);
            }
        }
    }

    // Upload do PDF (Material de Apoio)
    $caminho_pdf = "";
    $novo_pdf_enviado = false;
    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $pasta_pdf = "../uploads/pdfs/";
        if (!is_dir($pasta_pdf))
            mkdir($pasta_pdf, 0777, true);
        $ext_pdf = strtolower(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_EXTENSION));

        if ($ext_pdf === 'pdf') { // Garante que é apenas PDF
            $novo_nome_pdf = "material_" . uniqid() . ".pdf";
            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $pasta_pdf . $novo_nome_pdf)) {
                $caminho_pdf = $pasta_pdf . $novo_nome_pdf;
                $novo_pdf_enviado = true;

                // Remove o PDF antigo se existir ao editar
                if ($curso_atual && !empty($curso_atual['arquivo_pdf']) && file_exists($curso_atual['arquivo_pdf'])) {
                    unlink($curso_atual['arquivo_pdf']);
                }
            }
        }
    }

    // Upload da Planilha (Material de Apoio)
    $caminho_planilha = "";
    $nova_planilha_enviada = false;
    if (isset($_FILES['arquivo_planilha']) && $_FILES['arquivo_planilha']['error'] === UPLOAD_ERR_OK) {
        $pasta_planilha = "../uploads/planilhas/";
        if (!is_dir($pasta_planilha))
            mkdir($pasta_planilha, 0777, true);
        $ext_planilha = strtolower(pathinfo($_FILES['arquivo_planilha']['name'], PATHINFO_EXTENSION));

        // Verifica se é excel
        if (in_array($ext_planilha, ['xls', 'xlsx'])) {
            $novo_nome_planilha = "planilha_" . uniqid() . "." . $ext_planilha;
            if (move_uploaded_file($_FILES['arquivo_planilha']['tmp_name'], $pasta_planilha . $novo_nome_planilha)) {
                $caminho_planilha = $pasta_planilha . $novo_nome_planilha;
                $nova_planilha_enviada = true;

                // Remove a planilha antiga se existir ao editar
                if ($curso_atual && !empty($curso_atual['arquivo_planilha']) && file_exists($curso_atual['arquivo_planilha'])) {
                    unlink($curso_atual['arquivo_planilha']);
                }
            }
        }
    }

    // Operação no Banco (Insert ou Update)
    if ($id) {
        // Monta a query dinâmica para UPDATE
        $sql = "UPDATE cursos SET titulo = :titulo, descricao = :descricao, preco = :preco, preco_antigo = :preco_antigo, destaque = :destaque, promocao = :promocao, video_url = :video_url, tipo_aula = :tipo_aula";

        if ($nova_img_enviada)
            $sql .= ", imagem = :imagem";
        if ($novo_pdf_enviado)
            $sql .= ", arquivo_pdf = :arquivo_pdf";
        if ($nova_planilha_enviada)
            $sql .= ", arquivo_planilha = :arquivo_planilha";

        $sql .= " WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        if ($nova_img_enviada)
            $stmt->bindParam(':imagem', $caminho_imagem);
        if ($novo_pdf_enviado)
            $stmt->bindParam(':arquivo_pdf', $caminho_pdf);
        if ($nova_planilha_enviada)
            $stmt->bindParam(':arquivo_planilha', $caminho_planilha);

    } else {
        // INSERT
        $sql = "INSERT INTO cursos (titulo, descricao, preco, preco_antigo, destaque, promocao, imagem, video_url, arquivo_pdf, tipo_aula, arquivo_planilha) 
                VALUES (:titulo, :descricao, :preco, :preco_antigo, :destaque, :promocao, :imagem, :video_url, :arquivo_pdf, :tipo_aula, :arquivo_planilha)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':imagem', $caminho_imagem);
        $stmt->bindParam(':arquivo_pdf', $caminho_pdf);
        $stmt->bindParam(':arquivo_planilha', $caminho_planilha);
    }

    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':preco_antigo', $preco_antigo);
    $stmt->bindParam(':destaque', $destaque);
    $stmt->bindParam(':promocao', $promocao);
    $stmt->bindParam(':video_url', $video_url);
    $stmt->bindParam(':tipo_aula', $tipo_aula);
    $stmt->execute();

    header("Location: admin_cursos.php");
    exit;
}

// Lógica de exclusão com remoção de arquivos
if (isset($_GET['deletar'])) {
    $id = $_GET['deletar'];

    // Busca os caminhos dos arquivos antes de excluir a linha do banco
    $stmt = $conn->prepare("SELECT imagem, arquivo_pdf, arquivo_planilha FROM cursos WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($curso) {
        if (!empty($curso['imagem']) && file_exists($curso['imagem'])) {
            unlink($curso['imagem']);
        }
        if (!empty($curso['arquivo_pdf']) && file_exists($curso['arquivo_pdf'])) {
            unlink($curso['arquivo_pdf']);
        }
        if (!empty($curso['arquivo_planilha']) && file_exists($curso['arquivo_planilha'])) {
            unlink($curso['arquivo_planilha']);
        }

        $stmt = $conn->prepare("DELETE FROM cursos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    header("Location: admin_cursos.php");
    exit;
}

// Busca todos os cursos para listar na tabela
$cursos = $conn->query("SELECT * FROM cursos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Busca os dados de um curso específico se clicar em Editar
$curso_edit = null;
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $curso_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Cursos</title>
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

        /* Área Principal */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #fff;
            margin-bottom: 24px;
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
                    <li class="active"><a href="admin_cursos.php">📚 Cursos</a></li>
                    <li><a href="admin_ebooks.php">📖 E-books</a></li>
                    <li><a href="admin_alunos.php">👥 Alunos</a></li>
                    <li><a href="admin_duvidas.php">💬 Fórum de Dúvidas</a></li>
                    <li><a href="admin_configuracoes.php">⚙️ Configurações</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="link-ver-site"><i class="bi bi-box-arrow-up-right"></i> Ver Site Público</a>
                <a href="../auth/logout.php" class="link-sair"><i class="bi bi-box-arrow-left"></i> Encerrar Sessão</a>
            </div>
        </aside>

        <!-- Conteúdo Principal -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold m-0 text-dark">Gestão de Cursos</h2>
                    <p class="text-muted small m-0">Gerencie todos os cursos da plataforma.</p>
                </div>
                <a href="admin.php" class="btn btn-outline-secondary bg-white shadow-sm fw-semibold">⬅ Voltar ao Dashboard</a>
            </div>

            <!-- Formulário -->
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-3"><?php echo $curso_edit ? 'Editar Curso' : 'Cadastrar Novo Curso'; ?></h5>
                <form action="admin_cursos.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $curso_edit ? $curso_edit['id'] : ''; ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Título do Curso</label>
                            <input type="text" name="titulo" class="form-control" required
                                value="<?php echo $curso_edit ? htmlspecialchars($curso_edit['titulo']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo de Conteúdo</label>
                            <select name="tipo_aula" class="form-select" required>
                                <option value="video" <?php echo ($curso_edit && $curso_edit['tipo_aula'] == 'video') ? 'selected' : ''; ?>>Vídeo Aula</option>
                                <option value="exercicio" <?php echo ($curso_edit && $curso_edit['tipo_aula'] == 'exercicio') ? 'selected' : ''; ?>>Exercício de Fixação / Prática</option>
                                <option value="leitura" <?php echo ($curso_edit && $curso_edit['tipo_aula'] == 'leitura') ? 'selected' : ''; ?>>Material de Leitura</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Preço Antigo (Opcional)</label>
                            <input type="text" name="preco_antigo" class="form-control monetario"
                                placeholder="Ex: 97,90"
                                value="<?php echo ($curso_edit && !empty($curso_edit['preco_antigo'])) ? number_format($curso_edit['preco_antigo'], 2, ',', '.') : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Preço Atual</label>
                            <input type="text" name="preco" class="form-control monetario text-success fw-bold"
                                placeholder="Ex: 65,99" required
                                value="<?php echo $curso_edit ? number_format($curso_edit['preco'], 2, ',', '.') : ''; ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Descrição do Curso</label>
                            <textarea name="descricao" class="form-control" rows="4"
                                required><?php echo $curso_edit ? htmlspecialchars($curso_edit['descricao']) : ''; ?></textarea>
                        </div>

                        <!-- Campos de Mídia -->
                        <div class="col-12 mt-4 mb-2">
                            <h6 class="fw-bold border-bottom pb-2">Conteúdos & Arquivos Base</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-primary">🎥 Link do Vídeo (YouTube)</label>
                            <input type="url" name="video_url" class="form-control"
                                placeholder="https://www.youtube.com/watch?v=..."
                                value="<?php echo $curso_edit ? htmlspecialchars($curso_edit['video_url']) : ''; ?>">
                            <small class="text-muted d-block mt-1">Deixe vazio se for apenas exercício/leitura.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-danger">📥 Apostila (PDF)</label>
                            <?php if ($curso_edit && !empty($curso_edit['arquivo_pdf'])): ?>
                                <div class="mb-1"><span class="badge bg-success">Material atual já enviado</span></div>
                            <?php endif; ?>
                            <input type="file" name="arquivo_pdf" class="form-control" accept="application/pdf">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-success">📊 Planilha Base (Excel)</label>
                            <?php if ($curso_edit && !empty($curso_edit['arquivo_planilha'])): ?>
                                <div class="mb-1"><span class="badge bg-success">Planilha atual já enviada</span></div>
                            <?php endif; ?>
                            <input type="file" name="arquivo_planilha" class="form-control" accept=".xls,.xlsx">
                        </div>

                        <div class="col-12 border-top pt-3 mt-4">
                            <label class="form-label fw-semibold text-dark">Capa do Curso (Imagem)</label>
                            <?php if ($curso_edit && !empty($curso_edit['imagem'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($curso_edit['imagem']); ?>" alt="Capa Atual" style="height: 60px; border-radius: 6px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="imagem" class="form-control" accept="image/*">
                        </div>

                        <!-- Vitrine -->
                        <div class="col-12 mt-3">
                            <label class="form-label fw-semibold d-block">Opções de Vitrine</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="destaque" id="destaque" <?php echo ($curso_edit && $curso_edit['destaque'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="destaque">🌟 Marcar como Destaque</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="promocao" id="promocao" <?php echo ($curso_edit && $curso_edit['promocao'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-danger fw-semibold" for="promocao">🔥 Colocar em Promoção</label>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary px-4 fw-bold"
                                style="background-color: #4318FF; border:none;">
                                <?php echo $curso_edit ? 'Atualizar Curso' : 'Salvar Curso'; ?>
                            </button>
                            <?php if ($curso_edit): ?>
                                <a href="admin_cursos.php" class="btn btn-outline-secondary ms-2">Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabela de Cursos -->
            <div class="card card-custom p-4 mt-4">
                <h5 class="fw-bold mb-4">Cursos Cadastrados</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small" style="background-color: #f8f9fa;">
                            <tr>
                                <th>ID</th>
                                <th>CAPA</th>
                                <th>TÍTULO</th>
                                <th>PREÇO</th>
                                <th>MÍDIA</th>
                                <th class="text-end">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cursos)): ?>
                                <?php foreach ($cursos as $c): ?>
                                    <tr>
                                        <td class="text-muted fw-bold">#<?php echo $c['id']; ?></td>
                                        <td>
                                            <?php if (!empty($c['imagem'])): ?>
                                                <img src="<?php echo htmlspecialchars($c['imagem']); ?>" alt="Capa"
                                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background-color: #e9ecef; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d;">
                                                    Sem Capa
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($c['titulo']); ?></div>
                                            <div class="small mt-1">
                                                <?php if ($c['destaque']): ?>
                                                    <span class="badge bg-warning text-dark me-1">Destaque</span>
                                                <?php endif; ?>
                                                <?php if ($c['promocao']): ?>
                                                    <span class="badge bg-danger">Promoção</span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($c['tipo_aula'] ?? 'Video'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-success fw-bold d-block">R$ <?php echo number_format($c['preco'], 2, ',', '.'); ?></span>
                                            <?php if (!empty($c['preco_antigo']) && $c['preco_antigo'] > 0): ?>
                                                <small class="text-muted text-decoration-line-through">R$ <?php echo number_format($c['preco_antigo'], 2, ',', '.'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($c['video_url'])): ?>
                                                <span title="Possui Vídeo" style="cursor:help;">🎥</span>
                                            <?php endif; ?>
                                            <?php if (!empty($c['arquivo_pdf'])): ?>
                                                <span title="Possui PDF" style="cursor:help;">📥</span>
                                            <?php endif; ?>
                                            <?php if (!empty($c['arquivo_planilha'])): ?>
                                                <span title="Possui Planilha" style="cursor:help;">📊</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="admin_cursos.php?editar=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary">✏️ Editar</a>
                                            <a href="admin_cursos.php?deletar=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja apagar este curso permanentemente?');">🗑️ Apagar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Nenhum curso cadastrado ainda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const inputsMonetarios = document.querySelectorAll('.monetario');

            inputsMonetarios.forEach(function (input) {
                input.addEventListener('input', function (e) {
                    let valor = e.target.value.replace(/\D/g, '');
                    if (valor === '') {
                        e.target.value = '';
                        return;
                    }
                    valor = (parseInt(valor) / 100).toFixed(2) + '';
                    valor = valor.replace('.', ',');
                    valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                    e.target.value = valor;
                });
            });
        });
    </script>

    <?php include '../includes/footer.php'; ?>

</body>

</html>