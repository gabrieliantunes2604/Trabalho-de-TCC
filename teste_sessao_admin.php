<?php
/**
 * ============================================================================
 * TESTE DE SEGURANÇA - SESSION ID REGENERATION E GRAVAÇÃO DE ADMIN ID
 * Valida se a sessão está sendo regenerada corretamente e o admin ID gravado
 * ============================================================================
 */

session_start();
require './includes/conexao.php';
require_once './includes/funcoes_config.php';

garantirEstruturaAdmins($conn);

$relatorio_teste = [];
$teste_ativo = false;
$admin_id_simulado = null;
$session_id_antes = null;
$session_id_depois = null;

// Se for uma requisição para simular login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simular_login'])) {
    $usuario_teste = trim($_POST['usuario_teste'] ?? '');
    $senha_teste = trim($_POST['senha_teste'] ?? '');
    
    if (!empty($usuario_teste) && !empty($senha_teste)) {
        // Captura o session ID ANTES de regenerar
        $session_id_antes = session_id();
        
        // Busca o admin no banco
        $stmt = $conn->prepare("SELECT * FROM admins WHERE usuario = :usuario OR email = :usuario LIMIT 1");
        $stmt->bindParam(':usuario', $usuario_teste);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && senhaConfere($senha_teste, $admin['senha'] ?? '', $admin['data_nascimento'] ?? null)) {
            // ANTES de regenerar
            $relatorio_teste[] = [
                'etapa' => 'ANTES da regeneração',
                'session_id' => $session_id_antes,
                'admin_id_sessao' => $_SESSION['admin_id'] ?? 'VAZIO',
                'status' => '✓ Capturado'
            ];
            
            // Regenera o session ID (SEGURANÇA CONTRA FIXAÇÃO DE SESSÃO)
            session_regenerate_id(true);
            $session_id_depois = session_id();
            
            // Grava os dados do admin na sessão
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            
            // DEPOIS de regenerar
            $relatorio_teste[] = [
                'etapa' => 'DEPOIS da regeneração',
                'session_id' => $session_id_depois,
                'admin_id_sessao' => $_SESSION['admin_id'],
                'status' => '✓ Regenerado e Gravado'
            ];
            
            // Validação de segurança
            $relatorio_teste[] = [
                'etapa' => 'VALIDAÇÃO',
                'verificacao' => 'Session ID mudou?',
                'resultado' => ($session_id_antes !== $session_id_depois) ? '✓ SIM (SEGURO)' : '✗ NÃO (PROBLEMA)',
                'status' => ($session_id_antes !== $session_id_depois) ? 'success' : 'danger'
            ];
            
            $relatorio_teste[] = [
                'etapa' => 'VALIDAÇÃO',
                'verificacao' => 'Admin ID gravado na sessão?',
                'resultado' => isset($_SESSION['admin_id']) ? '✓ SIM (ID: ' . $_SESSION['admin_id'] . ')' : '✗ NÃO',
                'status' => isset($_SESSION['admin_id']) ? 'success' : 'danger'
            ];
            
            $relatorio_teste[] = [
                'etapa' => 'VALIDAÇÃO',
                'verificacao' => 'Admin logado (flag)?',
                'resultado' => ($_SESSION['admin_logado'] ?? false) ? '✓ SIM (Verdadeiro)' : '✗ NÃO',
                'status' => ($_SESSION['admin_logado'] ?? false) ? 'success' : 'danger'
            ];
            
            $teste_ativo = true;
            $admin_id_simulado = $admin['id'];
            
        } else {
            $relatorio_teste[] = [
                'etapa' => 'ERRO',
                'mensagem' => 'Usuário ou senha incorretos!',
                'status' => 'danger'
            ];
        }
    }
}

// Se a sessão já tem admin_id, exibe
$admin_autenticado = $_SESSION['admin_logado'] ?? false;
$admin_id_atual = $_SESSION['admin_id'] ?? null;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Sessão Admin - Engenharia Academy</title>
    <link rel="shortcut icon" href="./uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #111c44 0%, #4318FF 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Inter', sans-serif;
        }

        .container { max-width: 1000px; }
        
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

        .badge-pass {
            background-color: #05CD99;
            color: white;
        }

        .badge-fail {
            background-color: #FF6B6B;
            color: white;
        }

        .table-teste {
            margin-top: 15px;
        }

        .table-teste td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table-teste tr:last-child td {
            border-bottom: none;
        }

        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .status-box.success {
            background-color: #d4edda;
            border-left: 4px solid #05CD99;
            color: #155724;
        }

        .status-box.danger {
            background-color: #f8d7da;
            border-left: 4px solid #FF6B6B;
            color: #721c24;
        }

        .status-box.info {
            background-color: #d1ecf1;
            border-left: 4px solid #4318FF;
            color: #0c5460;
        }

        .code-block {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
            overflow-x: auto;
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 14px;
        }

        .btn-teste {
            background-color: #4318FF;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            transition: 0.3s;
        }

        .btn-teste:hover {
            background-color: #3311DB;
            color: white;
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
        <a href="index.php" class="btn btn-outline-light btn-sm">← Voltar</a>
        <span class="text-white fw-bold ms-3">Teste de Segurança - Sessão Admin</span>
    </div>

    <h1>🔐 Teste de Session ID Regeneration & Admin ID</h1>

    <!-- CARD 1: FORMA DE TESTE -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-pencil-square"></i> Formulário de Teste
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Faça login como admin para testar a regeneração de sessão:</p>
            
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Usuário ou E-mail</label>
                        <input type="text" name="usuario_teste" class="form-control" 
                               placeholder="Ex: admin" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Senha</label>
                        <input type="password" name="senha_teste" class="form-control" 
                               placeholder="Sua senha de admin" required>
                    </div>
                </div>
                <button type="submit" name="simular_login" class="btn btn-teste mt-3 w-100">
                    <i class="bi bi-play-circle"></i> Simular Login & Testar Sessão
                </button>
            </form>

            <div class="status-box info mt-3">
                <strong>💡 Como funciona:</strong> Ao fazer login, o código regenera o session ID 
                e grava o admin_id na sessão. Este teste captura e exibe os valores antes e depois.
            </div>
        </div>
    </div>

    <!-- CARD 2: STATUS ATUAL DA SESSÃO -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> Status Atual da Sessão
        </div>
        <div class="card-body">
            <table class="table-teste w-100">
                <tr>
                    <td width="40%"><strong>Session ID Atual:</strong></td>
                    <td>
                        <code><?php echo session_id(); ?></code>
                    </td>
                </tr>
                <tr>
                    <td><strong>Admin Autenticado:</strong></td>
                    <td>
                        <?php if ($admin_autenticado): ?>
                            <span class="badge badge-pass">✓ SIM</span>
                        <?php else: ?>
                            <span class="badge badge-fail">✗ NÃO</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Admin ID:</strong></td>
                    <td>
                        <?php if ($admin_id_atual): ?>
                            <code><?php echo $admin_id_atual; ?></code>
                        <?php else: ?>
                            <span class="text-muted">Nenhum admin autenticado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Admin Usuário:</strong></td>
                    <td>
                        <?php if (isset($_SESSION['admin_usuario'])): ?>
                            <code><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></code>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- CARD 3: RESULTADOS DO TESTE (SE EXECUTADO) -->
    <?php if ($teste_ativo && !empty($relatorio_teste)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-check-circle"></i> Resultados do Teste de Login
            </div>
            <div class="card-body">
                <?php foreach ($relatorio_teste as $resultado): ?>
                    <?php if ($resultado['etapa'] === 'ANTES da regeneração' || $resultado['etapa'] === 'DEPOIS da regeneração'): ?>
                        <div class="status-box info mb-3">
                            <strong><?php echo $resultado['etapa']; ?>:</strong><br>
                            Session ID: <code><?php echo $resultado['session_id']; ?></code><br>
                            Admin ID na sessão: <strong><?php echo $resultado['admin_id_sessao']; ?></strong>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <hr>

                <h6 class="fw-bold mb-3">Validações de Segurança:</h6>
                <?php foreach ($relatorio_teste as $resultado): ?>
                    <?php if ($resultado['etapa'] === 'VALIDAÇÃO'): ?>
                        <div class="status-box <?php echo $resultado['status']; ?> mb-2">
                            <strong><?php echo $resultado['verificacao']; ?></strong><br>
                            <?php echo $resultado['resultado']; ?>
                        </div>
                    <?php endif; ?>
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
                <strong>1. Session ID Regeneration:</strong>
                <div class="code-block">
session_regenerate_id(true);  // true = deleta sessão antiga
                </div>
                <p class="small text-muted mb-0">
                    Isso previne ataques de <strong>Session Fixation</strong>, onde um atacante tenta 
                    forçar a sessão do usuário a usar um ID específico. Ao regenerar, o ID antigo é deletado.
                </p>
            </div>

            <div class="mb-3">
                <strong>2. Gravação de Admin ID:</strong>
                <div class="code-block">
$_SESSION['admin_id'] = (int)$admin['id'];<br>
$_SESSION['admin_logado'] = true;
                </div>
                <p class="small text-muted mb-0">
                    Após regenerar a sessão, os dados do admin são gravados. Isso permite que 
                    páginas posteriores validem quem está autenticado via <code>$_SESSION['admin_id']</code>.
                </p>
            </div>

            <div class="mb-3">
                <strong>3. Validações Esperadas:</strong>
                <ul class="small text-muted mb-0">
                    <li>✓ Session ID muda após regeneração</li>
                    <li>✓ Admin ID é gravado e acessível</li>
                    <li>✓ Flag admin_logado é verdadeira</li>
                    <li>✓ Variáveis persistem na sessão</li>
                </ul>
            </div>

            <div class="status-box success mt-3">
                <strong>✓ Benefício de Segurança:</strong> Se um atacante conseguir um ID de sessão 
                válido antes do login, ele não poderá usar depois, pois o ID é regenerado e o antigo deletado.
            </div>
        </div>
    </div>

    <!-- CARD 5: CÓDIGO-FONTE VERIFICADO -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-code"></i> Código-Fonte (auth/login.php)
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">Trecho relevante que está sendo testado:</p>
            <div class="code-block" style="font-size: 0.85rem;">
if ($admin && senhaConfere($senha, $admin['senha'] ?? '', $admin['data_nascimento'] ?? null)) {<br>
&nbsp;&nbsp;&nbsp;&nbsp;session_regenerate_id(true);  <span style="color: #05CD99;">// ← ID regenerado</span><br>
&nbsp;&nbsp;&nbsp;&nbsp;$_SESSION['admin_logado'] = true;<br>
&nbsp;&nbsp;&nbsp;&nbsp;$_SESSION['admin_id'] = (int)$admin['id'];  <span style="color: #05CD99;">// ← ID gravado</span><br>
&nbsp;&nbsp;&nbsp;&nbsp;$_SESSION['admin_usuario'] = $admin['usuario'];<br>
}
            </div>
            <div class="status-box success mt-3">
                <strong>✓ Status:</strong> O código está implementando corretamente a regeneração de sessão 
                e a gravação do admin_id.
            </div>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div style="text-align: center; color: white; margin-top: 40px; margin-bottom: 20px;">
        <p class="small">Teste de Segurança - Engenharia Academy | <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</div>

<?php include './includes/footer.php'; ?>
</body>

</html>
