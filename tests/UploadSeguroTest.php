<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/funcoes_config.php';

class UploadSeguroTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/eng_academy_upload_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileInfo) {
                $filePath = $fileInfo->getPathname();
                if ($fileInfo->isDir()) {
                    rmdir($filePath);
                } else {
                    unlink($filePath);
                }
            }

            rmdir($this->tmpDir);
        }
    }

    public function testUploadSeguroAceitaImagemValida(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAF' . 'c2p9AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ0UkG' . 'AAAAAQMEBQAAAABJRU5ErkJggg==');
        $file = $this->tmpDir . '/curso_imagem.png';
        file_put_contents($file, $png);

        $payload = [
            'name' => 'curso_imagem.png',
            'type' => 'image/png',
            'tmp_name' => $file,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($file),
        ];

        $resultado = uploadSeguro($payload, $this->tmpDir, 'imagem');

        $this->assertTrue($resultado['sucesso'], $resultado['erro']);
        $this->assertFileExists($resultado['caminho']);
        $this->assertStringEndsWith('.png', $resultado['caminho']);
    }

    public function testUploadSeguroAceitaPdfValido(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n5 0 obj\n<< /Length 18 >>\nstream\nBT /F1 18 Tf 72 72 Td (OK) Tj ET\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $file = $this->tmpDir . '/ebook.pdf';
        file_put_contents($file, $pdf);

        $payload = [
            'name' => 'ebook.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $file,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($file),
        ];

        $resultado = uploadSeguro($payload, $this->tmpDir, 'pdf');

        $this->assertTrue($resultado['sucesso'], $resultado['erro']);
        $this->assertFileExists($resultado['caminho']);
        $this->assertStringEndsWith('.pdf', $resultado['caminho']);
    }

    public function testUploadSeguroRejeitaArquivoInvalido(): void
    {
        $file = $this->tmpDir . '/arquivo_invalido.php';
        file_put_contents($file, '<?php echo "teste";');

        $payload = [
            'name' => 'arquivo_invalido.php',
            'type' => 'application/x-php',
            'tmp_name' => $file,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($file),
        ];

        $resultado = uploadSeguro($payload, $this->tmpDir, 'imagem');

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('', $resultado['caminho']);
        $this->assertNotSame('', $resultado['erro']);
    }
}
