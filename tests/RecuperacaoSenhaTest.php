<?php
use PHPUnit\Framework\TestCase;

class RecuperacaoSenhaTest extends TestCase
{
    private string $tmpScript;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE alunos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT,
            email TEXT UNIQUE,
            senha TEXT,
            token_recuperacao TEXT,
            token_expiracao TEXT
        );");
        $pdo->exec("INSERT INTO alunos (nome, email, senha) VALUES ('Aluno Teste', 'teste@exemplo.com', 'hash-da-senha')");
        $GLOBALS['TEST_CONN'] = $pdo;

        $connFile = __DIR__ . '/../includes/conexao_test.php';
        file_put_contents($connFile, <<<'PHP'
<?php
if (!empty($GLOBALS['TEST_CONN']) && $GLOBALS['TEST_CONN'] instanceof PDO) {
    $conn = $GLOBALS['TEST_CONN'];
} else {
    $conn = new PDO('sqlite::memory:');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("CREATE TABLE alunos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        email TEXT UNIQUE,
        senha TEXT,
        token_recuperacao TEXT,
        token_expiracao TEXT
    );");
}
$GLOBALS['conn'] = $conn;
PHP
        );

        $this->tmpScript = __DIR__ . '/tmp_processa_recuperacao.php';
        $script = <<<'PHP'
<?php
require __DIR__ . '/../includes/conexao_test.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    return false;
}

$tipo = ($_POST['tipo'] ?? 'aluno') === 'admin' ? 'admin' : 'aluno';
$email = trim($_POST['email'] ?? '');

if ($tipo === 'admin') {
    $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email = :email OR usuario = :email LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT id, email FROM alunos WHERE email = :email");
}
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && !empty($usuario['email'])) {
    $token = bin2hex(random_bytes(32));
    $tabela = $tipo === 'admin' ? 'admins' : 'alunos';
    $hashToken = hash('sha256', $token);
    $stmtUpdate = $conn->prepare("UPDATE $tabela SET token_recuperacao = :token_hash, token_expiracao = datetime('now', '+30 minutes') WHERE id = :id");
    $stmtUpdate->execute(['token_hash' => $hashToken, 'id' => $usuario['id']]);

    $link = 'http://localhost/auth/redefinir_senha.php?token=' . $token . '&tipo=' . $tipo;
    __fake_mail($usuario['email'], 'Recuperação de senha', 'Acesse em até 30 min: ' . $link);
    $GLOBALS['LAST_LINK'] = $link;
    $GLOBALS['LAST_HASH'] = $hashToken;
}

return true;

function __fake_mail($to, $subject, $message)
{
    $GLOBALS['LAST_MAIL'] = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
    ];
    return true;
}
PHP;
        file_put_contents($this->tmpScript, $script);
    }

    protected function tearDown(): void
    {
        $connFile = __DIR__ . '/../includes/conexao_test.php';
        if (file_exists($connFile)) {
            unlink($connFile);
        }
        if (file_exists($this->tmpScript)) {
            unlink($this->tmpScript);
        }
        unset($GLOBALS['TEST_CONN'], $GLOBALS['LAST_MAIL'], $GLOBALS['LAST_LINK'], $GLOBALS['LAST_HASH'], $GLOBALS['conn']);
    }

    public function testEnvioDeLinkComTokenHashEValidadeDe30Minutos(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'tipo' => 'aluno',
            'email' => 'teste@exemplo.com',
        ];

        $GLOBALS['LAST_MAIL'] = null;
        ob_start();
        $resultado = include $this->tmpScript;
        $saida = ob_get_clean();

        $this->assertTrue($resultado);
        $this->assertNotNull($GLOBALS['LAST_MAIL']);
        $this->assertSame('teste@exemplo.com', $GLOBALS['LAST_MAIL']['to']);
        $this->assertSame('Recuperação de senha', $GLOBALS['LAST_MAIL']['subject']);
        $this->assertStringContainsString('token=', $GLOBALS['LAST_MAIL']['message']);
        $this->assertStringContainsString('30 min', $GLOBALS['LAST_MAIL']['message']);

        preg_match('/token=([^&\s]+)/', $GLOBALS['LAST_MAIL']['message'], $matches);
        $this->assertArrayHasKey(1, $matches);
        $token = $matches[1];

        $conn = $GLOBALS['conn'];
        $stmt = $conn->query("SELECT token_recuperacao, token_expiracao FROM alunos WHERE email = 'teste@exemplo.com'");
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($aluno['token_recuperacao']);
        $this->assertNotEmpty($aluno['token_expiracao']);
        $this->assertTrue(hash_equals(hash('sha256', $token), $aluno['token_recuperacao']));

        $diffStmt = $conn->query("SELECT (strftime('%s', token_expiracao) - strftime('%s', 'now')) AS diff FROM alunos WHERE email = 'teste@exemplo.com'");
        $diffRow = $diffStmt->fetch(PDO::FETCH_ASSOC);
        $diff = (int) ($diffRow['diff'] ?? 0);

        $this->assertGreaterThanOrEqual(1500, $diff);
        $this->assertLessThanOrEqual(2100, $diff);
        $this->assertStringContainsString('auth/redefinir_senha.php', $GLOBALS['LAST_LINK']);
        $this->assertSame('', $saida);
    }
}
