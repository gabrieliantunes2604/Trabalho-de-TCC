<?php
// 1. Inicia a sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Verificação de autenticação do Administrador
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

require '../includes/conexao.php';

// 3. Recebe parâmetros GET (suporta curso/ebook e a página de retorno)
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$tipo    = filter_input(INPUT_GET, 'tipo') ?? 'curso';
$ref     = filter_input(INPUT_GET, 'ref') ?? 'admin.php'; // Define para onde vai voltar

// Higieniza o redirecionamento de retorno (evita URLs externas por segurança)
$paginas_permitidas = ['admin.php', 'admin_alunos.php'];
if (!in_array($ref, $paginas_permitidas, true)) {
    $ref = 'admin.php';
}

if (!$item_id) {
    header("Location: " . $ref);
    exit;
}

// 4. Busca os dados de acordo com o Tipo (Curso ou E-book)
if ($tipo === 'ebook') {
    $stmt = $conn->prepare("
        SELECT ce.id, ce.status_pagamento, a.nome AS aluno_nome, a.email AS aluno_email, e.titulo AS item_titulo 
        FROM compras_ebooks ce
        JOIN alunos a ON ce.aluno_id = a.id
        JOIN ebooks e ON ce.ebook_id = e.id
        WHERE ce.id = ?
    ");
} else {
    $stmt = $conn->prepare("
        SELECT m.id, m.status_pagamento, a.nome AS aluno_nome, a.email AS aluno_email, c.titulo AS item_titulo 
        FROM matriculas m
        JOIN alunos a ON m.aluno_id = a.id
        JOIN cursos c ON m.curso_id = c.id
        WHERE m.id = ?
    ");
}

$stmt->execute([$item_id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dados) {
    $status_atual = strtolower(trim($dados['status_pagamento'] ?? ''));

    // Executa a aprovação apenas se ainda não estiver pago
    if (!in_array($status_atual, ['pago', 'aprovado', 'confirmado'], true)) {

        // 5. Atualiza o status no banco de dados para 'pago'
        if ($tipo === 'ebook') {
            $update = $conn->prepare("UPDATE compras_ebooks SET status_pagamento = 'pago' WHERE id = ?");
        } else {
            $update = $conn->prepare("UPDATE matriculas SET status_pagamento = 'pago' WHERE id = ?");
        }
        $update->execute([$item_id]);

        // 6. Prepara o envio do e-mail de confirmação
        $para = filter_var($dados['aluno_email'], FILTER_SANITIZE_EMAIL);

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
        $urlLogin = $baseUrl . "/auth/login_aluno.php";

        $nomeAluno  = htmlspecialchars($dados['aluno_nome'], ENT_QUOTES, 'UTF-8');
        $tituloItem = htmlspecialchars($dados['item_titulo'], ENT_QUOTES, 'UTF-8');
        $rotulo     = ($tipo === 'ebook') ? 'e-book' : 'curso';

        $assunto = "🎉 Seu acesso ao {$rotulo} " . $tituloItem . " foi liberado!";

        $mensagem = <<<HTML
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Pagamento Confirmado</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f9f9f9; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;'>
                <h2 style='color: #4318FF;'>Olá, {$nomeAluno}!</h2>
                <p>Confirmamos o recebimento do seu pagamento para o {$rotulo}: <strong>{$tituloItem}</strong>.</p>
                <p>Seu acesso já está 100% liberado na nossa plataforma!</p>
                <br>
                <p style='text-align: center;'>
                    <a href='{$urlLogin}' style='background-color: #05CD99; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Acessar Meu Painel</a>
                </p>
                <br>
                <p>Bons estudos!<br><strong>Equipe Engenharia Academy</strong></p>
            </div>
        </body>
        </html>
        HTML;

        $headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=utf-8',
            'From' => 'Engenharia Academy <no-reply@' . ($host !== 'localhost' ? $host : 'engenhariaacademy.com.br') . '>',
            'Reply-To' => 'suporte@' . ($host !== 'localhost' ? $host : 'engenhariaacademy.com.br'),
            'X-Mailer' => 'PHP/' . phpversion()
        ];

        $headersFormatted = '';
        foreach ($headers as $key => $val) {
            $headersFormatted .= "{$key}: {$val}\r\n";
        }

        @mail($para, $assunto, $mensagem, $headersFormatted);
    }
}

// 7. Redireciona de volta dinamicamente para a tela de origem
header("Location: " . $ref . "?msg=aprovado");
exit;