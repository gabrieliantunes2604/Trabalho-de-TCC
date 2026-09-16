<?php
/**
 * ============================================================================
 * ENGENHARIA ACADEMY - SISTEMA DE GESTÃO E-LEARNING & ERP
 * Arquivo: player_aulas.php
 * Finalidade: Ambiente de Aprendizagem (LMS) do Aluno.
 *             Apresenta o player de vídeo, navegação entre aulas, materiais em
 *             anexo (PDF/Planilhas), Fórum de Dúvidas (Q&A interativo com professor)
 *             e Quizzes de Fixação pedagógica para liberação do certificado.
 * ============================================================================
 */

session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

// Garante as tabelas estruturais do TCC
garantirTabelasTCC($conn);

// ----------------------------------------------------------------------------
// 1. CAMADA DE AUTENTICAÇÃO E CONTROLE DE ACESSO
// ----------------------------------------------------------------------------

// Proteção 1: O usuário precisa estar autenticado como Aluno
if (!isset($_SESSION['aluno_id'])) {
    header("Location: ../auth/login_aluno.php");
    exit;
}

// Proteção 2: O ID do curso deve ser fornecido via parâmetro GET
if (!isset($_GET['id'])) {
    header("Location: painel_aluno.php");
    exit;
}

$aluno_id = (int) $_SESSION['aluno_id'];
$curso_id = (int) $_GET['id'];

// Proteção 3: Validação de matrícula ativa e pagamento confirmado
$sql = "SELECT c.id, c.titulo, c.descricao, c.video_url, c.arquivo_pdf, c.arquivo_planilha, c.tipo_aula, m.status_pagamento, m.forma_pagamento 
        FROM matriculas m 
        JOIN cursos c ON m.curso_id = c.id 
        WHERE m.aluno_id = :aluno_id AND m.curso_id = :curso_id";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':aluno_id', $aluno_id, PDO::PARAM_INT);
$stmt->bindParam(':curso_id', $curso_id, PDO::PARAM_INT);
$stmt->execute();
$curso_comprado = $stmt->fetch(PDO::FETCH_ASSOC);

$is_pago = false;
if ($curso_comprado) {
    $status = strtolower($curso_comprado['status_pagamento'] ?? '');
    $is_pago = in_array($status, ['pago', 'confirmado', 'aprovado'], true);
}

// Bloqueia acesso caso a matrícula não esteja regularizada
if (!$curso_comprado || !$is_pago) {
    echo "<script>
            alert('Acesso negado! O pagamento deste curso ainda está pendente ou não foi encontrado.');
            window.location.href = 'painel_aluno.php';
          </script>";
    exit;
}

// ----------------------------------------------------------------------------
// 2. PROCESSAMENTO DE AÇÕES VIA POST (COM PROTEÇÃO CSRF)
// ----------------------------------------------------------------------------

$mensagemFeedback = '';
$tipoFeedback = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // Validação do Token CSRF para impedir ataques de requisições forjadas
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensagemFeedback = 'Erro de segurança (Token CSRF inválido ou expirado). Tente novamente.';
        $tipoFeedback = 'danger';
    } else {
        // AÇÃO 1: Alternar Conclusão da Aula
        if ($acao === 'toggle_conclusao') {
            $aula_id_toggle = (int) ($_POST['aula_id'] ?? 0);
            alternarConclusaoAula($conn, $aluno_id, $curso_id, $aula_id_toggle);
            header("Location: player_aulas.php?id=" . $curso_id . ($aula_id_toggle > 0 ? "&aula_id=" . $aula_id_toggle : ""));
            exit;
        }

        // AÇÃO 2: Enviar Nova Dúvida no Fórum da Aula (Q&A)
        if ($acao === 'enviar_duvida') {
            $duvidaTexto = trim($_POST['duvida_texto'] ?? '');
            $aulaDuvidaId = (int) ($_POST['aula_id'] ?? 0);
            if (!empty($duvidaTexto)) {
                enviarDuvidaAula($conn, $curso_id, $aulaDuvidaId, $aluno_id, $duvidaTexto);
                $mensagemFeedback = 'Sua dúvida foi enviada com sucesso! O professor ou administrador responderá em breve.';
                $tipoFeedback = 'success';
            } else {
                $mensagemFeedback = 'Por favor, escreva a sua dúvida antes de enviar.';
                $tipoFeedback = 'warning';
            }
        }

        // AÇÃO 3: Submissão do Quiz de Fixação Pedagógica
        if ($acao === 'responder_quiz') {
            $perguntasQuiz = obterPerguntasQuiz($conn, $curso_id);
            $totalPerguntas = count($perguntasQuiz);
            $acertos = 0;

            foreach ($perguntasQuiz as $p) {
                $respAluno = trim($_POST['resp_' . $p['id']] ?? '');
                if (strtoupper($respAluno) === strtoupper($p['resposta_correta'])) {
                    $acertos++;
                }
            }

            $notaPercentual = ($totalPerguntas > 0) ? (int) round(($acertos / $totalPerguntas) * 100) : 100;
            $aprovado = ($notaPercentual >= 60);

            salvarResultadoQuiz($conn, $aluno_id, $curso_id, $notaPercentual, $aprovado);

            if ($aprovado) {
                $mensagemFeedback = "Parabéns! Você concluiu a avaliação com {$notaPercentual}% de aproveitamento ({$acertos}/{$totalPerguntas} acertos) e foi APROVADO!";
                $tipoFeedback = 'success';
            } else {
                $mensagemFeedback = "Você obteve {$notaPercentual}% de aproveitamento ({$acertos}/{$totalPerguntas} acertos). Para aprovação é necessário atingir no mínimo 60%. Você pode tentar novamente!";
                $tipoFeedback = 'warning';
            }
        }
    }
}

// ----------------------------------------------------------------------------
// 3. CONSULTA DE DADOS DA AULA SELECIONADA E PROGRESSO
// ----------------------------------------------------------------------------

// Busca lista de aulas adicionais cadastradas no curso
$stmtAulas = $conn->prepare("SELECT * FROM aulas WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
$stmtAulas->execute([$curso_id]);
$aulas = $stmtAulas->fetchAll(PDO::FETCH_ASSOC);

// Determina qual aula está selecionada no momento
$aula_id_selecionada = isset($_GET['aula_id']) ? (int) $_GET['aula_id'] : 0;
$aula_atual = null;

if (!empty($aulas)) {
    if ($aula_id_selecionada > 0) {
        foreach ($aulas as $a) {
            if ((int) $a['id'] === $aula_id_selecionada) {
                $aula_atual = $a;
                break;
            }
        }
    }
    if (!$aula_atual) {
        $aula_atual = $aulas[0];
        $aula_id_selecionada = (int) $aula_atual['id'];
    }
}

// Dados do vídeo/material da aula atual (ou curso principal)
$titulo_aula_exibicao = $aula_atual ? $aula_atual['titulo'] : $curso_comprado['titulo'];
$url_video = $aula_atual ? ($aula_atual['url_video'] ?? '') : ($curso_comprado['video_url'] ?? '');
$pdf_aula = $aula_atual ? ($aula_atual['arquivo_pdf'] ?? '') : ($curso_comprado['arquivo_pdf'] ?? '');
$planilha_aula = $aula_atual ? ($aula_atual['arquivo_planilha'] ?? '') : ($curso_comprado['arquivo_planilha'] ?? '');
$tipo_aula_exibicao = $aula_atual ? ($aula_atual['tipo_aula'] ?? 'video') : ($curso_comprado['tipo_aula'] ?? 'video');

// Converte link comum do YouTube para formato Embed
$embed_url = "";
if (!empty($url_video)) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url_video, $match);
    $youtube_id = $match[1] ?? '';
    if ($youtube_id) {
        $embed_url = "https://www.youtube.com/embed/" . $youtube_id;
    }
}

// Progresso e status da aula
$progresso_curso = obterProgressoCurso($conn, $aluno_id, $curso_id);
$aula_esta_concluida = isAulaConcluida($conn, $aluno_id, $curso_id, $aula_id_selecionada);

// Dúvidas da aula e Perguntas do Quiz
$duvidas = obterDuvidasAula($conn, $curso_id, $aula_id_selecionada);
$perguntasQuiz = obterPerguntasQuiz($conn, $curso_id);
$statusQuiz = verificarAprovacaoQuiz($conn, $aluno_id, $curso_id);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Aula - <?php echo htmlspecialchars($curso_comprado['titulo']); ?> - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <!-- Bootstrap 5 & Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-navy: #111c44;
            --primary-purple: #4318FF;
            --green-success: #05CD99;
            --bg-body: #f8f9fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
        }

        .navbar-custom {
            background-color: var(--primary-navy);
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            max-width: 100%;
            background: #000;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .video-container iframe,
        .video-container video,
        .video-container .no-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .no-video {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .lista-aulas {
            max-height: 520px;
            overflow-y: auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .aula-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
        }

        .aula-item:hover {
            background-color: #f4f7fe;
            color: inherit;
        }

        .aula-item.active {
            background-color: #eef2ff;
            border-left: 4px solid var(--primary-purple);
        }

        .aula-item:last-child {
            border-bottom: none;
        }

        .icon-play {
            width: 32px;
            height: 32px;
            background-color: var(--primary-purple);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .icon-check {
            width: 32px;
            height: 32px;
            background-color: var(--green-success);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            font-weight: bold;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--green-success);
            transition: width 0.3s ease;
        }

        .card-duvida {
            background-color: #f8fafc;
            border-left: 4px solid var(--primary-purple);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .card-resposta {
            background-color: #f0fdf4;
            border-left: 4px solid var(--green-success);
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }
    </style>
</head>

<body>

    <!-- Barra de Navegação Superior do LMS -->
    <nav class="navbar navbar-dark navbar-custom py-3 sticky-top">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="painel_aluno.php" class="btn btn-outline-light btn-sm rounded-3">← Voltar ao Meu Painel</a>
                <span class="navbar-brand fw-bold fs-5 mb-0 d-none d-md-block">🎓 Engenharia Academy LMS</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small d-none d-sm-inline">Aluno:
                    <b><?php echo htmlspecialchars(explode(' ', $_SESSION['aluno_nome'] ?? 'Aluno')[0]); ?></b></span>
                <a href="../auth/logout_aluno.php" class="btn btn-danger btn-sm rounded-3">Sair</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container-fluid px-4 py-4">

        <!-- Mensagens de Notificação / Feedback -->
        <?php if (!empty($mensagemFeedback)): ?>
            <div class="alert alert-<?php echo $tipoFeedback; ?> alert-dismissible fade show rounded-3 shadow-sm mb-4"
                role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($mensagemFeedback); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Título do Curso e Ações Rápidas -->
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($curso_comprado['titulo']); ?></h2>
                <p class="text-muted small mb-0">
                    Aula atual: <strong><?php echo htmlspecialchars($titulo_aula_exibicao); ?></strong> • Formato: <span
                        class="badge bg-secondary"><?php echo ucfirst($tipo_aula_exibicao); ?></span>
                </p>
            </div>

            <!-- Botão de Conclusão da Aula e Certificado -->
            <div class="d-flex gap-2 align-items-center">
                <form method="POST" class="m-0">
                    <?php echo campoCSRF(); ?>
                    <input type="hidden" name="acao" value="toggle_conclusao">
                    <input type="hidden" name="aula_id" value="<?php echo $aula_id_selecionada; ?>">
                    <?php if ($aula_esta_concluida): ?>
                        <button type="submit" class="btn btn-success fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle"></i> Aula Concluída (Desmarcar)
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-outline-success fw-bold shadow-sm">
                            <i class="bi bi-circle"></i> Marcar como Concluída
                        </button>
                    <?php endif; ?>
                </form>

                <?php if ($progresso_curso === 100): ?>
                    <a href="certificado.php?curso_id=<?php echo $curso_id; ?>" target="_blank"
                        class="btn btn-primary fw-bold" style="background-color: #4318FF; border: none;">
                        🎓 Emitir Certificado
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Banner de 100% de Conclusão -->
        <?php if ($progresso_curso === 100): ?>
            <div
                class="alert alert-success d-flex flex-column flex-md-row align-items-center justify-content-between p-3 rounded-3 shadow-sm mb-4">
                <div class="d-flex align-items-center gap-3 mb-2 mb-md-0">
                    <span class="fs-2">🏆</span>
                    <div>
                        <h5 class="fw-bold mb-0 text-success">Parabéns! Você completou 100% da carga horária deste curso!
                        </h5>
                        <p class="mb-0 small text-dark">Seu certificado oficial com QR Code e autenticação digital já está
                            disponível.</p>
                    </div>
                </div>
                <a href="certificado.php?curso_id=<?php echo $curso_id; ?>" target="_blank"
                    class="btn btn-success fw-bold px-4 py-2 shadow-sm text-nowrap">
                    📜 Abrir Certificado de Conclusão
                </a>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Coluna da Esquerda: Vídeo Player, Materiais, Fórum Q&A e Quiz -->
            <div class="col-lg-8">
                <!-- Player de Vídeo Dinâmico -->
                <div class="video-container mb-4">
                    <?php if (!empty($embed_url)): ?>
                        <iframe src="<?php echo $embed_url; ?>" title="Vídeo Aula"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                    <?php else: ?>
                        <div class="no-video">
                            <div>
                                <h4 class="mb-3">Conteúdo Teórico / Material Didático</h4>
                                <p class="text-muted">Acesse as instruções, fórum de dúvidas e materiais de apoio nas abas
                                    abaixo.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Abas Multidisciplinares (Instruções, Materiais, Fórum Q&A e Quiz) -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">
                        <ul class="nav nav-tabs mb-3" id="meusTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold" id="resumo-tab" data-bs-toggle="tab"
                                    data-bs-target="#resumo" type="button">
                                    <i class="bi bi-info-circle"></i> Resumo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold" id="materiais-tab" data-bs-toggle="tab"
                                    data-bs-target="#materiais" type="button">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Materiais de Apoio
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold" id="forum-tab" data-bs-toggle="tab"
                                    data-bs-target="#forum" type="button">
                                    <i class="bi bi-chat-dots"></i> Fórum de Dúvidas (<?php echo count($duvidas); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold" id="quiz-tab" data-bs-toggle="tab"
                                    data-bs-target="#quiz" type="button">
                                    <i class="bi bi-card-checklist"></i> Teste de Fixação (Quiz)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="meusTabsContent">

                            <!-- ABA 1: RESUMO DO CURSO/AULA -->
                            <div class="tab-pane fade show active text-muted" id="resumo">
                                <h6 class="fw-bold text-dark mb-2">
                                    <?php echo htmlspecialchars($titulo_aula_exibicao); ?></h6>
                                <p><?php echo nl2br(htmlspecialchars($curso_comprado['descricao'])); ?></p>
                            </div>

                            <!-- ABA 2: MATERIAIS COMPLEMENTARES -->
                            <div class="tab-pane fade" id="materiais">
                                <h6 class="fw-bold text-dark mb-3">Downloads disponíveis para esta aula:</h6>
                                <?php if (!empty($pdf_aula)): ?>
                                    <a href="<?php echo htmlspecialchars($pdf_aula); ?>" download
                                        class="btn btn-outline-primary me-2 mb-2">
                                        <i class="bi bi-file-pdf"></i> Baixar Material em PDF
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($planilha_aula)): ?>
                                    <a href="<?php echo htmlspecialchars($planilha_aula); ?>" download
                                        class="btn btn-outline-success mb-2">
                                        <i class="bi bi-file-earmark-spreadsheet"></i> Baixar Planilha de Apoio
                                    </a>
                                <?php endif; ?>

                                <?php if (empty($pdf_aula) && empty($planilha_aula)): ?>
                                    <div class="alert alert-light border">Nenhum arquivo complementar anexado a esta aula.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ABA 3: FÓRUM DE DÚVIDAS DA AULA (Q&A) -->
                            <div class="tab-pane fade" id="forum">
                                <div class="mb-4">
                                    <h6 class="fw-bold text-dark mb-2">Tem alguma dúvida sobre esta aula?</h6>
                                    <p class="small text-muted">Envie sua pergunta para a equipe técnica e professores
                                        da Engenharia Academy:</p>
                                    <form method="POST"
                                        action="player_aulas.php?id=<?php echo $curso_id; ?>&aula_id=<?php echo $aula_id_selecionada; ?>">
                                        <?php echo campoCSRF(); ?>
                                        <input type="hidden" name="acao" value="enviar_duvida">
                                        <input type="hidden" name="aula_id" value="<?php echo $aula_id_selecionada; ?>">
                                        <div class="mb-3">
                                            <textarea name="duvida_texto" rows="3" class="form-control"
                                                placeholder="Descreva detalhadamente sua dúvida ou comentário técnico..."
                                                required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4"
                                            style="background-color: #4318FF; border:none;">
                                            <i class="bi bi-send"></i> Enviar Dúvida
                                        </button>
                                    </form>
                                </div>

                                <hr>

                                <h6 class="fw-bold text-dark mb-3">Perguntas Recentes desta Aula:</h6>
                                <?php if (!empty($duvidas)): ?>
                                    <?php foreach ($duvidas as $d): ?>
                                        <div class="card-duvida">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-dark small"><i class="bi bi-person-circle"></i>
                                                    <?php echo htmlspecialchars($d['aluno_nome']); ?></strong>
                                                <span class="text-muted"
                                                    style="font-size: 0.75rem;"><?php echo date('d/m/Y H:i', strtotime($d['data_pergunta'])); ?></span>
                                            </div>
                                            <p class="mb-0 text-dark small">
                                                <?php echo nl2br(htmlspecialchars($d['pergunta'])); ?></p>

                                            <?php if (!empty($d['resposta'])): ?>
                                                <div class="card-resposta">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong class="text-success small"><i class="bi bi-mortarboard-fill"></i>
                                                            Resposta do Instrutor
                                                            (<?php echo htmlspecialchars($d['admin_nome'] ?? 'Equipe Técnica'); ?>):</strong>
                                                        <span class="text-muted"
                                                            style="font-size: 0.75rem;"><?php echo date('d/m/Y H:i', strtotime($d['data_resposta'])); ?></span>
                                                    </div>
                                                    <p class="mb-0 text-dark small">
                                                        <?php echo nl2br(htmlspecialchars($d['resposta'])); ?></p>
                                                </div>
                                            <?php else: ?>
                                                <div class="mt-2 text-warning small">
                                                    <i class="bi bi-hourglass-split"></i> <em>Aguardando resposta do
                                                        professor...</em>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">Nenhuma dúvida registrada nesta aula até o momento. Seja o
                                        primeiro a perguntar!</p>
                                <?php endif; ?>
                            </div>

                            <!-- ABA 4: TESTE DE FIXAÇÃO PEDAGÓGICA (QUIZ) -->
                            <div class="tab-pane fade" id="quiz">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">Questionário de Fixação Pedagógica</h6>
                                        <p class="small text-muted mb-0">Avalie os conhecimentos adquiridos ao longo das
                                            aulas do curso.</p>
                                    </div>
                                    <?php if ($statusQuiz['aprovado']): ?>
                                        <span class="badge bg-success py-2 px-3"><i class="bi bi-check-circle"></i> Aprovado
                                            (<?php echo $statusQuiz['nota']; ?>%)</span>
                                    <?php endif; ?>
                                </div>

                                <form method="POST"
                                    action="player_aulas.php?id=<?php echo $curso_id; ?>&aula_id=<?php echo $aula_id_selecionada; ?>">
                                    <?php echo campoCSRF(); ?>
                                    <input type="hidden" name="acao" value="responder_quiz">

                                    <?php foreach ($perguntasQuiz as $idx => $p): ?>
                                        <div class="card p-3 mb-3 border-0 bg-light rounded-3">
                                            <p class="fw-bold text-dark mb-2">
                                                <?php echo ($idx + 1) . '. ' . htmlspecialchars($p['pergunta']); ?></p>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio"
                                                    name="resp_<?php echo $p['id']; ?>" id="p_<?php echo $p['id']; ?>_a"
                                                    value="A" required>
                                                <label class="form-check-label text-dark small"
                                                    for="p_<?php echo $p['id']; ?>_a">
                                                    A) <?php echo htmlspecialchars($p['opcao_a']); ?>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio"
                                                    name="resp_<?php echo $p['id']; ?>" id="p_<?php echo $p['id']; ?>_b"
                                                    value="B">
                                                <label class="form-check-label text-dark small"
                                                    for="p_<?php echo $p['id']; ?>_b">
                                                    B) <?php echo htmlspecialchars($p['opcao_b']); ?>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio"
                                                    name="resp_<?php echo $p['id']; ?>" id="p_<?php echo $p['id']; ?>_c"
                                                    value="C">
                                                <label class="form-check-label text-dark small"
                                                    for="p_<?php echo $p['id']; ?>_c">
                                                    C) <?php echo htmlspecialchars($p['opcao_c']); ?>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                    name="resp_<?php echo $p['id']; ?>" id="p_<?php echo $p['id']; ?>_d"
                                                    value="D">
                                                <label class="form-check-label text-dark small"
                                                    for="p_<?php echo $p['id']; ?>_d">
                                                    D) <?php echo htmlspecialchars($p['opcao_d']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 rounded-3">
                                        <i class="bi bi-send-check"></i> Enviar Respostas do Quiz
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna da Direita: Lista de Aulas e Progresso -->
            <div class="col-lg-4">

                <!-- Card de Progresso Geral -->
                <div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark small text-uppercase">Progresso Geral</span>
                        <span class="fw-bold text-success small"><?php echo $progresso_curso; ?>% Concluído</span>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $progresso_curso; ?>%;"></div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3 text-dark">Ementa do Curso</h5>

                <div class="lista-aulas">

                    <?php if (!empty($aulas)): ?>
                        <?php foreach ($aulas as $idx => $a):
                            $is_active = ((int) $a['id'] === $aula_id_selecionada);
                            $concluida = isAulaConcluida($conn, $aluno_id, $curso_id, $a['id']);
                            ?>
                            <a href="player_aulas.php?id=<?php echo $curso_id; ?>&aula_id=<?php echo $a['id']; ?>"
                                class="aula-item <?php echo $is_active ? 'active' : ''; ?>">
                                <?php if ($concluida): ?>
                                    <div class="icon-check">✓</div>
                                <?php else: ?>
                                    <div class="icon-play">▶</div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-0" style="font-size: 0.95rem;">
                                        <?php echo ($idx + 1) . '. ' . htmlspecialchars($a['titulo']); ?></h6>
                                    <small class="text-muted"><?php echo $concluida ? 'Concluída' : 'Pendente'; ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Módulo único quando não há submódulos -->
                        <div class="p-3 bg-light border-bottom fw-bold text-secondary text-uppercase"
                            style="font-size: 0.85rem;">
                            Módulo Único de Engenharia
                        </div>

                        <div class="aula-item active">
                            <?php if ($aula_esta_concluida): ?>
                                <div class="icon-check">✓</div>
                            <?php else: ?>
                                <div class="icon-play">▶</div>
                            <?php endif; ?>
                            <div>
                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($curso_comprado['titulo']); ?></h6>
                                <small
                                    class="text-muted"><?php echo $aula_esta_concluida ? 'Concluída' : 'Em andamento'; ?></small>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

    <!-- Script Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>