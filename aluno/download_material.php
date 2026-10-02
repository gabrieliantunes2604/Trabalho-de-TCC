<?php
session_start();
require '../includes/conexao.php';

if (!isset($_SESSION['aluno_id'])) {
    header('Location: ../auth/login_aluno.php');
    exit;
}

$aluno_id = (int) ($_SESSION['aluno_id'] ?? 0);
$curso_id = isset($_GET['curso_id']) ? (int) $_GET['curso_id'] : 0;
$tipo = isset($_GET['tipo']) ? strtolower((string) $_GET['tipo']) : '';

if ($aluno_id <= 0 || $curso_id <= 0 || !in_array($tipo, ['pdf', 'planilha'], true)) {
    echo "<script>alert('Material inválido ou indisponível.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$sql = "
    SELECT c.titulo, c.arquivo_pdf, c.arquivo_planilha, m.status_pagamento
    FROM matriculas m
    JOIN cursos c ON c.id = m.curso_id
    WHERE m.aluno_id = :aluno_id AND m.curso_id = :curso_id
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->execute([':aluno_id' => $aluno_id, ':curso_id' => $curso_id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    echo "<script>alert('Você não possui acesso a este material.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$status = strtolower((string) ($curso['status_pagamento'] ?? ''));
if (!in_array($status, ['pago', 'confirmado', 'aprovado'], true)) {
    echo "<script>alert('O pagamento deste curso ainda não foi confirmado.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$campo = $tipo === 'pdf' ? 'arquivo_pdf' : 'arquivo_planilha';
$caminhoDb = trim((string) ($curso[$campo] ?? ''));
if ($caminhoDb === '') {
    echo "<script>alert('Este material ainda não foi enviado para o curso.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$baseDir = dirname(__DIR__);
$candidatos = [];

$caminhoNormalizado = str_replace('\\', '/', $caminhoDb);
$caminhoNormalizado = preg_replace('#^\.?/?#', '', $caminhoNormalizado);
$caminhoNormalizado = ltrim($caminhoNormalizado, '/');

$candidatos[] = $baseDir . '/' . $caminhoNormalizado;
$candidatos[] = $baseDir . '/uploads/' . basename($caminhoNormalizado);
$candidatos[] = $baseDir . '/uploads/pdfs/' . basename($caminhoNormalizado);
$candidatos[] = $baseDir . '/uploads/planilhas/' . basename($caminhoNormalizado);

$arquivoReal = null;
foreach ($candidatos as $caminho) {
    $real = realpath($caminho);
    if ($real !== false && is_file($real)) {
        $arquivoReal = $real;
        break;
    }
}

if ($arquivoReal === null) {
    echo "<script>alert('Arquivo não encontrado no servidor.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$ext = strtolower(pathinfo($arquivoReal, PATHINFO_EXTENSION));
if ($tipo === 'pdf' && $ext !== 'pdf') {
    echo "<script>alert('O material disponível não é um PDF válido.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

if ($tipo === 'planilha' && !in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
    echo "<script>alert('O material disponível não é uma planilha válida.'); window.location.href='painel_aluno.php';</script>";
    exit;
}

$nomeArquivo = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $curso['titulo']);
$nomeArquivo = $nomeArquivo ?: 'material';
$nomeArquivo .= ($tipo === 'pdf') ? '_material.pdf' : '_planilha.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: ' . ($tipo === 'pdf' ? 'application/pdf' : 'application/vnd.ms-excel'));
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($arquivoReal));
readfile($arquivoReal);
exit;
