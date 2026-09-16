<?php
// funcoes_log.php

function registrarLog($conn, $acao, $detalhes = null, $aluno_id = null, $admin_id = null) {
    try {
        // Pega o ID do aluno da sessão se não for passado diretamente
        if (!$aluno_id && isset($_SESSION['aluno_id'])) {
            $aluno_id = $_SESSION['aluno_id'];
        }

        // Pega o IP de quem está acessando
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $sql = "INSERT INTO logs_acesso (aluno_id, admin_id, acao, detalhes, ip, data_hora) 
                VALUES (:aluno_id, :admin_id, :acao, :detalhes, :ip, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':aluno_id', $aluno_id, PDO::PARAM_INT);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->bindParam(':acao', $acao, PDO::PARAM_STR);
        $stmt->bindParam(':detalhes', $detalhes, PDO::PARAM_STR);
        $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        // Silencia erros de log para não interromper a navegação do usuário
        error_log("Erro ao salvar log: " . $e->getMessage());
    }
}
?>