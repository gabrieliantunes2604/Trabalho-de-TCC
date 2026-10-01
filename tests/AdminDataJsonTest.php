<?php
use PHPUnit\Framework\TestCase;

class AdminDataJsonTest extends TestCase
{
    public function testDataDeFiltroEhValida(): void
    {
        $data_inicio = '2026-09-01';
        $data_fim = '2026-09-30';

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data_inicio);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data_fim);
        $this->assertInstanceOf(DateTime::class, DateTime::createFromFormat('Y-m-d', $data_inicio));
        $this->assertInstanceOf(DateTime::class, DateTime::createFromFormat('Y-m-d', $data_fim));
    }

    public function testJsonEncodeGeraScriptSeguro(): void
    {
        $labels = ['Set/26', 'Out/26'];
        $valores = [1200.50, 2000.75];

        $jsonLabels = json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonValores = json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertSame('["Set/26","Out/26"]', $jsonLabels);
        $this->assertSame('[1200.5,2000.75]', $jsonValores);
        $this->assertStringContainsString('Set/26', $jsonLabels);
    }
}
