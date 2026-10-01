<?php
/**
 * ============================================================================
 * BLOQUEIO DE ACESSO DIRETO - PASTA DE UPLOADS
 * Impede que usuários acessem diretamente arquivos da pasta de uploads
 * sem passar pela camada de autenticação e validação do sistema.
 * ============================================================================
 */

// Tenta redirecionar para a página inicial do projeto
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove '/uploads' do final da URI para redirecionar para raiz
$nova_locacao = $protocolo . $host . preg_replace('#/uploads.*#', '/', $uri);

header("Location: " . $nova_locacao);
exit;
?>
