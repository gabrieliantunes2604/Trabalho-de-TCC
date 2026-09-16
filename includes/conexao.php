<?php
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "engcursos_db";

try {
    // Cria a conexão PDO
    $conn = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    
    // Configura o PDO para mostrar erros caso algo dê errado
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Se a ponte falhar, o sistema avisa o erro
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}