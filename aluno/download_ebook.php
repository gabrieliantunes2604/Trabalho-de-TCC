<?php
ob_start();
session_start();
require '../includes/conexao.php';

// Proteção: apenas alunos logados
if (!isset($_SESSION['aluno_id']) || !isset($_GET['id'])) {
    die("Acesso negado.");
}

$aluno_id = $_SESSION['aluno_id'];
$ebook_id = $_GET['id'];

// Valida se este aluno comprou este e-book e puxa o status financeiro
$sql = "SELECT e.arquivo_pdf, e.titulo, ce.status_pagamento, ce.pago_em 
        FROM compras_ebooks ce 
        JOIN ebooks e ON ce.ebook_id = e.id 
        WHERE ce.aluno_id = :aluno_id AND ce.ebook_id = :ebook_id";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':aluno_id', $aluno_id, PDO::PARAM_INT);
$stmt->bindParam(':ebook_id', $ebook_id, PDO::PARAM_INT);
$stmt->execute();
$ebook = $stmt->fetch(PDO::FETCH_ASSOC);

// CORREÇÃO DE SEGURANÇA: Exige status 'confirmado' ou 'pago'.
$is_pago = false;
if ($ebook) {
    $status = strtolower(trim($ebook['status_pagamento'] ?? ''));
    if ($status === 'confirmado' || $status === 'pago' || $status === 'aprovado') {
        $is_pago = true;
    }
}

// Trava de segurança
if (!$is_pago) {
    echo "<script>
            alert('Download bloqueado! O pagamento deste E-book ainda não foi confirmado.');
            window.location.href = 'painel_aluno.php';
          </script>";
    exit;
}

$arquivo_db = $ebook['arquivo_pdf'];

// =========================================================================
// CORREÇÃO DO CAMINHO DO ARQUIVO (Limpa duplicações de 'uploads/')
// =========================================================================
// 1. Limpa barras iniciais e referências antigas de pasta
$caminho_limpo = ltrim($arquivo_db, '/.');
$caminho_limpo = str_replace('uploads/', '', $caminho_limpo); // remove duplicados de 'uploads/'

// 2. Lista de possíveis locais onde o arquivo PDF pode estar no seu projeto
$possiveis_caminhos = [
    '../uploads/' . $caminho_limpo,
    '../uploads/pdfs/' . $caminho_limpo,
    '../uploads/ebooks/' . $caminho_limpo,
    '../' . ltrim($arquivo_db, '/.')
];

$caminhoArquivo = null;

// Testa qual dos caminhos realmente existe no servidor
foreach ($possiveis_caminhos as $caminho) {
    if (file_exists($caminho) && !is_dir($caminho)) {
        $caminhoArquivo = $caminho;
        break;
    }
}

// =========================================================================
// EXECUTA O DOWNLOAD SE ENCONTRADO
// =========================================================================
if ($caminhoArquivo && file_exists($caminhoArquivo)) {

    // LOG DE AUDITORIA (Se o arquivo existir)
    if (file_exists('../includes/funcoes_log.php')) {
        require_once '../includes/funcoes_log.php';
        registrarLog($conn, 'download_ebook', "Baixou o e-book: {$ebook['titulo']} (ID: {$ebook_id})", $aluno_id);
    }
    
    if (ob_get_length()) {
        ob_clean();
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    
    $nome_download = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $ebook['titulo']); 
    header('Content-Disposition: attachment; filename="' . $nome_download . '.pdf"');
    
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminhoArquivo));
    
    readfile($caminhoArquivo);
    exit;
    
} else {
    echo "<div style='font-family: sans-serif; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px; margin: 40px auto; max-width: 600px;'>";
    echo "<h3 style='color: #e53e3e;'>Erro ao baixar arquivo</h3>";
    echo "<p>O arquivo PDF não foi localizado na pasta de uploads.</p>";
    echo "<p><b>Valor no Banco de Dados:</b> " . htmlspecialchars($arquivo_db) . "</p>";
    echo "<p><b>O sistema buscou em:</b> <code>" . htmlspecialchars('../uploads/' . $caminho_limpo) . "</code></p>";
    echo "<br><a href='painel_aluno.php' style='background:#4318ff; color:#fff; padding: 10px 18px; text-decoration:none; border-radius: 6px;'>Voltar ao Painel</a>";
    echo "</div>";
}
?>