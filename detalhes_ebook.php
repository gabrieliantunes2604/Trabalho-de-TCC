<?php
session_start();
require 'includes/conexao.php';

// Verifica se o ID do e-book foi passado na URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Busca os detalhes do e-book no banco de dados
$stmt = $conn->prepare("SELECT * FROM ebooks WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$ebook = $stmt->fetch(PDO::FETCH_ASSOC);

// Se não achar o e-book, redireciona para a página inicial
if (!$ebook) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ebook['titulo']); ?> - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .hero-curso {
            background: linear-gradient(135deg, #111c44 0%, #1b2b65 100%);
            color: white;
            padding: 60px 0;
        }

        /* Ajuste na capa do e-book para formato retrato (livro) */
        .img-capa {
            width: 100%;
            max-width: 320px;
            display: block;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            object-fit: cover;
        }

        .card-preco {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background-color: #fff;
            position: sticky;
            top: 100px;
        }

        .btn-comprar {
            background-color: #05CD99;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .btn-comprar:hover {
            background-color: #04b586;
            color: white;
        }

        .badge-destaque {
            background-color: #FFCE20;
            color: #111c44;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <!-- Navegação Superior -->
    <nav class="navbar navbar-dark bg-dark py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">← Voltar para Home</a>
        </div>
    </nav>

    <!-- Cabeçalho do E-book -->
    <header class="hero-curso">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <?php if ($ebook['promocao'] == 1): ?>
                        <span class="badge bg-danger mb-3 px-3 py-2">🔥 Em Promoção</span>
                    <?php endif; ?>
                    <?php if ($ebook['destaque'] == 1): ?>
                        <span class="badge badge-destaque mb-3 px-3 py-2 ms-2">🌟 Destaque</span>
                    <?php endif; ?>

                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($ebook['titulo']); ?></h1>
                    <p class="lead opacity-75">Aprofunde seus conhecimentos e tenha este material como guia de consulta
                        a qualquer momento.</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <div class="container py-5">
        <div class="row g-5">

            <!-- Coluna da Esquerda (Informações e Descrição) -->
            <div class="col-lg-7">

                <h3 class="fw-bold mb-4">📖 Sobre este E-book</h3>
                <div class="fs-5 text-muted mb-5" style="line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($ebook['descricao'])); ?>
                </div>

                <!-- Card extra para materiais gratuitos -->
                <?php if ($ebook['preco'] == 0 && !empty($ebook['arquivo_pdf'])): ?>
                    <div
                        class="p-4 border rounded-3 bg-white shadow-sm d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-1 text-success">🎉 E-book 100% Gratuito</h4>
                            <p class="text-muted mb-0 small">Você já pode fazer o download completo e começar a ler agora
                                mesmo.</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Coluna da Direita (Card de Compra Fixo) -->
            <div class="col-lg-5">
                <div class="card card-preco p-4">

                    <!-- Imagem da Capa -->
                    <?php $imagem_ebook = !empty($ebook['imagem']) ? $ebook['imagem'] : 'https://placehold.co/400x600/111c44/FFFFFF?text=Capa+E-book'; ?>
                    <div class="mb-4 text-center">
                        <img src="<?php echo htmlspecialchars($imagem_ebook); ?>" alt="Capa do E-book" class="img-capa">
                    </div>

                    <!-- Preços -->
                    <div class="mb-4 text-center">
                        <?php if ($ebook['preco'] == 0): ?>
                            <h2 class="fw-bold text-success display-6 mb-0">Gratuito</h2>
                        <?php else: ?>
                            <?php if (!empty($ebook['preco_antigo']) && $ebook['preco_antigo'] > 0): ?>
                                <del class="text-muted d-block mb-1 fs-5">De R$
                                    <?php echo number_format($ebook['preco_antigo'], 2, ',', '.'); ?></del>
                            <?php endif; ?>
                            <h2 class="fw-bold text-success display-6 mb-0">R$
                                <?php echo number_format($ebook['preco'], 2, ',', '.'); ?></h2>
                        <?php endif; ?>
                    </div>

                    <!-- Botões Inteligentes -->
                    <?php if ($ebook['preco'] == 0): ?>

                        <?php if (!empty($ebook['arquivo_pdf'])): ?>
                            <!-- Botão de Download Direto (Gratuito) -->
                            <a href="<?php echo htmlspecialchars($ebook['arquivo_pdf']); ?>" download
                                class="btn btn-comprar w-100 py-3 mb-3 rounded-pill text-uppercase">
                                📥 Baixar E-book Agora
                            </a>
                        <?php else: ?>
                            <!-- Botão Desativado (Sem arquivo) -->
                            <button class="btn btn-secondary w-100 py-3 mb-3 rounded-pill fw-bold" disabled>Arquivo
                                Indisponível</button>
                        <?php endif; ?>

                    <?php else: ?>

                        <?php if (isset($_SESSION['aluno_id'])): ?>
                            <!-- ALUNO LOGADO: Redireciona para o checkout do e-book -->
                            <a href="compra_ebook.php?id=<?php echo $ebook['id']; ?>"
                                class="btn btn-comprar w-100 py-3 mb-3 rounded-pill">
                                Comprar E-book
                            </a>
                        <?php else: ?>
                            <!-- NÃO LOGADO: Redireciona para o formulário de cadastro/autenticação -->
                            <a href="auth/cadastro.php?tipo=ebook&id=<?php echo $ebook['id']; ?>"
                                class="btn btn-comprar w-100 py-3 mb-3 rounded-pill">
                                Comprar E-book
                            </a>
                            <a href="auth/login_aluno.php" class="btn btn-outline-secondary w-100 py-2 rounded-pill fw-semibold">
                                Já sou aluno (Acessar)
                            </a>
                        <?php endif; ?>

                    <?php endif; ?>

                    <hr class="my-4">
                    <ul class="list-unstyled text-muted small">
                        <li class="mb-2">✔️ Formato digital em PDF (Alta Qualidade)</li>
                        <li class="mb-2">✔️ Compatível com celulares, tablets e PCs</li>
                        <?php if ($ebook['preco'] > 0): ?>
                            <li class="mb-2">✔️ Pagamento 100% seguro</li>
                            <li class="mb-2">✔️ Acesso vitalício ao material</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <!-- Chama o arquivo do Rodapé -->
    <?php include 'includes/footer.php'; ?>

</body>

</html>