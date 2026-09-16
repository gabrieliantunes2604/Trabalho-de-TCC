<?php
session_start();
require '../includes/conexao.php';

// Verifica se está logado
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Função para tratar valores monetários
function converterMoedaParaFloat($valor) {
    if (empty($valor)) return 0.0;
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return (float) $valor;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);

    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $promocao = isset($_POST['promocao']) ? 1 : 0;
    $is_gratuito = isset($_POST['is_gratuito']) ? 1 : 0;

    $preco = $is_gratuito ? 0.00 : (isset($_POST['preco']) ? converterMoedaParaFloat($_POST['preco']) : 0.00);
    $preco_antigo = !empty($_POST['preco_antigo']) ? converterMoedaParaFloat($_POST['preco_antigo']) : null;

    $id_edit = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $caminho_imagem = "";
    $caminho_pdf = "";
    
    if ($id_edit) {
        $stmt_atual = $conn->prepare("SELECT imagem, arquivo_pdf FROM ebooks WHERE id = :id");
        $stmt_atual->execute([':id' => $id_edit]);
        $dados_atuais = $stmt_atual->fetch(PDO::FETCH_ASSOC);
        if ($dados_atuais) {
            $caminho_imagem = $dados_atuais['imagem'];
            $caminho_pdf = $dados_atuais['arquivo_pdf'];
        }
    }

    $pasta_uploads = "../uploads/";
    $pasta_pdfs    = "../uploads/pdfs/";

    if (!is_dir($pasta_uploads)) mkdir($pasta_uploads, 0777, true);
    if (!is_dir($pasta_pdfs)) mkdir($pasta_pdfs, 0777, true);

    // Upload da Imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $extensoes_permitidas)) {
            $novo_nome_img = "ebook_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $pasta_uploads . $novo_nome_img)) {
                $caminho_imagem = "uploads/" . $novo_nome_img;
            }
        }
    }

    // Upload do PDF
    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $ext_pdf = strtolower(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_EXTENSION));

        if ($ext_pdf === 'pdf') {
            $novo_nome_pdf = "ebook_arq_" . uniqid() . ".pdf";
            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $pasta_pdfs . $novo_nome_pdf)) {
                $caminho_pdf = "pdfs/" . $novo_nome_pdf;
            }
        }
    }

    if ($id_edit) {
        $sql = "UPDATE ebooks SET 
                    titulo = :titulo, 
                    descricao = :descricao, 
                    preco = :preco, 
                    preco_antigo = :preco_antigo, 
                    destaque = :destaque, 
                    promocao = :promocao,
                    imagem = :imagem,
                    arquivo_pdf = :arquivo_pdf
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id_edit, PDO::PARAM_INT);
    } else {
        $sql = "INSERT INTO ebooks (titulo, descricao, preco, preco_antigo, destaque, promocao, imagem, arquivo_pdf) 
                VALUES (:titulo, :descricao, :preco, :preco_antigo, :destaque, :promocao, :imagem, :arquivo_pdf)";
        $stmt = $conn->prepare($sql);
    }

    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':preco_antigo', $preco_antigo);
    $stmt->bindParam(':destaque', $destaque, PDO::PARAM_INT);
    $stmt->bindParam(':promocao', $promocao, PDO::PARAM_INT);
    $stmt->bindParam(':imagem', $caminho_imagem);
    $stmt->bindParam(':arquivo_pdf', $caminho_pdf);
    $stmt->execute();

    header("Location: admin_ebooks.php");
    exit;
}

// Exclusão
if (isset($_GET['deletar'])) {
    $id = (int)$_GET['deletar'];
    $stmt = $conn->prepare("DELETE FROM ebooks WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: admin_ebooks.php");
    exit;
}

// Busca todos os e-books
$ebooks = $conn->query("SELECT * FROM ebooks ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Edição
$ebook_edit = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM ebooks WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $ebook_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de E-books</title>
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

        .sidebar-brand h4 { font-weight: 700; font-size: 1.2rem; margin: 0; color: #fff; }
        .sidebar-brand span.erp { color: var(--primary-purple); font-weight: 700; display: block; }

        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0 0 0; }
        .sidebar-menu li { margin-bottom: 6px; }

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

        .sidebar-menu a:hover, .sidebar-menu li.active a {
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-weight: 600;
            border-left: 4px solid var(--primary-purple);
        }

        .sidebar-footer { border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .link-ver-site { color: #ffb800 !important; }
        .link-sair { color: #ee5d50 !important; }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
            box-sizing: border-box;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #fff;
            margin-bottom: 24px;
        }

        .table-responsive {
            overflow-x: auto;
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
                    <li class="active"><a href="admin_ebooks.php">📖 E-books</a></li>
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

        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold m-0 text-dark">Gestão de E-books</h2>
                    <p class="text-muted small m-0">Gerencie todos os materiais em PDF da plataforma.</p>
                </div>
                <a href="admin.php" class="btn btn-outline-secondary bg-white shadow-sm fw-semibold">⬅ Voltar ao Dashboard</a>
            </div>

            <!-- Formulário -->
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-3"><?php echo $ebook_edit ? 'Editar E-book' : 'Cadastrar Novo E-book'; ?></h5>
                <form action="admin_ebooks.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $ebook_edit ? $ebook_edit['id'] : ''; ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Título do E-book</label>
                            <input type="text" name="titulo" class="form-control" required
                                value="<?php echo $ebook_edit ? htmlspecialchars($ebook_edit['titulo']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Preço Antigo (Opcional)</label>
                            <input type="text" name="preco_antigo" class="form-control monetario"
                                placeholder="Ex: 49,90"
                                value="<?php echo ($ebook_edit && !empty($ebook_edit['preco_antigo'])) ? number_format($ebook_edit['preco_antigo'], 2, ',', '.') : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Preço Atual</label>
                            <div class="input-group">
                                <input type="text" name="preco" id="preco_atual"
                                    class="form-control monetario text-success fw-bold" placeholder="Ex: 29,99" 
                                    <?php echo ($ebook_edit && $ebook_edit['preco'] == 0) ? 'disabled' : 'required'; ?>
                                    value="<?php echo ($ebook_edit && $ebook_edit['preco'] > 0) ? number_format($ebook_edit['preco'], 2, ',', '.') : ''; ?>">
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="is_gratuito" id="is_gratuito"
                                    <?php echo ($ebook_edit && $ebook_edit['preco'] == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label small text-success fw-bold" for="is_gratuito">Disponibilizar Gratuitamente</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Descrição do E-book</label>
                            <textarea name="descricao" class="form-control" rows="3" required><?php echo $ebook_edit ? htmlspecialchars($ebook_edit['descricao']) : ''; ?></textarea>
                        </div>

                        <div class="col-md-6 border-top pt-3">
                            <label class="form-label fw-semibold text-primary">Capa do E-book (Imagem)</label>
                            <?php if ($ebook_edit && !empty($ebook_edit['imagem'])): ?>
                                <div class="mb-2"><span class="badge bg-info text-dark">Imagem Cadastrada</span></div>
                            <?php endif; ?>
                            <input type="file" name="imagem" class="form-control" accept="image/*">
                        </div>

                        <div class="col-md-6 border-top pt-3">
                            <label class="form-label fw-semibold text-danger">📥 Arquivo do E-book (PDF)</label>
                            <?php if ($ebook_edit && !empty($ebook_edit['arquivo_pdf'])): ?>
                                <div class="mb-2"><span class="badge bg-success">PDF Cadastrado</span></div>
                            <?php endif; ?>
                            <input type="file" name="arquivo_pdf" class="form-control" accept="application/pdf">
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="destaque" id="destaque" <?php echo ($ebook_edit && $ebook_edit['destaque'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="destaque">🌟 Marcar como Destaque</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="promocao" id="promocao" <?php echo ($ebook_edit && $ebook_edit['promocao'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-danger fw-semibold" for="promocao">🔥 Colocar em Promoção</label>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary px-4 fw-bold" style="background-color: #4318FF; border:none;">
                                <?php echo $ebook_edit ? 'Atualizar E-book' : 'Salvar E-book'; ?>
                            </button>
                            <?php if ($ebook_edit): ?>
                                <a href="admin_ebooks.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabela de E-books -->
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-4">E-books Cadastrados</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small" style="background-color: #f8f9fa;">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 80px;">CAPA</th>
                                <th>TÍTULO</th>
                                <th style="width: 120px;">PREÇO</th>
                                <th style="width: 120px;">ARQUIVO</th>
                                <th class="text-end" style="width: 150px;">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ebooks)): ?>
                                <?php foreach ($ebooks as $e): 
                                    // TRATAMENTO INTELIGENTE DA IMAGEM PARA EXIBIÇÃO NO ADMIN
                                    $nome_arquivo_img = basename($e['imagem']);
                                    
                                    // Testa os locais onde a imagem pode estar guardada fisicamente
                                    $caminho_real_img = "";
                                    if (!empty($nome_arquivo_img)) {
                                        if (file_exists("../uploads/" . $nome_arquivo_img)) {
                                            $caminho_real_img = "../uploads/" . $nome_arquivo_img;
                                        } elseif (file_exists("../uploads/ebooks/" . $nome_arquivo_img)) {
                                            $caminho_real_img = "../uploads/ebooks/" . $nome_arquivo_img;
                                        } elseif (file_exists("../" . ltrim($e['imagem'], './'))) {
                                            $caminho_real_img = "../" . ltrim($e['imagem'], './');
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td class="text-muted fw-bold">#<?php echo $e['id']; ?></td>
                                        <td>
                                            <?php if (!empty($caminho_real_img)): ?>
                                                <img src="<?php echo htmlspecialchars($caminho_real_img); ?>" alt="Capa"
                                                    style="width: 45px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                            <?php else: ?>
                                                <div style="width: 45px; height: 60px; background-color: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d; text-align: center; border: 1px dashed #ccc;">
                                                    Sem Capa</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($e['titulo']); ?></div>
                                            <div class="small mt-1">
                                                <?php if ($e['destaque']): ?><span class="badge bg-warning text-dark me-1">Destaque</span><?php endif; ?>
                                                <?php if ($e['promocao']): ?><span class="badge bg-danger">Promoção</span><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($e['preco'] == 0): ?>
                                                <span class="badge bg-success">Gratuito</span>
                                            <?php else: ?>
                                                <span class="text-success fw-bold d-block">R$ <?php echo number_format($e['preco'], 2, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($e['arquivo_pdf'])): ?>
                                                <span class="badge bg-primary">PDF Anexado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sem PDF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="admin_ebooks.php?editar=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                                            <a href="admin_ebooks.php?deletar=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apagar este e-book?');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Nenhum e-book cadastrado ainda.</td>
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
            const checkboxGratuito = document.getElementById('is_gratuito');
            const inputPreco = document.getElementById('preco_atual');

            if (checkboxGratuito && inputPreco) {
                checkboxGratuito.addEventListener('change', function () {
                    if (this.checked) {
                        inputPreco.value = '';
                        inputPreco.disabled = true;
                        inputPreco.required = false;
                    } else {
                        inputPreco.disabled = false;
                        inputPreco.required = true;
                    }
                });
            }

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