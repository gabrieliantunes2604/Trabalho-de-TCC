<?php
/**
 * ============================================================================
 * TESTE DE SEGURANÇA - CONGELAMENTO DE "valor_pago" E "pago_em"
 * Valida se os campos são corretamente congelados no momento da aprovação
 * ============================================================================
 */

session_start();

// Verifica se é admin
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

require '../includes/conexao.php';

$relatorio = [];
$matricula_testada = null;
$ebook_testado = null;
$teste_executado = false;

// Se solicitar teste de matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_matricula'])) {
    $matricula_id = (int) $_POST['matricula_id'];
    
    if ($matricula_id > 0) {
        // ANTES da aprovação
        $stmt = $conn->prepare("
            SELECT m.id, m.valor_pago as valor_antes, m.pago_em as data_antes, 
                   m.status_pagamento, a.nome, c.preco, c.titulo
            FROM matriculas m
            JOIN alunos a ON m.aluno_id = a.id
            JOIN cursos c ON m.curso_id = c.id
            WHERE m.id = ?
        ");
        $stmt->execute([$matricula_id]);
        $matricula_testada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($matricula_testada) {
            $relatorio[] = [
                'etapa' => 'ANTES DA APROVAÇÃO',
                'tipo' => 'Matrícula (Curso)',
                'id' => $matricula_id,
                'status' => $matricula_testada['status_pagamento'],
                'valor_pago' => $matricula_testada['valor_antes'] ?? 'NULL',
                'pago_em' => $matricula_testada['data_antes'] ?? 'NULL',
                'preco_curso' => $matricula_testada['preco'],
                'aluno' => $matricula_testada['nome'],
                'titulo' => $matricula_testada['titulo'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Simula a aprovação
            if ($matricula_testada['status_pagamento'] !== 'pago' && $matricula_testada['status_pagamento'] !== 'aprovado') {
                $update = $conn->prepare("
                    UPDATE matriculas m 
                    JOIN cursos c ON c.id = m.curso_id 
                    SET m.status_pagamento = 'pago', m.pago_em = NOW(), m.valor_pago = c.preco 
                    WHERE m.id = ?
                ");
                $update->execute([$matricula_id]);
                
                // DEPOIS da aprovação
                $stmt_after = $conn->prepare("
                    SELECT m.id, m.valor_pago as valor_depois, m.pago_em as data_depois, 
                           m.status_pagamento, c.preco
                    FROM matriculas m
                    JOIN cursos c ON m.curso_id = c.id
                    WHERE m.id = ?
                ");
                $stmt_after->execute([$matricula_id]);
                $matricula_depois = $stmt_after->fetch(PDO::FETCH_ASSOC);
                
                $relatorio[] = [
                    'etapa' => 'DEPOIS DA APROVAÇÃO',
                    'tipo' => 'Matrícula (Curso)',
                    'id' => $matricula_id,
                    'status' => $matricula_depois['status_pagamento'],
                    'valor_pago' => $matricula_depois['valor_depois'],
                    'pago_em' => $matricula_depois['data_depois'],
                    'preco_curso' => $matricula_depois['preco'],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                // VALIDAÇÕES
                $valor_congelado = ($matricula_depois['valor_pago'] == $matricula_depois['preco']);
                $data_gravada = ($matricula_depois['data_depois'] !== null);
                $status_atualizado = ($matricula_depois['status_pagamento'] === 'pago');
                
                $relatorio[] = [
                    'validacao' => '1. Valor Pago Congelado?',
                    'esperado' => 'Deve ser igual ao preço do curso (' . $matricula_depois['preco'] . ')',
                    'obtido' => $matricula_depois['valor_pago'],
                    'resultado' => $valor_congelado ? '✓ PASSOU' : '✗ FALHOU',
                    'tipo_resultado' => $valor_congelado ? 'success' : 'danger'
                ];
                
                $relatorio[] = [
                    'validacao' => '2. Data Pago_em Preenchida?',
                    'esperado' => 'Não deve ser NULL',
                    'obtido' => $matricula_depois['data_depois'] ?? 'NULL',
                    'resultado' => $data_gravada ? '✓ PASSOU' : '✗ FALHOU',
                    'tipo_resultado' => $data_gravada ? 'success' : 'danger'
                ];
                
                $relatorio[] = [
                    'validacao' => '3. Status Atualizado?',
                    'esperado' => 'Deve ser "pago"',
                    'obtido' => $matricula_depois['status_pagamento'],
                    'resultado' => $status_atualizado ? '✓ PASSOU' : '✗ FALHOU',
                    'tipo_resultado' => $status_atualizado ? 'success' : 'danger'
                ];
                
                $teste_executado = true;
            }
        }
    }
}

// Se solicitar teste de e-book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_ebook'])) {
    $ebook_id = (int) $_POST['ebook_id'];
    
    if ($ebook_id > 0) {
        // ANTES da aprovação
        // Nota: usa COALESCE para compatibilidade com estrutura atual do BD
        $stmt = $conn->prepare("
            SELECT ce.id, COALESCE(ce.valor_pago, NULL) as valor_antes, COALESCE(ce.pago_em, NULL) as data_antes, 
                   ce.status_pagamento, a.nome, e.preco, e.titulo, ce.data_compra
            FROM compras_ebooks ce
            JOIN alunos a ON ce.aluno_id = a.id
            JOIN ebooks e ON ce.ebook_id = e.id
            WHERE ce.id = ?
        ");
        $stmt->execute([$ebook_id]);
        $ebook_testado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ebook_testado) {
            $relatorio[] = [
                'etapa' => 'ANTES DA APROVAÇÃO',
                'tipo' => 'Compra (E-book)',
                'id' => $ebook_id,
                'status' => $ebook_testado['status_pagamento'],
                'valor_pago' => $ebook_testado['valor_antes'] ?? 'NULL',
                'pago_em' => $ebook_testado['data_antes'] ?? 'NULL',
                'preco_ebook' => $ebook_testado['preco'],
                'aluno' => $ebook_testado['nome'],
                'titulo' => $ebook_testado['titulo'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Simula a aprovação
            if ($ebook_testado['status_pagamento'] !== 'pago' && $ebook_testado['status_pagamento'] !== 'aprovado') {
                $update = $conn->prepare("
                    UPDATE compras_ebooks ce 
                    JOIN ebooks e ON e.id = ce.ebook_id
                    SET ce.status_pagamento = 'pago', ce.pago_em = NOW(), ce.valor_pago = e.preco 
                    WHERE ce.id = ?
                ");
                $update->execute([$ebook_id]);
                
                // DEPOIS da aprovação
                $stmt_after = $conn->prepare("
                    SELECT ce.id, ce.valor_pago as valor_depois, ce.pago_em as data_depois, 
                           ce.status_pagamento, e.preco
                    FROM compras_ebooks ce
                    JOIN ebooks e ON ce.ebook_id = e.id
                    WHERE ce.id = ?
                ");
                $stmt_after->execute([$ebook_id]);
                $ebook_depois = $stmt_after->fetch(PDO::FETCH_ASSOC);
                
                if ($ebook_depois) {
                    $relatorio[] = [
                        'etapa' => 'DEPOIS DA APROVAÇÃO',
                        'tipo' => 'Compra (E-book)',
                        'id' => $ebook_id,
                        'status' => $ebook_depois['status_pagamento'],
                        'valor_pago' => $ebook_depois['valor_depois'],
                        'pago_em' => $ebook_depois['data_depois'],
                        'preco_ebook' => $ebook_depois['preco'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    // VALIDAÇÕES
                    $valor_congelado = ($ebook_depois['valor_pago'] == $ebook_depois['preco']);
                    $data_gravada = ($ebook_depois['data_depois'] !== null);
                    $status_atualizado = ($ebook_depois['status_pagamento'] === 'pago');
                    
                    $relatorio[] = [
                        'validacao' => '1. Valor Pago Congelado?',
                        'esperado' => 'Deve ser igual ao preço do e-book (' . $ebook_depois['preco'] . ')',
                        'obtido' => $ebook_depois['valor_pago'],
                        'resultado' => $valor_congelado ? '✓ PASSOU' : '✗ FALHOU',
                        'tipo_resultado' => $valor_congelado ? 'success' : 'danger'
                    ];
                    
                    $relatorio[] = [
                        'validacao' => '2. Data Pago_em Preenchida?',
                        'esperado' => 'Não deve ser NULL',
                        'obtido' => $ebook_depois['data_depois'] ?? 'NULL',
                        'resultado' => $data_gravada ? '✓ PASSOU' : '✗ FALHOU',
                        'tipo_resultado' => $data_gravada ? 'success' : 'danger'
                    ];
                    
                    $relatorio[] = [
                        'validacao' => '3. Status Atualizado?',
                        'esperado' => 'Deve ser "pago"',
                        'obtido' => $ebook_depois['status_pagamento'],
                        'resultado' => $status_atualizado ? '✓ PASSOU' : '✗ FALHOU',
                        'tipo_resultado' => $status_atualizado ? 'success' : 'danger'
                    ];
                    
                    $teste_executado = true;
                }
            }
        }
    }
}

// Busca matrículas pendentes
$stmt_matriculas = $conn->query("
    SELECT m.id, m.aluno_id, a.nome, c.titulo, m.status_pagamento, c.preco
    FROM matriculas m
    JOIN alunos a ON m.aluno_id = a.id
    JOIN cursos c ON m.curso_id = c.id
    WHERE m.status_pagamento NOT IN ('pago', 'confirmado', 'aprovado')
    ORDER BY m.id DESC
    LIMIT 10
");
$matriculas_pendentes = $stmt_matriculas->fetchAll(PDO::FETCH_ASSOC);

// Busca compras de e-books pendentes
$stmt_ebooks = $conn->query("
    SELECT ce.id, ce.aluno_id, a.nome, e.titulo, ce.status_pagamento, e.preco
    FROM compras_ebooks ce
    JOIN alunos a ON ce.aluno_id = a.id
    JOIN ebooks e ON ce.ebook_id = e.id
    WHERE ce.status_pagamento NOT IN ('pago', 'confirmado', 'aprovado')
    ORDER BY ce.id DESC
    LIMIT 10
");
$ebooks_pendentes = $stmt_ebooks->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Congelamento de Valores - Engenharia Academy</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #111c44 0%, #4318FF 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Inter', sans-serif;
        }

        .container { max-width: 1100px; }
        
        h1 { 
            color: white; 
            font-weight: bold; 
            margin-bottom: 40px; 
            text-align: center;
        }

        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #4318FF 0%, #111c44 100%);
            color: white;
            border-radius: 14px 14px 0 0;
            padding: 20px;
            font-weight: bold;
        }

        .card-body {
            padding: 25px;
        }

        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .status-box.success {
            background-color: #d4edda;
            border-left-color: #05CD99;
            color: #155724;
        }

        .status-box.danger {
            background-color: #f8d7da;
            border-left-color: #FF6B6B;
            color: #721c24;
        }

        .status-box.info {
            background-color: #d1ecf1;
            border-left-color: #4318FF;
            color: #0c5460;
        }

        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
            overflow-x: auto;
            border-left: 3px solid #4318FF;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 14px;
        }

        .btn-teste {
            background-color: #4318FF;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            transition: 0.3s;
        }

        .btn-teste:hover {
            background-color: #3311DB;
            color: white;
        }

        .table-teste {
            width: 100%;
            font-size: 0.95rem;
        }

        .table-teste td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-teste tr:last-child td {
            border-bottom: none;
        }

        .navbar-custom {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .badge {
            font-size: 0.85rem;
            padding: 6px 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="navbar-custom text-center">
        <a href="admin.php" class="btn btn-outline-light btn-sm">← Voltar</a>
        <span class="text-white fw-bold ms-3">Teste de Congelamento de Valores</span>
    </div>

    <h1>❄️ Teste de Congelamento: valor_pago & pago_em</h1>

    <!-- CARD 1: TESTE DE MATRÍCULA -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-book"></i> Teste 1: Congelamento em Matrícula (Curso)
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Selecione uma matrícula pendente de aprovação para testar o congelamento:</p>

            <?php if (!empty($matriculas_pendentes)): ?>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Matrícula Pendente</label>
                            <select name="matricula_id" class="form-select" required>
                                <option value="">-- Selecione uma matrícula --</option>
                                <?php foreach ($matriculas_pendentes as $m): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        [ID: <?php echo $m['id']; ?>] <?php echo htmlspecialchars($m['nome']); ?> → 
                                        <?php echo htmlspecialchars($m['titulo']); ?> 
                                        (R$ <?php echo number_format($m['preco'], 2, ',', '.'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">&nbsp;</label>
                            <button type="submit" name="testar_matricula" class="btn btn-teste w-100">
                                <i class="bi bi-play-circle"></i> Testar Congelamento
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="status-box info">
                    <strong>ℹ️ Nenhuma matrícula pendente</strong><br>
                    Todas as matrículas já foram aprovadas ou pagas. Crie uma nova matrícula para testar.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CARD 2: TESTE DE E-BOOK -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-file-earmark-pdf"></i> Teste 2: Congelamento em Compra de E-book
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Selecione uma compra de e-book pendente de aprovação:</p>

            <?php if (!empty($ebooks_pendentes)): ?>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Compra de E-book Pendente</label>
                            <select name="ebook_id" class="form-select" required>
                                <option value="">-- Selecione um e-book --</option>
                                <?php foreach ($ebooks_pendentes as $e): ?>
                                    <option value="<?php echo $e['id']; ?>">
                                        [ID: <?php echo $e['id']; ?>] <?php echo htmlspecialchars($e['nome']); ?> → 
                                        <?php echo htmlspecialchars($e['titulo']); ?> 
                                        (R$ <?php echo number_format($e['preco'], 2, ',', '.'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">&nbsp;</label>
                            <button type="submit" name="testar_ebook" class="btn btn-teste w-100">
                                <i class="bi bi-play-circle"></i> Testar Congelamento
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="status-box info">
                    <strong>ℹ️ Nenhuma compra de e-book pendente</strong><br>
                    Todas as compras já foram aprovadas ou pagas. Crie uma nova compra para testar.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CARD 3: RESULTADOS -->
    <?php if ($teste_executado && !empty($relatorio)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-check-circle"></i> Resultados do Teste
            </div>
            <div class="card-body">
                
                <!-- Dados ANTES e DEPOIS -->
                <?php 
                $dados_antes = array_values(array_filter($relatorio, function($r) {
                    return isset($r['etapa']) && $r['etapa'] === 'ANTES DA APROVAÇÃO';
                }));

                $dados_depois = array_values(array_filter($relatorio, function($r) {
                    return isset($r['etapa']) && $r['etapa'] === 'DEPOIS DA APROVAÇÃO';
                }));

                $validacoes = array_values(array_filter($relatorio, function($r) {
                    return isset($r['validacao']);
                }));
                ?>

                <h6 class="fw-bold mb-3">📊 Comparação Antes e Depois:</h6>

                <?php foreach ($dados_antes as $idx => $antes): ?>
                    <?php $depois = $dados_depois[$idx] ?? []; ?>
                    <div class="status-box info mb-3">
                        <strong><?php echo $antes['tipo']; ?> - ID: <?php echo $antes['id']; ?></strong><br>
                        <span class="small">Aluno: <?php echo htmlspecialchars($antes['aluno']); ?></span><br>
                        <span class="small">Curso/E-book: <?php echo htmlspecialchars($antes['titulo']); ?></span>
                    </div>

                    <table class="table-teste mb-4">
                        <tr style="background-color: #f8f9fa;">
                            <td><strong>Campo</strong></td>
                            <td><strong>ANTES</strong></td>
                            <td><strong>DEPOIS</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Status Pagamento</strong></td>
                            <td><span class="badge bg-warning"><?php echo $antes['status']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $depois['status'] ?? '—'; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Valor Pago</strong></td>
                            <td>
                                <code><?php echo ($antes['valor_pago'] ?? null) === 'NULL' || empty($antes['valor_pago']) ? '<em style="color: red;">NULL</em>' : 'R$ ' . number_format((float) $antes['valor_pago'], 2, ',', '.'); ?></code>
                            </td>
                            <td>
                                <code><?php echo (!empty($depois['valor_pago'])) ? 'R$ ' . number_format((float) $depois['valor_pago'], 2, ',', '.') : '<em style="color: red;">NULL</em>'; ?></code>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Pago Em</strong></td>
                            <td>
                                <code><?php echo (($antes['pago_em'] ?? 'NULL') === 'NULL' || empty($antes['pago_em'])) ? '<em style="color: red;">NULL</em>' : htmlspecialchars($antes['pago_em']); ?></code>
                            </td>
                            <td>
                                <code><?php echo (!empty($depois['pago_em'])) ? htmlspecialchars($depois['pago_em']) : '<em style="color: red;">NULL</em>'; ?></code>
                            </td>
                        </tr>
                    </table>
                <?php endforeach; ?>

                <hr>

                <h6 class="fw-bold mb-3">✅ Validações de Segurança:</h6>
                
                <?php foreach ($validacoes as $val): ?>
                    <div class="status-box <?php echo $val['tipo_resultado']; ?> mb-2">
                        <strong><?php echo $val['validacao']; ?></strong><br>
                        <span class="small">Esperado: <?php echo $val['esperado']; ?></span><br>
                        <span class="small">Obtido: <code><?php echo htmlspecialchars((string) $val['obtido']); ?></code></span><br>
                        <span class="small mt-1" style="font-size: 1rem;"><?php echo $val['resultado']; ?></span>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    <?php endif; ?>

    <!-- CARD 4: EXPLICAÇÃO TÉCNICA -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-gear"></i> Explicação Técnica
        </div>
        <div class="card-body">
            <h6 class="fw-bold mb-3">O que está sendo testado?</h6>

            <div class="mb-3">
                <strong>1. Congelamento de Valor Pago:</strong>
                <div class="code-block">
UPDATE matriculas m 
JOIN cursos c ON c.id = m.curso_id 
SET m.valor_pago = c.preco  ← Grava o preço EXATO do curso no momento
WHERE m.id = ?
                </div>
                <p class="small text-muted mb-0">
                    ❄️ <strong>Segurança:</strong> O valor não pode ser alterado depois. 
                    Se o administrador quiser mudar o preço do curso, isso não afeta matrículas já aprovadas.
                </p>
            </div>

            <div class="mb-3">
                <strong>2. Congelamento de Data de Pagamento:</strong>
                <div class="code-block">
SET m.pago_em = NOW()  ← Registra exatamente quando foi aprovado
                </div>
                <p class="small text-muted mb-0">
                    📅 <strong>Auditoria:</strong> Fica registrado o momento exato da aprovação 
                    para fins de auditoria e relatórios financeiros.
                </p>
            </div>

            <div class="mb-3">
                <strong>3. Atualização de Status:</strong>
                <div class="code-block">
SET m.status_pagamento = 'pago'  ← Marca como confirmado
                </div>
                <p class="small text-muted mb-0">
                    ✓ <strong>Controle:</strong> Impede que a mesma matrícula seja aprovada duas vezes.
                </p>
            </div>

            <div class="status-box success mt-4">
                <strong>✓ Benefício de Segurança Financeira:</strong><br>
                O congelamento evita que valores sejam alterados retroativamente, 
                mantendo a integridade dos registros financeiros.
            </div>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div style="text-align: center; color: white; margin-top: 40px; margin-bottom: 20px;">
        <p class="small">Teste de Segurança - Engenharia Academy | <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
