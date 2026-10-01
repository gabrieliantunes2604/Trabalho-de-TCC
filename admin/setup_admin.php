<?php
// Só roda pelo terminal: php admin/setup_admin.php
if (php_sapi_name() !== 'cli') {
http_response_code(404);
exit;
}
require __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/funcoes_config.php';
garantirEstruturaAdmins($conn);
$senhaInicial = bin2hex(random_bytes(6));
$stmt = $conn->prepare("INSERT IGNORE INTO admins (usuario, senha)
VALUES ('admin', ?)");
$stmt->execute([password_hash($senhaInicial, PASSWORD_DEFAULT)]);
echo "Admin criado. Senha temporária: {$senhaInicial}\n";
?>