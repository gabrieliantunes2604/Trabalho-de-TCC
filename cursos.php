<?php
require 'includes/conexao.php';

// Busca TODOS os cursos cadastrados
$sql = "SELECT * FROM cursos ORDER BY id DESC";
$stmt = $conn->query($sql);
$todos_cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Cursos - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .hero-cursos {
            background: linear-gradient(135deg, #111c44 0%, #4318FF 100%);
            color: white;
            padding: 60px 0;
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

        .badge-promocao {
            background-color: #ff3b30;
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 15px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(255, 59, 48, 0.4);
            z-index: 10;
        }
    </style>
</head>

<body>

    <!-- Menu Simplificado -->
    <nav class="navbar navbar-dark bg-dark py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">← Voltar ao Início</a>
        </div>
    </nav>

    <header class="hero-cursos text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">Catálogo Completo de Cursos</h1>
            <p class="lead mb-0">Escolha a sua próxima especialização e alavanque sua carreira.</p>
        </div>
    </header>

    <div class="container py-5">
        <div class="row g-4">
            <?php if (!empty($todos_cursos)): ?>
                <?php foreach ($todos_cursos as $curso): ?>

                    <!-- Lógica para imagem padrão caso o curso não tenha foto -->
                    <?php
                    $imagem_curso = !empty($curso['imagem']) ? $curso['imagem'] : 'https://placehold.co/600x400/111c44/FFFFFF?text=Engenharia+Academy';
                    ?>

                    <div class="col-md-6 col-lg-4">
                        <div class="card card-produto h-100 position-relative d-flex flex-column">

                            <!-- Etiqueta de Promoção (se houver) -->
                            <?php if ($curso['promocao'] == 1): ?>
                                <div class="badge-promocao">PROMOÇÃO</div>
                            <?php endif; ?>

                            <!-- Imagem do Curso -->
                            <img src="<?php echo htmlspecialchars($imagem_curso); ?>" class="card-img-top"
                                alt="Capa do curso <?php echo htmlspecialchars($curso['titulo']); ?>">

                            <!-- Corpo do Card -->
                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <h4 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($curso['titulo']); ?></h4>
                                <p class="text-muted flex-grow-1 small">
                                    <?php echo htmlspecialchars(strlen($curso['descricao']) > 100 ? substr($curso['descricao'], 0, 100) . '...' : $curso['descricao']); ?>
                                </p>

                                <div class="mt-auto pt-3 border-top">
                                    <?php if ($curso['promocao'] == 1): ?>
                                        <h3 class="fw-bold text-danger mb-4">R$
                                            <?php echo number_format($curso['preco'], 2, ',', '.'); ?></h3>
                                    <?php else: ?>
                                        <h3 class="fw-bold text-primary mb-4">R$
                                            <?php echo number_format($curso['preco'], 2, ',', '.'); ?></h3>
                                    <?php endif; ?>

                                    <!-- CORRIGIDO: Direcionando para aluno/detalhes_curso.php -->
                                    <a href="detalhes_curso.php?id=<?php echo $curso['id']; ?>"
                                        class="btn <?php echo $curso['promocao'] == 1 ? 'btn-danger' : 'btn-comprar'; ?> py-2 w-100 rounded-3">Ver
                                        Detalhes</a>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted w-100">Nenhum curso disponível no momento.</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Chama o arquivo do Rodapé -->
    <?php include 'includes/footer.php'; ?>

</body>

</html>