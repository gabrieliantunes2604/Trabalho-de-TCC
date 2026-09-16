<?php
/**
 * ============================================================================
 * ENGENHARIA ACADEMY - SISTEMA DE GESTÃO E-LEARNING & ERP
 * Arquivo: funcoes_config.php
 * Finalidade: Biblioteca central de funções utilitárias, regras de segurança (CSRF,
 *             upload seguro), migração dinâmica do banco de dados e lógica pedagógica
 *             (Certificados, Fórum Q&A, Avaliações e Quizzes de fixação).
 * ============================================================================
 */

// ============================================================================
// 1. SEGURANÇA: PROTEÇÃO CONTRA CSRF (Cross-Site Request Forgery)
// ============================================================================

/**
 * Gera ou recupera o token CSRF armazenado na sessão do usuário.
 * O CSRF impede que sites maliciosos enviem requisições forjadas em nome do usuário autenticado.
 *
 * @return string Token hexadecimal seguro
 */
function gerarTokenCSRF()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gera a tag HTML do input hidden contendo o token CSRF pronto para formulários.
 *
 * @return string Tag <input type="hidden" ...>
 */
function campoCSRF()
{
    $token = htmlspecialchars(gerarTokenCSRF(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Valida se o token CSRF recebido via POST confere com o token guardado na sessão.
 * Utiliza hash_equals para evitar ataques de temporização (Timing Attacks).
 *
 * @param string|null $tokenEnviado Token vindo do formulário ($_POST['csrf_token'])
 * @return bool True se o token for válido, False caso contrário
 */
function validarTokenCSRF($tokenEnviado)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($tokenEnviado)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$tokenEnviado);
}

// ============================================================================
// 2. SEGURANÇA: UPLOAD SEGURO DE ARQUIVOS (OWASP File Upload Security)
// ============================================================================

/**
 * Realiza o upload de arquivos de forma segura, validando extensões permitidas,
 * tipo MIME real através do Fileinfo e renomeando o arquivo com hash aleatório
 * para impedir execução de scripts maliciosos (.php, .phtml, .exe, etc.).
 *
 * @param array $fileArray Array do arquivo vindo de $_FILES['campo']
 * @param string $pastaDestino Diretório relativo onde o arquivo será salvo
 * @param string $tipoCategoria Categoria permitida: 'imagem', 'pdf' ou 'planilha'
 * @return array ['sucesso' => bool, 'caminho' => string, 'erro' => string]
 */
function uploadSeguro($fileArray, $pastaDestino = 'uploads/', $tipoCategoria = 'imagem')
{
    if (!isset($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'caminho' => '', 'erro' => 'Nenhum arquivo enviado ou erro no upload.'];
    }

    // Garante que o diretório de destino existe
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    $nomeOriginal = $fileArray['name'];
    $tamanho = $fileArray['size'];
    $tmpName = $fileArray['tmp_name'];

    // 1. Limite de tamanho (Exemplo: 25MB)
    $limiteBytes = 25 * 1024 * 1024;
    if ($tamanho > $limiteBytes) {
        return ['sucesso' => false, 'caminho' => '', 'erro' => 'O arquivo excede o limite máximo permitido de 25MB.'];
    }

    // 2. Lista branca de extensões e tipos MIME permitidos por categoria
    $regras = [
        'imagem' => [
            'extensoes' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'pdf' => [
            'extensoes' => ['pdf'],
            'mimes' => ['application/pdf']
        ],
        'planilha' => [
            'extensoes' => ['xlsx', 'xls', 'csv'],
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
                'text/plain'
            ]
        ]
    ];

    if (!isset($regras[$tipoCategoria])) {
        return ['sucesso' => false, 'caminho' => '', 'erro' => 'Categoria de arquivo inválida.'];
    }

    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    // Valida extensão na lista branca
    if (!in_array($extensao, $regras[$tipoCategoria]['extensoes'], true)) {
        return ['sucesso' => false, 'caminho' => '', 'erro' => 'Extensão de arquivo não permitida para esta categoria.'];
    }

    // Valida tipo MIME real utilizando a extensão Fileinfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mimeReal, $regras[$tipoCategoria]['mimes'], true)) {
            return ['sucesso' => false, 'caminho' => '', 'erro' => "Tipo de arquivo inválido ($mimeReal)."];
        }
    }

    // 3. Gera nome de arquivo completamente aleatório para evitar sobrescrita e injeção de nomes
    $novoNome = $tipoCategoria . '_' . bin2hex(random_bytes(12)) . '.' . $extensao;
    $caminhoFinal = rtrim($pastaDestino, '/') . '/' . $novoNome;

    if (move_uploaded_file($tmpName, $caminhoFinal)) {
        return ['sucesso' => true, 'caminho' => $caminhoFinal, 'erro' => ''];
    }

    return ['sucesso' => false, 'caminho' => '', 'erro' => 'Falha ao mover o arquivo para a pasta final.'];
}

// ============================================================================
// 3. UTILITÁRIOS: AUTENTICAÇÃO E FORMATAÇÃO DE DADOS
// ============================================================================

/**
 * Extrai apenas os números da data de nascimento para uso em PIN / senha inicial.
 */
function pinNascimento($data)
{
    if (empty($data) || $data === '0000-00-00') {
        return '';
    }
    return preg_replace('/\D+/', '', (string)$data);
}

/**
 * Valida se a senha digitada confere com o hash criptográfico ou com o PIN de nascimento.
 */
function senhaConfere($senhaDigitada, $hash, $dataNascimento = null)
{
    $senhaDigitada = trim((string)$senhaDigitada);
    if ($senhaDigitada === '') {
        return false;
    }
    if (!empty($hash) && password_verify($senhaDigitada, $hash)) {
        return true;
    }
    $pin = pinNascimento($dataNascimento);
    return ($pin !== '' && hash_equals($pin, $senhaDigitada));
}

/**
 * Verifica se a senha digitada corresponde ao PIN de reset (data de nascimento).
 */
function ehSenhaReset($senhaDigitada, $dataNascimento)
{
    $pin = pinNascimento($dataNascimento);
    return ($pin !== '' && hash_equals($pin, trim((string)$senhaDigitada)));
}

/**
 * Valida o algoritmo oficial do CPF brasileiro (dígitos verificadores).
 */
function validarCPF($cpf)
{
    $cpf = preg_replace('/\D+/', '', (string)$cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    // Rejeita sequências de dígitos repetidos (ex: 111.111.111-11)
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Valida primeiro e segundo dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }

    return true;
}

/**
 * Aplica a máscara padrão no CPF (000.000.000-00).
 */
function formatarCPF($cpf)
{
    $cpf = preg_replace('/\D+/', '', (string)$cpf);
    if (strlen($cpf) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
    return $cpf;
}

/**
 * Valida o formato de um endereço de e-mail.
 */
function validarEmail($email)
{
    $email = trim((string)$email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Verifica se uma coluna existe em uma tabela do MySQL.
 */
function colunaExiste(PDO $conn, $tabela, $coluna)
{
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$tabela` LIKE :coluna");
        $stmt->execute([':coluna' => $coluna]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================================================
// 4. ESTRUTURA E MIGRAÇÃO AUTOMÁTICA DO BANCO DE DADOS (XAMPP / MySQL)
// ============================================================================

/**
 * Garante a criação e atualização das tabelas de Administradores.
 */
function garantirEstruturaAdmins(PDO $conn)
{
    $conn->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $colunas = [
        'nome' => "ALTER TABLE admins ADD COLUMN nome VARCHAR(100) NULL",
        'email' => "ALTER TABLE admins ADD COLUMN email VARCHAR(100) NULL",
        'telefone' => "ALTER TABLE admins ADD COLUMN telefone VARCHAR(20) NULL",
        'cpf' => "ALTER TABLE admins ADD COLUMN cpf VARCHAR(20) NULL",
        'data_nascimento' => "ALTER TABLE admins ADD COLUMN data_nascimento DATE NULL",
        'token_recuperacao' => "ALTER TABLE admins ADD COLUMN token_recuperacao VARCHAR(255) NULL",
    ];

    foreach ($colunas as $nome => $sql) {
        if (!colunaExiste($conn, 'admins', $nome)) {
            $conn->exec($sql);
        }
    }
}

/**
 * Garante a criação da tabela de progresso dos cursos.
 */
function garantirTabelaProgresso(PDO $conn)
{
    $conn->exec("CREATE TABLE IF NOT EXISTS progresso_cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        curso_id INT NOT NULL,
        aula_id INT NULL DEFAULT 0,
        concluido TINYINT(1) NOT NULL DEFAULT 1,
        data_conclusao DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY aluno_curso_aula (aluno_id, curso_id, aula_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Garante a criação de todas as tabelas adicionais para os pilares do TCC:
 * - Certificados emitidos
 * - Fórum de Dúvidas (Q&A)
 * - Avaliações de Cursos (Rating)
 * - Quizzes e Perguntas de Fixação
 */
function garantirTabelasTCC(PDO $conn)
{
    garantirEstruturaAdmins($conn);
    garantirTabelaProgresso($conn);

    // 1. Tabela de Certificados com Hash e Registro de Carga Horária
    $conn->exec("CREATE TABLE IF NOT EXISTS certificados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        curso_id INT NOT NULL,
        codigo_autenticidade VARCHAR(64) NOT NULL UNIQUE,
        carga_horaria INT DEFAULT 40,
        data_emissao DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY aluno_curso_cert (aluno_id, curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Tabela do Fórum de Dúvidas da Aula (Q&A)
    $conn->exec("CREATE TABLE IF NOT EXISTS duvidas_aulas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        aula_id INT DEFAULT 0,
        aluno_id INT NOT NULL,
        pergunta TEXT NOT NULL,
        resposta TEXT NULL,
        respondido_por_admin_id INT NULL,
        status ENUM('pendente', 'respondida') DEFAULT 'pendente',
        data_pergunta DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_resposta DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Tabela de Avaliações e Depoimentos (Rating de 1 a 5 estrelas)
    $conn->exec("CREATE TABLE IF NOT EXISTS avaliacoes_cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        aluno_id INT NOT NULL,
        nota INT NOT NULL CHECK(nota >= 1 AND nota <= 5),
        comentario TEXT NULL,
        data_avaliacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY aluno_curso_aval (aluno_id, curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Tabela de Perguntas de Fixação (Quizzes)
    $conn->exec("CREATE TABLE IF NOT EXISTS quizzes_perguntas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        pergunta TEXT NOT NULL,
        opcao_a VARCHAR(255) NOT NULL,
        opcao_b VARCHAR(255) NOT NULL,
        opcao_c VARCHAR(255) NOT NULL,
        opcao_d VARCHAR(255) NOT NULL,
        resposta_correta ENUM('A', 'B', 'C', 'D') NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Tabela de Respostas e Desempenho dos Alunos nos Quizzes
    $conn->exec("CREATE TABLE IF NOT EXISTS quizzes_respostas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        curso_id INT NOT NULL,
        nota_percentual INT NOT NULL DEFAULT 0,
        aprovado TINYINT(1) NOT NULL DEFAULT 0,
        data_resposta DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY aluno_curso_quiz (aluno_id, curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ============================================================================
// 5. REGRAS PEDAGÓGICAS: PROGRESSO, AULAS E CERTIFICADOS
// ============================================================================

/**
 * Calcula a porcentagem de conclusão (0 a 100%) de um curso para determinado aluno.
 */
function obterProgressoCurso(PDO $conn, $alunoId, $cursoId)
{
    garantirTabelaProgresso($conn);

    $stmtAulas = $conn->prepare("SELECT COUNT(*) FROM aulas WHERE curso_id = ?");
    $stmtAulas->execute([$cursoId]);
    $totalAulas = (int)$stmtAulas->fetchColumn();

    if ($totalAulas > 0) {
        $stmtConc = $conn->prepare("SELECT COUNT(*) FROM progresso_cursos WHERE aluno_id = ? AND curso_id = ? AND concluido = 1 AND aula_id > 0");
        $stmtConc->execute([$alunoId, $cursoId]);
        $concluidas = (int)$stmtConc->fetchColumn();
        return min(100, (int)round(($concluidas / $totalAulas) * 100));
    } else {
        $stmtConc = $conn->prepare("SELECT COUNT(*) FROM progresso_cursos WHERE aluno_id = ? AND curso_id = ? AND concluido = 1 AND (aula_id = 0 OR aula_id IS NULL)");
        $stmtConc->execute([$alunoId, $cursoId]);
        return ($stmtConc->fetchColumn() > 0) ? 100 : 0;
    }
}

/**
 * Verifica se uma aula específica já foi marcada como concluída pelo aluno.
 */
function isAulaConcluida(PDO $conn, $alunoId, $cursoId, $aulaId = 0)
{
    garantirTabelaProgresso($conn);
    $stmt = $conn->prepare("SELECT concluido FROM progresso_cursos WHERE aluno_id = ? AND curso_id = ? AND (aula_id = ? OR (? = 0 AND aula_id IS NULL))");
    $stmt->execute([$alunoId, $cursoId, $aulaId, $aulaId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($res['concluido']);
}

/**
 * Alterna (marca ou desmarca) o estado de conclusão de uma aula.
 */
function alternarConclusaoAula(PDO $conn, $alunoId, $cursoId, $aulaId = 0)
{
    garantirTabelaProgresso($conn);
    $jaConcluida = isAulaConcluida($conn, $alunoId, $cursoId, $aulaId);
    if ($jaConcluida) {
        $stmt = $conn->prepare("DELETE FROM progresso_cursos WHERE aluno_id = ? AND curso_id = ? AND (aula_id = ? OR (? = 0 AND aula_id IS NULL))");
        $stmt->execute([$alunoId, $cursoId, $aulaId, $aulaId]);
        return false;
    } else {
        $stmt = $conn->prepare("INSERT INTO progresso_cursos (aluno_id, curso_id, aula_id, concluido, data_conclusao) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE concluido = 1, data_conclusao = NOW()");
        $stmt->execute([$alunoId, $cursoId, $aulaId]);
        return true;
    }
}

/**
 * Registra ou recupera o código de autenticidade único de um certificado.
 */
function registrarOuObterCertificado(PDO $conn, $alunoId, $cursoId, $cargaHoraria = 40)
{
    garantirTabelasTCC($conn);

    $codigo = "EA-" . strtoupper(substr(sha1($alunoId . '_' . $cursoId . '_engenharia_academy_tcc'), 0, 12));

    $stmt = $conn->prepare("INSERT INTO certificados (aluno_id, curso_id, codigo_autenticidade, carga_horaria, data_emissao)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE codigo_autenticidade = VALUES(codigo_autenticidade)");
    $stmt->execute([$alunoId, $cursoId, $codigo, $cargaHoraria]);

    return $codigo;
}

// ============================================================================
// 6. FUNÇÕES: FÓRUM DE DÚVIDAS (Q&A)
// ============================================================================

/**
 * Busca todas as dúvidas enviadas para uma aula ou curso.
 */
function obterDuvidasAula(PDO $conn, $cursoId, $aulaId = 0)
{
    garantirTabelasTCC($conn);
    $sql = "SELECT d.*, a.nome AS aluno_nome, adm.nome AS admin_nome
            FROM duvidas_aulas d
            JOIN alunos a ON a.id = d.aluno_id
            LEFT JOIN admins adm ON adm.id = d.respondido_por_admin_id
            WHERE d.curso_id = ? " . ($aulaId > 0 ? "AND d.aula_id = ?" : "") . "
            ORDER BY d.id DESC";
    $stmt = $conn->prepare($sql);
    if ($aulaId > 0) {
        $stmt->execute([$cursoId, $aulaId]);
    } else {
        $stmt->execute([$cursoId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Envia uma nova dúvida feita pelo aluno.
 */
function enviarDuvidaAula(PDO $conn, $cursoId, $aulaId, $alunoId, $pergunta)
{
    garantirTabelasTCC($conn);
    $pergunta = trim((string)$pergunta);
    if (empty($pergunta)) {
        return false;
    }
    $stmt = $conn->prepare("INSERT INTO duvidas_aulas (curso_id, aula_id, aluno_id, pergunta, status, data_pergunta) VALUES (?, ?, ?, ?, 'pendente', NOW())");
    return $stmt->execute([$cursoId, $aulaId, $alunoId, $pergunta]);
}

// ============================================================================
// 7. FUNÇÕES: AVALIAÇÕES E REVIEWS
// ============================================================================

/**
 * Obtém as avaliações, média de estrelas e total de depoimentos de um curso.
 */
function obterAvaliacoesCurso(PDO $conn, $cursoId)
{
    garantirTabelasTCC($conn);

    // Média e contagem
    $stmtMedia = $conn->prepare("SELECT AVG(nota) AS media, COUNT(*) AS total FROM avaliacoes_cursos WHERE curso_id = ?");
    $stmtMedia->execute([$cursoId]);
    $dadosMedia = $stmtMedia->fetch(PDO::FETCH_ASSOC);

    $media = $dadosMedia['media'] ? round((float)$dadosMedia['media'], 1) : 5.0;
    $total = (int)($dadosMedia['total'] ?? 0);

    // Lista de depoimentos recentes
    $stmtDepoimentos = $conn->prepare("SELECT ac.*, a.nome AS aluno_nome
                                      FROM avaliacoes_cursos ac
                                      JOIN alunos a ON a.id = ac.aluno_id
                                      WHERE ac.curso_id = ?
                                      ORDER BY ac.id DESC LIMIT 10");
    $stmtDepoimentos->execute([$cursoId]);
    $depoimentos = $stmtDepoimentos->fetchAll(PDO::FETCH_ASSOC);

    return [
        'media' => $media,
        'total' => $total,
        'depoimentos' => $depoimentos
    ];
}

/**
 * Salva ou atualiza a avaliação de um aluno sobre determinado curso.
 */
function salvarAvaliacaoCurso(PDO $conn, $alunoId, $cursoId, $nota, $comentario = '')
{
    garantirTabelasTCC($conn);
    $nota = max(1, min(5, (int)$nota));
    $comentario = trim((string)$comentario);

    $stmt = $conn->prepare("INSERT INTO avaliacoes_cursos (aluno_id, curso_id, nota, comentario, data_avaliacao)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario), data_avaliacao = NOW()");
    return $stmt->execute([$alunoId, $cursoId, $nota, $comentario]);
}

// ============================================================================
// 8. FUNÇÕES: QUIZZES DE FIXAÇÃO PEDAGÓGICA
// ============================================================================

/**
 * Semeia perguntas de exemplo se o curso ainda não tiver perguntas cadastradas.
 */
function semearPerguntasPadraoQuiz(PDO $conn, $cursoId)
{
    garantirTabelasTCC($conn);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM quizzes_perguntas WHERE curso_id = ?");
    $stmt->execute([$cursoId]);
    if ((int)$stmt->fetchColumn() === 0) {
        $perguntas = [
            [
                'pergunta' => 'Qual é o princípio fundamental aplicado nas metodologias apresentadas neste módulo?',
                'a' => 'Análise empírica sem validação normativa.',
                'b' => 'Aplicação de normas técnicas, critérios de dimensionamento e fatores de segurança.',
                'c' => 'Suposição estocástica sem conferência de memória de cálculo.',
                'd' => 'Otimização visual puramente estética.',
                'correta' => 'B'
            ],
            [
                'pergunta' => 'Em relação à gestão e controle de qualidade técnica, qual é a boa prática indicada?',
                'a' => 'Auditoria e conferência sistemática de planilhas e memoriais.',
                'b' => 'Omissão de dados preliminares.',
                'c' => 'Execução sem verificação de limites de serviço.',
                'd' => 'Adoção de coeficientes arbitrários.',
                'correta' => 'A'
            ],
            [
                'pergunta' => 'Qual documentação técnica comprova formalmente a responsabilidade e conclusão do estudo?',
                'a' => 'Apenas rascunhos informais de projeto.',
                'b' => 'Memória descritiva, ART/RRT e certificado de capacitação com autenticidade verificável.',
                'c' => 'Comprovante genérico sem registro de carga horária.',
                'd' => 'Nenhuma documentação é necessária.',
                'correta' => 'B'
            ]
        ];

        $stmtInsert = $conn->prepare("INSERT INTO quizzes_perguntas (curso_id, pergunta, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($perguntas as $p) {
            $stmtInsert->execute([$cursoId, $p['pergunta'], $p['a'], $p['b'], $p['c'], $p['d'], $p['correta']]);
        }
    }
}

/**
 * Obtém as perguntas do quiz de um curso.
 */
function obterPerguntasQuiz(PDO $conn, $cursoId)
{
    semearPerguntasPadraoQuiz($conn, $cursoId);
    $stmt = $conn->prepare("SELECT * FROM quizzes_perguntas WHERE curso_id = ? ORDER BY id ASC");
    $stmt->execute([$cursoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Salva a pontuação do aluno no quiz de fixação.
 */
function salvarResultadoQuiz(PDO $conn, $alunoId, $cursoId, $notaPercentual, $aprovado)
{
    garantirTabelasTCC($conn);
    $stmt = $conn->prepare("INSERT INTO quizzes_respostas (aluno_id, curso_id, nota_percentual, aprovado, data_resposta)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE nota_percentual = VALUES(nota_percentual), aprovado = VALUES(aprovado), data_resposta = NOW()");
    return $stmt->execute([$alunoId, $cursoId, (int)$notaPercentual, $aprovado ? 1 : 0]);
}

/**
 * Verifica se o aluno foi aprovado no quiz de fixação do curso (ou se está dispensado).
 */
function verificarAprovacaoQuiz(PDO $conn, $alunoId, $cursoId)
{
    garantirTabelasTCC($conn);
    $stmt = $conn->prepare("SELECT aprovado, nota_percentual FROM quizzes_respostas WHERE aluno_id = ? AND curso_id = ?");
    $stmt->execute([$alunoId, $cursoId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res && !empty($res['aprovado'])) {
        return ['aprovado' => true, 'nota' => (int)$res['nota_percentual']];
    }
    return ['aprovado' => false, 'nota' => 0];
}


