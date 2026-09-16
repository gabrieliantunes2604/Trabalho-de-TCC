<?php
require '../includes/conexao.php';

try {
    // Cria a tabela de administradores
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL
    )";
    $conn->exec($sql);

    // Criptografa a senha '123456' usando o algoritmo BCRYPT (Padrão ouro de segurança)
    $senhaSegura = password_hash('123456', PASSWORD_DEFAULT);

    // Insere o usuário no banco (ignorando se já existir)
    $sqlInsert = "INSERT IGNORE INTO admins (usuario, senha) VALUES ('admin', :senha)";
    $stmt = $conn->prepare($sqlInsert);
    $stmt->bindParam(':senha', $senhaSegura);
    $stmt->execute();

    echo "<h2 style='color: green; font-family: sans-serif;'>Instalação concluída! Tabela 'admins' criada e usuário 'admin' (senha: 123456) cadastrado com sucesso.</h2>";
} catch(PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>