<?php
require 'includes/conexao.php';

// Busca E-books Pagos
$pagos = $conn->query("SELECT * FROM ebooks WHERE preco > 0 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Busca E-books Gratuitos
$gratuitos = $conn->query("SELECT * FROM ebooks WHERE preco = 0 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-books - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Swiper CSS (Para o Carrossel) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: #333;
        }

        .section-title {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 30px;
        }

        .ebook-card {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #eee;
            text-decoration: none;
            color: inherit;
        }

        .ebook-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-img-wrapper {
            width: 100%;
            height: 250px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-content {
            padding: 20px;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-grow: 1;
        }

        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: #1c1d1f;
        }

        .card-arrow {
            color: #1c1d1f;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .swiper-pagination-bullet-active {
            background: #4318FF;
        }

        .swiper-button-next,
        .swiper-button-prev {
            color: #333;
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container py-5">

        <!-- Seção: E-books Pagos (Essenciais) -->
        <div class="row align-items-center mb-5">
            <div class="col-lg-3">
                <h2 class="section-title">Aprenda habilidades <span style="font-style: italic;">essenciais</span></h2>
                <p class="section-subtitle">Materiais completos desenvolvidos para alavancar sua carreira no mercado atual.</p>
            </div>

            <div class="col-lg-9">
                <!-- Swiper Carrossel Pagos -->
                <div class="swiper swiperPagos pb-5">
                    <div class="swiper-wrapper">
                        <?php foreach ($pagos as $p): ?>
                            <div class="swiper-slide">
                                <!-- Link direciona para detalhes_ebook.php na raiz -->
                                <a href="detalhes_ebook.php?id=<?php echo $p['id']; ?>" class="ebook-card">
                                    <div class="card-img-wrapper">
                                        <img src="<?php echo !empty($p['imagem']) ? htmlspecialchars($p['imagem']) : 'https://placehold.co/400x400?text=Capa'; ?>"
                                            alt="Capa">
                                    </div>
                                    <div class="card-content">
                                        <div>
                                            <h3 class="card-title"><?php echo htmlspecialchars($p['titulo']); ?></h3>
                                            <span class="text-success fw-bold small">R$
                                                <?php echo number_format($p['preco'], 2, ',', '.'); ?></span>
                                        </div>
                                        <span class="card-arrow">→</span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
        </div>

        <!-- Seção: E-books Gratuitos -->
        <?php if (count($gratuitos) > 0): ?>
            <hr class="my-5">

            <div class="row align-items-center mb-5">
                <div class="col-lg-3">
                    <h2 class="section-title">Materiais <span style="font-style: italic; color:#05CD99;">Gratuitos</span></h2>
                    <p class="section-subtitle">Comece agora mesmo a ler nossos materiais de apoio sem custo algum.</p>
                </div>

                <div class="col-lg-9">
                    <!-- Swiper Carrossel Gratuitos -->
                    <div class="swiper swiperGratuitos pb-5">
                        <div class="swiper-wrapper">
                            <?php foreach ($gratuitos as $g): ?>
                                <div class="swiper-slide">
                                    <!-- Link direciona para detalhes_ebook.php na raiz -->
                                    <a href="detalhes_ebook.php?id=<?php echo $g['id']; ?>" class="ebook-card">
                                        <div class="card-img-wrapper">
                                            <img src="<?php echo !empty($g['imagem']) ? htmlspecialchars($g['imagem']) : 'https://placehold.co/400x400?text=Capa'; ?>"
                                                alt="Capa">
                                        </div>
                                        <div class="card-content">
                                            <div>
                                                <h3 class="card-title"><?php echo htmlspecialchars($g['titulo']); ?></h3>
                                                <span class="badge bg-success">Baixar Grátis</span>
                                            </div>
                                            <span class="card-arrow">→</span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script>
        const swiperConfig = {
            slidesPerView: 1,
            spaceBetween: 20,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            breakpoints: {
                640: { slidesPerView: 2, spaceBetween: 20 },
                992: { slidesPerView: 3, spaceBetween: 30 },
            },
        };

        new Swiper(".swiperPagos", swiperConfig);
        new Swiper(".swiperGratuitos", swiperConfig);
    </script>
    
    <!-- Inclusão do Rodapé da pasta includes -->
    <?php include 'includes/footer.php'; ?>

</body>

</html>