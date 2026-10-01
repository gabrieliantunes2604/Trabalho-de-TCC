<?php
/**
 * ============================================================================
 * BLOQUEIO DE ACESSO DIRETO - PASTA DE PDFs
 * Impede que usuários acessem diretamente arquivos PDF sem autenticação
 * ============================================================================
 */

http_response_code(403);
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'erro' => 'Acesso Negado',
    'mensagem' => 'Não é permitido acessar diretamente os arquivos desta pasta.',
    'status' => 403,
    'timestamp' => date('Y-m-d H:i:s')
]);

exit;
?>
