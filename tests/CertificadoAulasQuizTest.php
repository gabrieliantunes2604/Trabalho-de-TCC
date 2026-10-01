<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/funcoes_config.php';

class CertificadoAulasQuizTest extends TestCase
{
    private PDO $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn->exec("CREATE TABLE aulas (
            id INTEGER PRIMARY KEY,
            curso_id INTEGER,
            titulo TEXT
        );");

        $this->conn->exec("CREATE TABLE progresso_cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            aluno_id INTEGER,
            curso_id INTEGER,
            aula_id INTEGER,
            concluido INTEGER,
            data_conclusao DATETIME
        );");

        $this->conn->exec("CREATE TABLE quizzes_respostas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            aluno_id INTEGER,
            curso_id INTEGER,
            nota_percentual INTEGER,
            aprovado INTEGER,
            data_resposta DATETIME
        );");
    }

    public function testObterProgressoCursoContaApenasAulasReais(): void
    {
        $stmtAulas = $this->conn->prepare("INSERT INTO aulas (id, curso_id, titulo) VALUES (?, ?, ?)");
        $stmtAulas->execute([11, 77, 'Aula 1']);
        $stmtAulas->execute([12, 77, 'Aula 2']);

        $stmtProgresso = $this->conn->prepare("INSERT INTO progresso_cursos (aluno_id, curso_id, aula_id, concluido, data_conclusao) VALUES (?, ?, ?, ?, datetime('now'))");
        $stmtProgresso->execute([1, 77, 0, 1]);
        $stmtProgresso->execute([1, 77, 11, 1]);

        $progresso = obterProgressoCurso($this->conn, 1, 77);

        $this->assertSame(50, $progresso);
    }

    public function testCertificadoExigeQuizAprovadoNoServidor(): void
    {
        $this->conn->exec("INSERT INTO aulas (id, curso_id, titulo) VALUES (21, 88, 'Aula final')");
        $this->conn->exec("INSERT INTO progresso_cursos (aluno_id, curso_id, aula_id, concluido, data_conclusao) VALUES (2, 88, 21, 1, datetime('now'))");

        $progresso = obterProgressoCurso($this->conn, 2, 88);
        $quiz = verificarAprovacaoQuiz($this->conn, 2, 88);
        $podeEmitir = $progresso >= 100 && $quiz['aprovado'];

        $this->assertSame(100, $progresso);
        $this->assertFalse($podeEmitir);
        $this->assertFalse($quiz['aprovado']);

        $this->conn->exec("INSERT INTO quizzes_respostas (aluno_id, curso_id, nota_percentual, aprovado, data_resposta) VALUES (2, 88, 100, 1, datetime('now'))");

        $quizAprovado = verificarAprovacaoQuiz($this->conn, 2, 88);
        $this->assertTrue($quizAprovado['aprovado']);
        $this->assertTrue(obterProgressoCurso($this->conn, 2, 88) >= 100 && $quizAprovado['aprovado']);
    }
}
