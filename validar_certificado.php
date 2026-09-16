<?php
/**
 * ============================================================================
 * ENGENHARIA ACADEMY - SISTEMA DE GESTÃO E-LEARNING & ERP
 * Arquivo: validar_certificado.php
 * Finalidade: Consulta pública de autenticidade de certificados emitidos pela
 *             plataforma. Permite que empresas, recrutadores e a banca examinadora
 *             validem a veracidade do diploma digitando o código ou lendo o QR Code.
 * ============================================================================
 */

require 'includes/conexao.php';
require_once 'includes/funcoes_config.php';

// Garante a existência das tabelas estruturais
garantirTabelasTCC($conn);

// Obtém o código informado via GET ou POST (com sanitização)
$codigoBuscado = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');
$certificadoValido = false;
$dadosCertificado = null;
$mensagemErro = '';

if (!empty($codigoBuscado)) {
    // 1. Busca na tabela oficial de certificados registrados
    $stmt = $conn->prepare("
        SELECT c.*, a.nome AS aluno_nome, a.cpf AS aluno_cpf, cur.titulo AS curso_titulo, cur.descricao AS curso_descricao
        FROM certificados c
        JOIN alunos a ON a.id = c.aluno_id
        JOIN cursos cur ON cur.id = c.curso_id
        WHERE c.codigo_autenticidade = ?
    ");
    $stmt->execute([$codigoBuscado]);
    $dadosCertificado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dadosCertificado) {
        $certificadoValido = true;
    } else {
        // 2. Fallback de verificação dinâmica caso o certificado tenha sido emitido pelo algoritmo SHA1
        // Formato: EA- + 12 caracteres hexadecimais
        $stmtTodosAlunos = $conn->query("
            SELECT m.aluno_id, m.curso_id, a.nome AS aluno_nome, a.cpf AS aluno_cpf, c.titulo AS curso_titulo
            FROM matriculas m
            JOIN alunos a ON a.id = m.aluno_id
            JOIN cursos c ON c.id = m.curso_id
            WHERE LOWER(IFNULL(m.status_pagamento, '')) IN ('pago', 'aprovado', 'confirmado')
        ");
        $matriculas = $stmtTodosAlunos->fetchAll(PDO::FETCH_ASSOC);

        foreach ($matriculas as $m) {
            $prog = obterProgressoCurso($conn, $m['aluno_id'], $m['curso_id']);
            if ($prog >= 100) {
                $hashCalculado = "EA-" . strtoupper(substr(sha1($m['aluno_id'] . '_' . $m['curso_id'] . '_engenharia_academy_tcc'), 0, 12));
                if (strcasecmp($hashCalculado, $codigoBuscado) === 0) {
                    // Registra na tabela oficial para consultas futuras
                    registrarOuObterCertificado($conn, $m['aluno_id'], $m['curso_id'], 40);
                    $dadosCertificado = [
                        'codigo_autenticidade' => $hashCalculado,
                        'aluno_nome' => $m['aluno_nome'],
                        'aluno_cpf' => $m['aluno_cpf'],
                        'curso_titulo' => $m['curso_titulo'],
                        'carga_horaria' => 40,
                        'data_emissao' => date('Y-m-d H:i:s')
                    ];
                    $certificadoValido = true;
                    break;
                }
            }
        }

        if (!$certificadoValido) {
            $mensagemErro = "Nenhum certificado autêntico foi localizado com o código informado: " . htmlspecialchars($codigoBuscado);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação Pública de Certificados - Engenharia Academy</title>
    <link rel="shortcut icon" href="uploads/logo.ico?v=1" type="image/x-icon">
    <!-- Bootstrap 5 & Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg-body: #f4f7fe;
            --primary-navy: #0b1437;
            --primary-purple: #4318ff;
            --green-success: #05cd99;
            --danger-red: #ee5d50;
            --text-main: #2b3674;
            --text-muted: #a3aed0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-custom {
            background-color: var(--primary-navy);
            padding: 18px 0;
        }

        .card-validador {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            overflow: hidden;
        }

        .seal-success {
            width: 85px;
            height: 85px;
            background: rgba(5, 205, 153, 0.12);
            color: var(--green-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            margin: 0 auto 20px auto;
        }

        .seal-error {
            width: 85px;
            height: 85px;
            background: rgba(238, 93, 80, 0.12);
            color: var(--danger-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            margin: 0 auto 20px auto;
        }

        .badge-status-valid {
            background-color: #d1fae5;
            color: #065f46;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .info-grid {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
        }

        .btn-consultar {
            background-color: var(--primary-purple);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            transition: 0.3s;
        }

        .btn-consultar:hover {
            background-color: #3311db;
            color: #ffffff;
        }
    </style>
</head>

<body>

    <!-- Cabeçalho Público de Navegação -->
    <header class="navbar-custom">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-white text-decoration-none fw-bold fs-4 d-flex align-items-center gap-2">
                🎓 Engenharia Academy
            </a>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light btn-sm rounded-3">Página Inicial</a>
                <!-- CORRIGIDO: Aponta para a subpasta auth/ -->
                <a href="auth/login.php" class="btn btn-light btn-sm fw-semibold rounded-3">Área do Aluno</a>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="container py-5 flex-grow-1" style="max-width: 860px;">

        <!-- Título e Introdução da Validação -->
        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark mb-2">Portal de Verificação de Certificados</h2>
            <p class="text-muted">Consulte a autenticidade, carga horária e emissão oficial dos certificados emitidos
                pela Engenharia Academy.</p>
        </div>

        <!-- Formulário de Busca por Código -->
        <div class="card card-validador p-4 mb-4">
            <form action="validar_certificado.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i
                                class="bi bi-qr-code-scan"></i></span>
                        <input type="text" name="codigo" class="form-control form-control-lg border-start-0 ps-0"
                            placeholder="Ex: EA-4F8A9C12B3E1" value="<?php echo htmlspecialchars($codigoBuscado); ?>"
                            required>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-consultar btn-lg w-100">
                        <i class="bi bi-search"></i> Verificar
                    </button>
                </div>
            </form>
        </div>

        <!-- RESULTADO 1: CERTIFICADO AUTÊNTICO -->
        <?php if ($certificadoValido && $dadosCertificado): ?>
            <div class="card card-validador p-4 p-md-5 text-center">
                <div class="seal-success">
                    <i class="bi bi-patch-check-fill"></i>
                </div>

                <div class="mb-3">
                    <span class="badge-status-valid">
                        <i class="bi bi-shield-check"></i> CERTIFICADO AUTÊNTICO E VÁLIDO
                    </span>
                </div>

                <h3 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($dadosCertificado['aluno_nome']); ?></h3>
                <p class="text-muted small mb-4">Registro verificado na base oficial de dados da Engenharia Academy</p>

                <!-- Grade de Metadados do Certificado -->
                <div class="info-grid text-start mb-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Curso Concluído:</span>
                            <strong
                                class="fs-6 text-dark"><?php echo htmlspecialchars($dadosCertificado['curso_titulo']); ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Carga Horária Certificada:</span>
                            <strong class="fs-6 text-dark"><?php echo (int) ($dadosCertificado['carga_horaria'] ?? 40); ?>
                                Horas de Formação Técnica</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Data de Emissão:</span>
                            <strong
                                class="fs-6 text-dark"><?php echo date('d/m/Y - H:i', strtotime($dadosCertificado['data_emissao'])); ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Código Único de Rastreabilidade (Hash):</span>
                            <code
                                class="fs-6 text-primary fw-bold"><?php echo htmlspecialchars($dadosCertificado['codigo_autenticidade']); ?></code>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <button onclick="window.print()" class="btn btn-outline-secondary rounded-3">
                        <i class="bi bi-printer"></i> Imprimir Laudo de Verificação
                    </button>
                    <a href="validar_certificado.php" class="btn btn-primary rounded-3"
                        style="background-color: #4318FF; border:none;">
                        Consultar Outro Código
                    </a>
                </div>
            </div>

            <!-- RESULTADO 2: CÓDIGO NÃO LOCALIZADO / INVÁLIDO -->
        <?php elseif (!empty($codigoBuscado)): ?>
            <div class="card card-validador p-4 p-md-5 text-center">
                <div class="seal-error">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                </div>
                <h4 class="fw-bold text-danger mb-2">Certificado Não Localizado</h4>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($mensagemErro); ?></p>
                <p class="small text-muted">Certifique-se de que o código digitado corresponde exatamente aos dígitos
                    alfa-numéricos gravados no rodapé do documento emitido.</p>
                <div class="mt-3">
                    <a href="validar_certificado.php" class="btn btn-outline-primary rounded-3">Tentar Novamente</a>
                </div>
            </div>

            <!-- TELA INICIAL: AGUARDANDO CONSULTA -->
        <?php else: ?>
            <div class="card card-validador p-4 text-center">
                <div class="py-4">
                    <i class="bi bi-award fs-1 text-primary mb-3 d-block"></i>
                    <h5 class="fw-bold">Como funciona a validação?</h5>
                    <p class="text-muted mx-auto" style="max-width: 600px;">
                        Cada aluno que conclui 100% da carga horária e é aprovado na Engenharia Academy recebe um
                        certificado digital criptográfico com hash SHA-1 exclusivo. Digite o código no campo acima ou aponte
                        a câmera para o QR Code impresso no documento para verificar a emissão em tempo real.
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Rodapé -->
    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?php echo date('Y'); ?> Engenharia Academy. Todos os direitos reservados. Sistema em conformidade
            com a LGPD.
        </div>
    </footer>

</body>

</html>