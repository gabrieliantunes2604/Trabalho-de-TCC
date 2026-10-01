<?php
/**
 * ============================================================================
 * MIGRAÇÃO DE BANCO DE DADOS - Adicionar Colunas de Auditoria Financeira
 * Adiciona as colunas "valor_pago" e "pago_em" às tabelas de transações
 * ============================================================================
 */

session_start();

// Verifica autenticação
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

require '../includes/conexao.php';

$resultado = [];
$executado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executar_migracao'])) {
    try {
        // Migração 1: Tabela "matriculas"
        try {
            // Tenta adicionar coluna valor_pago
            $sql1 = "ALTER TABLE matriculas ADD COLUMN valor_pago DECIMAL(10,2) NULL AFTER status_pagamento";
            $conn->exec($sql1);
            $resultado[] = [
                'etapa' => 'Tabela: matriculas',
                'acao' => 'Coluna valor_pago',
                'status' => 'success',
                'mensagem' => '✓ Coluna "valor_pago" adicionada com sucesso'
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false) {
                $resultado[] = [
                    'etapa' => 'Tabela: matriculas',
                    'acao' => 'Coluna valor_pago',
                    'status' => 'info',
                    'mensagem' => '⚠️ Coluna "valor_pago" já existe'
                ];
            } else {
                throw $e;
            }
        }

        try {
            // Tenta adicionar coluna pago_em
            $sql2 = "ALTER TABLE matriculas ADD COLUMN pago_em DATETIME NULL AFTER valor_pago";
            $conn->exec($sql2);
            $resultado[] = [
                'etapa' => 'Tabela: matriculas',
                'acao' => 'Coluna pago_em',
                'status' => 'success',
                'mensagem' => '✓ Coluna "pago_em" adicionada com sucesso'
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false) {
                $resultado[] = [
                    'etapa' => 'Tabela: matriculas',
                    'acao' => 'Coluna pago_em',
                    'status' => 'info',
                    'mensagem' => '⚠️ Coluna "pago_em" já existe'
                ];
            } else {
                throw $e;
            }
        }

        // Migração 2: Tabela "compras_ebooks"
        try {
            // Tenta adicionar coluna valor_pago
            $sql3 = "ALTER TABLE compras_ebooks ADD COLUMN valor_pago DECIMAL(10,2) NULL AFTER status_pagamento";
            $conn->exec($sql3);
            $resultado[] = [
                'etapa' => 'Tabela: compras_ebooks',
                'acao' => 'Coluna valor_pago',
                'status' => 'success',
                'mensagem' => '✓ Coluna "valor_pago" adicionada com sucesso'
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false) {
                $resultado[] = [
                    'etapa' => 'Tabela: compras_ebooks',
                    'acao' => 'Coluna valor_pago',
                    'status' => 'info',
                    'mensagem' => '⚠️ Coluna "valor_pago" já existe'
                ];
            } else {
                throw $e;
            }
        }

        try {
            // Tenta adicionar coluna pago_em
            $sql4 = "ALTER TABLE compras_ebooks ADD COLUMN pago_em DATETIME NULL AFTER valor_pago";
            $conn->exec($sql4);
            $resultado[] = [
                'etapa' => 'Tabela: compras_ebooks',
                'acao' => 'Coluna pago_em',
                'status' => 'success',
                'mensagem' => '✓ Coluna "pago_em" adicionada com sucesso'
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false) {
                $resultado[] = [
                    'etapa' => 'Tabela: compras_ebooks',
                    'acao' => 'Coluna pago_em',
                    'status' => 'info',
                    'mensagem' => '⚠️ Coluna "pago_em" já existe'
                ];
            } else {
                throw $e;
            }
        }

        $resultado[] = [
            'status' => 'final_success',
            'mensagem' => '✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!'
        ];

        $executado = true;

    } catch (PDOException $e) {
        $resultado[] = [
            'status' => 'danger',
            'mensagem' => '❌ ERRO na migração: ' . $e->getMessage()
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração de Banco de Dados - Engenharia Academy</title>
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

        .container { max-width: 900px; }
        
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
            border-left: 4px solid;
        }

        .status-box.success {
            background-color: #d4edda;
            border-left-color: #05CD99;
            color: #155724;
        }

        .status-box.info {
            background-color: #d1ecf1;
            border-left-color: #4318FF;
            color: #0c5460;
        }

        .status-box.danger {
            background-color: #f8d7da;
            border-left-color: #FF6B6B;
            color: #721c24;
        }

        .status-box.final_success {
            background-color: #c6f6d5;
            border-left-color: #22543d;
            color: #22543d;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            margin: 10px 0;
            overflow-x: auto;
            border-left: 3px solid #4318FF;
        }

        .btn-migrar {
            background-color: #4318FF;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            transition: 0.3s;
        }

        .btn-migrar:hover {
            background-color: #3311DB;
            color: white;
        }

        .alert-warning-custom {
            background-color: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .navbar-custom {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="navbar-custom text-center">
        <a href="admin.php" class="btn btn-outline-light btn-sm">← Voltar</a>
        <span class="text-white fw-bold ms-3">Migração de Banco de Dados</span>
    </div>

    <h1>🗄️ Migração de Banco de Dados - Auditoria Financeira</h1>

    <!-- CARD 1: EXPLICAÇÃO -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> O Que Será Feito
        </div>
        <div class="card-body">
            <p class="mb-3">Este script adiciona <strong>2 novas colunas</strong> em <strong>2 tabelas</strong> para implementar auditoria financeira:</p>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">📊 Tabela: matriculas</h6>
                    <ul class="small">
                        <li><code>valor_pago</code> (DECIMAL 10,2) - Congela o valor pago</li>
                        <li><code>pago_em</code> (DATETIME) - Registra quando foi aprovado</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">📚 Tabela: compras_ebooks</h6>
                    <ul class="small">
                        <li><code>valor_pago</code> (DECIMAL 10,2) - Congela o valor pago</li>
                        <li><code>pago_em</code> (DATETIME) - Registra quando foi aprovado</li>
                    </ul>
                </div>
            </div>

            <div class="alert-warning-custom mt-3">
                <strong>⚠️ IMPORTANTE:</strong> Se as colunas já existem, elas serão ignoradas. A migração é segura!
            </div>

            <p class="text-muted small mt-3 mb-0">
                Depois da migração, o teste de congelamento funcionará corretamente.
            </p>
        </div>
    </div>

    <!-- CARD 2: EXECUÇÃO -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-play-circle"></i> Executar Migração
        </div>
        <div class="card-body">
            <form method="POST">
                <p class="text-muted mb-3">Clique no botão abaixo para adicionar as colunas ao banco de dados:</p>
                <button type="submit" name="executar_migracao" class="btn btn-migrar btn-lg">
                    <i class="bi bi-database-check"></i> Executar Migração Agora
                </button>
            </form>
        </div>
    </div>

    <!-- CARD 3: RESULTADOS -->
    <?php if ($executado && !empty($resultado)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-check-circle"></i> Resultados da Migração
            </div>
            <div class="card-body">
                <?php foreach ($resultado as $res): ?>
                    <?php if ($res['status'] === 'final_success'): ?>
                        <div class="status-box final_success text-center">
                            <i class="bi bi-check-circle-fill" style="font-size: 1.5rem;"></i><br>
                            <?php echo $res['mensagem']; ?>
                        </div>
                    <?php else: ?>
                        <div class="status-box <?php echo $res['status']; ?>">
                            <?php if (isset($res['etapa']) && isset($res['acao'])): ?>
                                <strong><?php echo $res['etapa']; ?> → <?php echo $res['acao']; ?></strong><br>
                            <?php endif; ?>
                            <?php echo $res['mensagem']; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="alert alert-success mt-4">
                    <h6 class="fw-bold mb-2">✅ Próximos Passos:</h6>
                    <ol class="small mb-0">
                        <li>Volte ao <a href="teste_congelamento_valores.php" class="fw-bold text-decoration-underline">Teste de Congelamento</a></li>
                        <li>Selecione uma matrícula ou e-book pendente</li>
                        <li>Clique em "Testar Congelamento"</li>
                        <li>Veja os valores sendo congelados! 🎉</li>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CARD 4: INSTRUÇÕES SQL -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-code-square"></i> Instruções SQL Executadas
        </div>
        <div class="card-body">
            <h6 class="fw-bold mb-2">Se preferir executar manualmente, use estes comandos:</h6>

            <div class="code-block">
-- Tabela matriculas
ALTER TABLE matriculas ADD COLUMN valor_pago DECIMAL(10,2) NULL AFTER status_pagamento;
ALTER TABLE matriculas ADD COLUMN pago_em DATETIME NULL AFTER valor_pago;

-- Tabela compras_ebooks
ALTER TABLE compras_ebooks ADD COLUMN valor_pago DECIMAL(10,2) NULL AFTER status_pagamento;
ALTER TABLE compras_ebooks ADD COLUMN pago_em DATETIME NULL AFTER valor_pago;
            </div>

            <p class="small text-muted mt-2 mb-0">
                Execute estes comandos no seu phpMyAdmin ou client MySQL se preferir fazer manualmente.
            </p>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div style="text-align: center; color: white; margin-top: 40px; margin-bottom: 20px;">
        <p class="small">Migração de Banco de Dados - Engenharia Academy | <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
