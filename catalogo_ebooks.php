<?php
require 'includes/conexao.php';
// Busca apenas os e-books do banco de dados, do mais recente para o mais antigo
$stmt = $conn->query("SELECT * FROM ebooks ORDER BY id DESC");
$ebooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de E-books - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <!-- Bootstrap e Fontes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        /* Navegação Superior (Link para a Home) */
        .nav-top {
            background-color: #1f2937;
            padding: 15px 0;
        }

        .nav-top a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
        }

        .nav-top a:hover {
            color: #d1d5db;
        }

        /* Banner Principal em Degradê */
        .hero-section {
            background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%);
            color: white;
            padding: 70px 0;
            text-align: center;
        }

        .hero-title {
            font-weight: 700;
            font-size: 2.8rem;
            margin-bottom: 15px;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            opacity: 0.9;
        }

        /* Estilo dos Cards de E-books */
        .card-ebook {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #fff;
            text-decoration: none;
            color: inherit;
        }

        .card-ebook:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Contêiner da Capa */
        .img-container {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            border-radius: 12px 12px 0 0;
            background-color: #e9ecef;
        }

        .img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Etiqueta de Promoção */
        .badge-promo {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #ef4444;
            color: white;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Corpo e Textos do Card */
        .card-body-custom {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .card-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .card-text {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Linha Divisória */
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0 0 20px 0;
        }

        /* Preços e Botões */
        .price-tag {
            font-weight: 700;
            font-size: 1.6rem;
            color: #ef4444;
            margin-bottom: 20px;
        }

        .price-tag.free {
            color: #10b981;
        }

        .btn-detalhes {
            background-color: #de3c4b;
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            border: none;
            transition: 0.2s;
            text-align: center;
            width: 100%;
            display: inline-block;
            text-decoration: none;
        }

        .btn-detalhes:hover {
            background-color: #c9303d;
            color: white;
        }

        .btn-detalhes.free {
            background-color: #10b981;
        }

        .btn-detalhes.free:hover {
            background-color: #059669;
            color: white;
        }
    </style>
</head>

<body>

    <!-- Navegação Superior -->
    <div class="nav-top">
        <div class="container">
            <a href="index.php">
                <span class="me-2">←</span> Voltar ao Início
            </a>
        </div>
    </div>

    <!-- Banner Principal -->
    <header class="hero-section">
        <div class="container">
            <h1 class="hero-title">Catálogo Completo de E-books</h1>
            <p class="hero-subtitle">Escolha o seu próximo material de estudo e aprofunde seus conhecimentos práticos.
            </p>
        </div>
    </header>

    <!-- Grid de E-books (Listagem) -->
    <div class="container py-5 mb-5">
        <div class="row g-4">

            <?php if (count($ebooks) > 0): ?>
                <?php foreach ($ebooks as $e): ?>
                    <div class="col-lg-4 col-md-6">

                        <div class="card-ebook">

                            <!-- Imagem da Capa -->
                            <div class="img-container">
                                <?php if ($e['promocao'] == 1 && $e['preco'] > 0): ?>
                                    <span class="badge-promo">PROMOÇÃO</span>
                                <?php endif; ?>

                                <?php $img_src = !empty($e['imagem']) ? htmlspecialchars($e['imagem']) : 'https://placehold.co/600x400/e9ecef/a3a3a3?text=Sem+Capa'; ?>
                                <img src="<?php echo $img_src; ?>" alt="Capa do E-book">
                            </div>

                            <!-- Informações -->
                            <div class="card-body-custom">
                                <h3 class="card-title"><?php echo htmlspecialchars($e['titulo']); ?></h3>
                                <p class="card-text"><?php echo htmlspecialchars($e['descricao']); ?></p>

                                <div class="divider"></div>

                                <!-- Exibição Dinâmica do Preço -->
                                <?php if ($e['preco'] == 0): ?>
                                    <div class="price-tag free">Gratuito</div>
                                <?php else: ?>
                                    <div class="price-tag">R$ <?php echo number_format($e['preco'], 2, ',', '.'); ?></div>
                                <?php endif; ?>

                                <!-- Botão de Redirecionamento -->
                                <a href="detalhes_ebook.php?id=<?php echo $e['id']; ?>"
                                    class="btn-detalhes <?php echo ($e['preco'] == 0) ? 'free' : ''; ?>">
                                    Ver Detalhes
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Tela Vazia (Caso não tenha nenhum E-book cadastrado no banco) -->
                <div class="col-12 text-center py-5">
                    <h3 class="text-muted fw-bold">Nenhum e-book disponível no momento.</h3>
                    <p class="text-muted">Volte novamente mais tarde para conferir nossos lançamentos!</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <!-- Chama o arquivo do Rodapé -->
    <?php include 'includes/footer.php'; ?>

</body>

</html>