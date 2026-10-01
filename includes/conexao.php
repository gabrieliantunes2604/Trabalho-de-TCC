<?php

$envPath = dirname(__FILE__) . "/../.env";

if (!file_exists($envPath)) {
    die("Erro de configuração: arquivo .env não encontrado em: $envPath (verifique se ele não foi salvo como '.env.txt')");
}

$env = parse_ini_file($envPath);

if ($env === false) {
    die("Erro de configuração: não foi possível interpretar o arquivo .env em $envPath (verifique a codificação do arquivo - deve ser texto simples/UTF-8 - e a sintaxe CHAVE=valor).");
}

$camposObrigatorios = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($camposObrigatorios as $campo) {
    if (!array_key_exists($campo, $env)) {
        die("Erro de configuração: chave '$campo' não encontrada no .env. Chaves encontradas: " . implode(', ', array_keys($env)));
    }
}
$servidor = $env['DB_HOST'];
$usuario = $env['DB_USER'];
$senha = $env['DB_PASS'] ;
$banco = $env['DB_NAME'];

try {
    // Cria a conexão PDO
    $conn = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    
    // Configura o PDO para mostrar erros caso algo dê errado
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Se a ponte falhar, o sistema avisa o erro
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}