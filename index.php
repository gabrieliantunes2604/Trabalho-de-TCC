<?php
session_start();
require 'includes/conexao.php';

// 1. Busca até 3 cursos marcados como DESTAQUE
$sqlDestaques = "SELECT * FROM cursos WHERE destaque = 1 ORDER BY id DESC LIMIT 3";
$stmtDestaques = $conn->query($sqlDestaques);
$cursos_destaque = $stmtDestaques->fetchAll(PDO::FETCH_ASSOC);

// 2. Busca até 3 cursos marcados como PROMOÇÃO
$sqlPromocao = "SELECT * FROM cursos WHERE promocao = 1 ORDER BY id DESC LIMIT 3";
$stmtPromocao = $conn->query($sqlPromocao);
$cursos_promocao = $stmtPromocao->fetchAll(PDO::FETCH_ASSOC);

// 3. Busca e-books (limite de 3 para vitrine)
$sqlEbooks = "SELECT * FROM ebooks ORDER BY id DESC LIMIT 3";
$stmtEbooks = $conn->query($sqlEbooks);
$ebooks = $stmtEbooks->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .hero-section {
            background: linear-gradient(135deg, #111c44 0%, #1b2b65 100%);
            color: white;
            padding: 100px 0;
        }

        .card-produto {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
            overflow: hidden;
        }

        .card-produto:hover {
            transform: translateY(-5px);
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .btn-comprar {
            background-color: #05CD99;
            color: white;
            font-weight: 600;
        }

        .btn-comprar:hover {
            background-color: #04b586;
            color: white;
        }

        /* BALÃO DE PROMOÇÃO PADRONIZADO */
        .badge-promocao {
            background-color: #ff3b30;
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 18px;
            border-radius: 50px;
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
            z-index: 10;
            width: max-content;
        }
    </style>
</head>

<body>

    <!-- Navegação Completa -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3 sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">Engenharia Academy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <li class="nav-item">
                        <a class="nav-link text-light fw-semibold" href="cursos.php">Todos os Cursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light fw-semibold" href="catalogo_ebooks.php">E-books</a>
                    </li>
                    <li class="nav-item">
                        <!-- Se estiver logado vai para o painel, se não vai para o login na pasta AUTH -->
                        <?php if (isset($_SESSION['aluno_id'])): ?>
                            <a class="nav-link text-light fw-semibold" href="aluno/painel_aluno.php"
                                title="Minhas Compras e Pedidos">
                                <i class="bi bi-cart-check"></i> Minhas Compras
                            </a>
                        <?php else: ?>
                            <a class="nav-link text-light fw-semibold" href="auth/login_aluno.php"
                                title="Minhas Compras e Pedidos">
                                <i class="bi bi-cart-check"></i> Minhas Compras
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light fw-semibold" href="#contato">Contato</a>
                    </li>

                    <li class="nav-item">
                        <?php if (isset($_SESSION['aluno_id'])): ?>
                            <a class="nav-link btn btn-primary text-white fw-bold px-4 py-2 rounded-3 text-nowrap"
                                href="aluno/painel_aluno.php" style="background-color: #4318FF; border: none;">
                                👤 Meu Painel
                            </a>
                        <?php else: ?>
                            <a class="nav-link btn btn-primary text-white fw-bold px-4 py-2 rounded-3 text-nowrap"
                                href="auth/login_aluno.php" style="background-color: #4318FF; border: none;">
                                🎓 Área do Aluno
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning fw-semibold text-nowrap" href="auth/login.php">⚙️ ERP Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Banner -->
    <header class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Especialize-se com os melhores materiais</h1>
            <p class="lead mb-5">Cursos e e-books completos para impulsionar a sua carreira na engenharia.</p>
            <a href="cursos.php" class="btn btn-light btn-lg fw-bold text-primary px-5 rounded-pill">Ver Todos os
                Cursos</a>
        </div>
    </header>

    <!-- Seção: Destaques -->
    <section class="py-5">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <h2 class="fw-bold mb-0">🌟 Cursos em Destaque</h2>
                <a href="cursos.php" class="text-decoration-none fw-bold">Ver catálogo completo →</a>
            </div>

            <div class="row g-4">
                <?php if (!empty($cursos_destaque)): ?>
                    <?php foreach ($cursos_destaque as $curso): ?>
                        <?php $imagem_curso = !empty($curso['imagem']) ? $curso['imagem'] : 'https://placehold.co/600x400/111c44/FFFFFF?text=Engenharia+Academy'; ?>

                        <div class="col-md-6 col-lg-4">
                            <div class="card card-produto h-100 position-relative d-flex flex-column"
                                style="border-top: 4px solid #4318FF;">
                                <img src="<?php echo (strpos($imagem_curso, 'http') === 0) ?$imagem_curso : str_replace('../', '', $imagem_curso); ?>" class="card-img-top" alt="Capa do curso">
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <h4 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($curso['titulo']); ?></h4>
                                    <p class="text-muted flex-grow-1 small">
                                        <?php echo strlen($curso['descricao']) > 100 ? substr(htmlspecialchars($curso['descricao']), 0, 100) . '...' : htmlspecialchars($curso['descricao']); ?>
                                    </p>
                                    <div class="mt-auto pt-3 border-top">
                                        <h3 class="fw-bold text-primary mb-4">R$
                                            <?php echo number_format($curso['preco'], 2, ',', '.'); ?>
                                        </h3>
                                        <a href="detalhes_curso.php?id=<?php echo $curso['id']; ?>"
                                            class="btn btn-comprar py-2 w-100 rounded-3">Ver Detalhes</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Novos cursos em destaque serão adicionados em breve.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Seção: Promoções -->
    <section class="py-5" style="background-color: #fef5f5;">
        <div class="container py-4">
            <h2 class="fw-bold mb-5 text-danger">🔥 Ofertas Especiais</h2>

            <div class="row g-4">
                <?php if (!empty($cursos_promocao)): ?>
                    <?php foreach ($cursos_promocao as $curso): ?>
                        <?php $imagem_curso = !empty($curso['imagem']) ? $curso['imagem'] : 'https://placehold.co/600x400/ff3b30/FFFFFF?text=Engenharia+Academy'; ?>

                        <div class="col-md-6 col-lg-4">
                            <div class="card card-produto h-100 position-relative d-flex flex-column">
                                <div class="badge-promocao">PROMOÇÃO</div>
                                <img src="<?php echo $imagem_curso; ?>" class="card-img-top" alt="Capa do curso">
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <h4 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($curso['titulo']); ?></h4>
                                    <p class="text-muted flex-grow-1 small">
                                        <?php echo strlen($curso['descricao']) > 100 ? substr(htmlspecialchars($curso['descricao']), 0, 100) . '...' : htmlspecialchars($curso['descricao']); ?>
                                    </p>
                                    <div class="mt-auto pt-3 border-top">
                                        <div class="mb-4">
                                            <?php if (!empty($curso['preco_antigo'])): ?>
                                                <del class="text-muted d-block mb-1" style="font-size: 0.95rem;">
                                                    R$ <?php echo number_format($curso['preco_antigo'], 2, ',', '.'); ?>
                                                </del>
                                            <?php endif; ?>

                                            <h3 class="fw-bold text-danger m-0">
                                                R$ <?php echo number_format($curso['preco'], 2, ',', '.'); ?>
                                            </h3>
                                        </div>

                                        <a href="detalhes_curso.php?id=<?php echo $curso['id']; ?>"
                                            class="btn btn-danger fw-bold py-2 w-100 rounded-3">Aproveitar Oferta</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Nenhuma promoção ativa no momento. Fique de olho!</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Vitrine de E-books -->
    <section id="ebooks" class="py-5 bg-light">
        <div class="container py-4">
            <h2 class="fw-bold text-center mb-5">E-books e Materiais em PDF</h2>
            <div class="row g-4">
                <?php if (!empty($ebooks)): ?>
                    <?php foreach ($ebooks as $e): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card card-produto h-100 position-relative d-flex flex-column">
                                <div style="height: 220px; background-color: #e9ecef; position: relative;">
                                    <?php if (isset($e['promocao']) && $e['promocao'] == 1 && $e['preco'] > 0): ?>
                                        <div class="badge-promocao">PROMOÇÃO</div>
                                    <?php endif; ?>

                                    <?php $img_src = !empty($e['imagem']) ? htmlspecialchars($e['imagem']) : 'https://placehold.co/600x400/e9ecef/a3a3a3?text=Sem+Capa'; ?>
                                    <img src="<?php echo $img_src; ?>" alt="Capa do E-book"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                </div>

                                <div class="card-body p-4 d-flex flex-column flex-grow-1">
                                    <span class="badge bg-primary w-25 mb-3">E-book</span>
                                    <h4 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($e['titulo']); ?></h4>
                                    <p class="text-muted flex-grow-1 small">
                                        <?php echo strlen($e['descricao']) > 100 ? substr(htmlspecialchars($e['descricao']), 0, 100) . '...' : htmlspecialchars($e['descricao']); ?>
                                    </p>

                                    <div class="mt-auto pt-3 border-top">
                                        <?php if ($e['preco'] == 0): ?>
                                            <h3 class="fw-bold text-success mb-4">Gratuito</h3>
                                        <?php else: ?>
                                            <h3 class="fw-bold text-dark mb-4">R$
                                                <?php echo number_format($e['preco'], 2, ',', '.'); ?>
                                            </h3>
                                        <?php endif; ?>

                                        <a href="detalhes_ebook.php?id=<?php echo $e['id']; ?>"
                                            class="btn btn-outline-primary fw-bold py-2 w-100 rounded-3">Ver E-book</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted">Nenhum e-book disponível.</p>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5">
                <a href="catalogo_ebooks.php" class="btn btn-primary btn-lg fw-bold px-5 rounded-pill"
                    style="background-color: #4318FF; border: none;">
                    Ver Todos os E-books
                </a>
            </div>

        </div>
    </section>


    <!-- SEÇÃO DE CONTATO (WHATSAPP) -->
    <section id="contato" class="py-5" style="background-color: #f4f7fe;">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-4">
                    <h2 class="fw-bold text-dark">Fale Conosco</h2>
                    <p class="text-muted">Tem alguma dúvida sobre os cursos ou e-books? Envie uma mensagem no nosso
                        WhatsApp.</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border border-light-subtle shadow-sm p-4"
                        style="border-radius: 12px; background-color: #ffffff;">
                        <form id="formWhatsApp" onsubmit="enviarParaWhatsApp(event)">
                            <div class="row g-3">
                                <div class="col-md-6 text-start">
                                    <label class="form-label fw-semibold text-dark">Seu Nome</label>
                                    <input type="text" id="wa_nome" class="form-control" placeholder="Digite seu nome"
                                        required>
                                </div>
                                <div class="col-md-6 text-start">
                                    <label class="form-label fw-semibold text-dark">Assunto</label>
                                    <input type="text" id="wa_assunto" class="form-control"
                                        placeholder="Ex: Dúvida sobre o curso de Excel" required>
                                </div>
                                <div class="col-12 text-start">
                                    <label class="form-label fw-semibold text-dark">Mensagem</label>
                                    <textarea id="wa_mensagem" class="form-control" rows="4"
                                        placeholder="Escreva sua mensagem aqui..." required></textarea>
                                </div>
                                <div class="col-12 text-end mt-3">
                                    <button type="submit" class="btn px-4 py-2 fw-bold text-white"
                                        style="background-color: #25d366; border-radius: 8px; border: none;">
                                        💬 Abrir WhatsApp
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function enviarParaWhatsApp(e) {
            e.preventDefault();

            // Insira o número do seu WhatsApp com DDD (apenas números, ex: 5511999999999)
            const numeroTelefone = "5544984553680";

            const nome = document.getElementById('wa_nome').value;
            const assunto = document.getElementById('wa_assunto').value;
            const mensagem = document.getElementById('wa_mensagem').value;

            const textoFormatado = `*Contato via Site - Engenharia Academy*%0A%0A` +
                `*Nome:* ${encodeURIComponent(nome)}%0A` +
                `*Assunto:* ${encodeURIComponent(assunto)}%0A` +
                `*Mensagem:* ${encodeURIComponent(mensagem)}`;

            const url = `https://api.whatsapp.com/send?phone=${numeroTelefone}&text=${textoFormatado}`;

            window.open(url, '_blank');
        }
    </script>

    <!-- Rodapé da Página -->
    <footer id="contato">
        <?php include 'includes/footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>