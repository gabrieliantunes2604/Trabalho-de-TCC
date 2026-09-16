<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

// Proteção 1: Aluno logado
if (!isset($_SESSION['aluno_id'])) {
    header("Location: ../auth/login_aluno.php");
    exit;
}

$aluno_id = (int) $_SESSION['aluno_id'];
$curso_id = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT);

if (!$curso_id) {
    header("Location: painel_aluno.php");
    exit;
}

// Busca dados do aluno
$stmtA = $conn->prepare("SELECT nome, cpf, email FROM alunos WHERE id = ?");
$stmtA->execute([$aluno_id]);
$aluno = $stmtA->fetch(PDO::FETCH_ASSOC);

if (!$aluno) {
    header("Location: ../auth/login_aluno.php");
    exit;
}

// Busca matrícula e dados do curso
$stmtC = $conn->prepare("
    SELECT c.id, c.titulo, c.descricao, m.status_pagamento, m.data_matricula
    FROM matriculas m
    JOIN cursos c ON m.curso_id = c.id
    WHERE m.aluno_id = ? AND m.curso_id = ?
");
$stmtC->execute([$aluno_id, $curso_id]);
$curso = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    echo "<script>alert('Você não possui matrícula neste curso.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$status = strtolower($curso['status_pagamento'] ?? '');
$is_pago = in_array($status, ['pago', 'confirmado', 'aprovado'], true);

if (!$is_pago) {
    echo "<script>alert('O pagamento deste curso ainda não foi confirmado.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

// Verifica se o progresso é 100%
$progresso = obterProgressoCurso($conn, $aluno_id, $curso_id);

if ($progresso < 100) {
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>Certificado Indisponível</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>body { font-family: sans-serif; background: #f4f7fe; }</style>
    </head>
    <body class='d-flex align-items-center justify-content-center vh-100'>
        <div class='card p-5 text-center shadow-sm border-0' style='max-width: 500px; border-radius: 16px;'>
            <div class='fs-1 mb-3'>⏳</div>
            <h4 class='fw-bold'>Curso em Andamento ({$progresso}%)</h4>
            <p class='text-muted'>Para emitir seu certificado de conclusão, é necessário concluir 100% das aulas deste curso.</p>
            <a href='player_aulas.php?id={$curso_id}' class='btn btn-primary fw-bold py-2' style='background: #4318FF; border:none;'>Continuar Assistindo</a>
            <a href='painel_aluno.php' class='btn btn-link text-muted mt-2'>Voltar ao Painel</a>
        </div>
    </body>
    </html>";
    exit;
}

// Busca data da última conclusão
$stmtData = $conn->prepare("SELECT MAX(data_conclusao) AS ultima_data FROM progresso_cursos WHERE aluno_id = ? AND curso_id = ?");
$stmtData->execute([$aluno_id, $curso_id]);
$dataRow = $stmtData->fetch(PDO::FETCH_ASSOC);
$dataConclusaoObj = !empty($dataRow['ultima_data']) ? new DateTime($dataRow['ultima_data']) : new DateTime();

// Meses em português
$meses = [
    1 => 'janeiro',
    2 => 'fevereiro',
    3 => 'março',
    4 => 'abril',
    5 => 'maio',
    6 => 'junho',
    7 => 'julho',
    8 => 'agosto',
    9 => 'setembro',
    10 => 'outubro',
    11 => 'novembro',
    12 => 'dezembro'
];
$diaFormatado = $dataConclusaoObj->format('d');
$mesFormatado = $meses[(int) $dataConclusaoObj->format('m')];
$anoFormatado = $dataConclusaoObj->format('Y');
$dataPorExtenso = "{$diaFormatado} de {$mesFormatado} de {$anoFormatado}";

// Hash de autenticidade único e registro oficial no banco de dados (TCC)
$codigoAutenticidade = registrarOuObterCertificado($conn, $aluno_id, $curso_id, 40);
$cpfExibicao = !empty($aluno['cpf']) && !str_starts_with($aluno['cpf'], 'TMP') ? formatarCPF($aluno['cpf']) : 'Inscrito na Plataforma';

// Monta a URL pública para verificação de autenticidade do certificado
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$diretorio = dirname($_SERVER['PHP_SELF']);
$diretorioRaiz = rtrim(dirname($diretorio), '/\\');
$urlValidacao = "{$protocolo}://{$host}{$diretorioRaiz}/validar_certificado.php?codigo=" . urlencode($codigoAutenticidade);

// URL do gerador de QR Code gratuito e de alta definição (QuickChart / QRServer)
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=" . urlencode($urlValidacao);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado - <?php echo htmlspecialchars($curso['titulo']); ?> - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Inter:wght@300;400;500;600;700&family=Great+Vibes&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #eef2f8;
            font-family: 'Inter', sans-serif;
            color: #1a202c;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 15px;
            min-height: 100vh;
        }

        /* Barra de Ações Superior */
        .actions-bar {
            width: 100%;
            max-width: 1050px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-print {
            background-color: #4318FF;
            color: #ffffff;
        }

        .btn-print:hover {
            background-color: #3311DB;
        }

        .btn-back {
            background-color: #ffffff;
            color: #2b3674;
            border: 1px solid #d1d5db;
        }

        .btn-back:hover {
            background-color: #f3f4f6;
        }

        /* Moldura do Certificado (Paisagem A4) */
        .certificate-container {
            width: 1050px;
            height: 742px;
            background: #ffffff;
            position: relative;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        /* Bordas ornamentais */
        .cert-outer-border {
            border: 3px solid #b89345;
            height: 100%;
            padding: 6px;
            position: relative;
        }

        .cert-inner-border {
            border: 1.5px solid #111c44;
            height: 100%;
            padding: 35px 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            position: relative;
            background: radial-gradient(circle at center, #ffffff 60%, #faf8f2 100%);
        }

        /* Cantos decorativos */
        .corner {
            position: absolute;
            width: 32px;
            height: 32px;
            border-color: #b89345;
            border-style: solid;
        }

        .top-left {
            top: 10px;
            left: 10px;
            border-width: 3px 0 0 3px;
        }

        .top-right {
            top: 10px;
            right: 10px;
            border-width: 3px 3px 0 0;
        }

        .bottom-left {
            bottom: 10px;
            left: 10px;
            border-width: 0 0 3px 3px;
        }

        .bottom-right {
            bottom: 10px;
            right: 10px;
            border-width: 0 3px 3px 0;
        }

        /* Cabeçalho */
        .cert-header {
            margin-bottom: 10px;
        }

        .institution-name {
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 3px;
            color: #111c44;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .cert-subtitle {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #b89345;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .cert-main-title {
            font-family: 'Cinzel', serif;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 4px;
            color: #b89345;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        /* Corpo do Certificado */
        .cert-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 10px 20px;
        }

        .cert-intro {
            font-size: 1rem;
            color: #4a5568;
            margin-bottom: 12px;
        }

        .student-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: #111c44;
            border-bottom: 2px solid #b89345;
            padding-bottom: 6px;
            margin-bottom: 12px;
            display: inline-block;
            min-width: 450px;
        }

        .cert-text {
            font-size: 1.05rem;
            line-height: 1.7;
            color: #2d3748;
            max-width: 820px;
            margin-bottom: 8px;
        }

        .course-title {
            font-weight: 700;
            color: #111c44;
            font-size: 1.2rem;
        }

        /* Rodapé com Assinaturas e Selo */
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 25px;
            padding-top: 15px;
        }

        .signature-box {
            width: 250px;
            text-align: center;
        }

        .signature-line {
            height: 1px;
            background-color: #4a5568;
            margin-bottom: 6px;
        }

        .signature-name {
            font-family: 'Great Vibes', cursive;
            font-size: 1.8rem;
            color: #111c44;
            line-height: 1;
            margin-bottom: 4px;
        }

        .signature-role {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #718096;
            font-weight: 600;
        }

        /* Selo e QR Code de Autenticidade */
        .seal-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .qr-code-img {
            width: 64px;
            height: 64px;
            border: 2px solid #d4af37;
            border-radius: 6px;
            padding: 2px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .qr-code-caption {
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Código de Autenticação */
        .auth-code {
            font-size: 0.68rem;
            color: #4a5568;
            font-family: monospace;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }

        /* Estilo de Impressão */
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            .certificate-container {
                width: 100vw;
                height: 100vh;
                box-shadow: none;
                padding: 10mm;
                page-break-inside: avoid;
            }

            @page {
                size: landscape;
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <!-- Botões de Ação na Tela -->
    <div class="actions-bar no-print">
        <a href="painel_aluno.php" class="btn-action btn-back">
            ← Voltar ao Painel do Aluno
        </a>
        <button onclick="window.print();" class="btn-action btn-print">
            🖨️ Imprimir / Salvar em PDF
        </button>
    </div>

    <!-- Container Principal do Certificado -->
    <div class="certificate-container">
        <div class="cert-outer-border">
            <div class="corner top-left"></div>
            <div class="corner top-right"></div>
            <div class="corner bottom-left"></div>
            <div class="corner bottom-right"></div>

            <div class="cert-inner-border">

                <!-- Cabeçalho -->
                <div class="cert-header">
                    <div class="institution-name">Engenharia Academy</div>
                    <div class="cert-subtitle">Centro de Excelência em Capacitação Tecnológica</div>
                    <h1 class="cert-main-title">Certificado de Conclusão</h1>
                </div>

                <!-- Corpo -->
                <div class="cert-body">
                    <p class="cert-intro">Certificamos com mérito e distinção que</p>
                    <div class="student-name"><?php echo htmlspecialchars($aluno['nome']); ?></div>

                    <p class="cert-text">
                        concluiu com êxito todos os módulos, exercícios e avaliações do curso de capacitação
                        profissional em
                    </p>

                    <p class="course-title">
                        "<?php echo htmlspecialchars($curso['titulo']); ?>"
                    </p>

                    <p class="cert-text" style="font-size: 0.95rem; margin-top: 6px;">
                        com carga horária total estimada de <strong>40 horas acadêmicas</strong>, finalizado em
                        <strong><?php echo $dataPorExtenso; ?></strong>.
                    </p>
                </div>

                <!-- Rodapé -->
                <div class="cert-footer">
                    <!-- Assinatura 1 -->
                    <div class="signature-box">
                        <div class="signature-name">Gabrieli Antunes</div>
                        <div class="signature-line"></div>
                        <div class="signature-role">Diretoria Pedagógica</div>
                    </div>

                    <!-- Selo e QR Code de Autenticidade Oficial (TCC) -->
                    <div class="seal-box">
                        <a href="<?php echo htmlspecialchars($urlValidacao); ?>" target="_blank"
                            title="Clique para validar autenticidade">
                            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code de Validação"
                                class="qr-code-img">
                        </a>
                        <div class="qr-code-caption">Validação Online (QR Code)</div>
                        <div class="auth-code"><?php echo $codigoAutenticidade; ?></div>
                    </div>

                    <!-- Assinatura 2 -->
                    <div class="signature-box">
                        <div class="signature-name">Eng. André Biancardine</div>
                        <div class="signature-line"></div>
                        <div class="signature-role">Coordenador Técnico</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>