<?php
session_start();
require 'includes/conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    // CORRIGIDO: Redireciona para cursos.php (ou cursos.php conforme estrutura)
    header("Location: cursos.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    // CORRIGIDO: Redireciona para cursos.php
    header("Location: cursos.php");
    exit;
}

$imagem = !empty($curso['imagem']) ? $curso['imagem'] : 'https://placehold.co/600x400/111c44/FFFFFF?text=Engenharia+Academy';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($curso['titulo']); ?> - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #2b3674;
        }

        .card-detalhes {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: #fff;
        }

        .img-detalhes {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 350px;
        }

        .btn-matricular {
            background-color: #05CD99;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            transition: 0.3s;
            width: 100%;
        }

        .btn-matricular:hover {
            background-color: #04b586;
            color: white;
        }
    </style>
</head>

<body class="py-5">

    <div class="container">
        <div class="mb-4">
            <!-- CORRIGIDO: Redireciona para cursos.php -->
            <a href="cursos.php" class="btn btn-outline-secondary rounded-3">← Voltar para cursos</a>
        </div>

        <div class="card card-detalhes">
            <div class="row g-0">
                <div class="col-md-6">
                    <img src="<?php echo htmlspecialchars($imagem); ?>" class="img-detalhes" alt="Capa do curso">
                </div>
                <div class="col-md-6 p-4 p-md-5 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="fw-bold mb-3" style="color: #111c44;">
                            <?php echo htmlspecialchars($curso['titulo']); ?></h2>
                        <p class="text-muted leading-relaxed mb-4">
                            <?php echo nl2br(htmlspecialchars($curso['descricao'])); ?></p>
                    </div>

                    <div class="pt-4 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <span class="text-muted fw-semibold">Investimento:</span>
                            <h2 class="fw-bold m-0" style="color: #05CD99;">R$
                                <?php echo number_format($curso['preco'], 2, ',', '.'); ?></h2>
                        </div>

                        <!-- CORRIGIDO: Redireciona para checkout.php -->
                        <a href="checkout.php?curso_id=<?php echo $curso['id']; ?>"
                            class="btn btn-matricular d-block text-center text-decoration-none">
                            Matricular-se Agora
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>