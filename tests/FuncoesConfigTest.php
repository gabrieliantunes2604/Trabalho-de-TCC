<?php
use PHPUnit\Framework\TestCase;

/**
 * Testes para funções em `includes/funcoes_config.php`.
 * Nota: a função `senhaConfere()` valida apenas senhas com hash armazenado.
 * O PIN derivado da data de nascimento é verificado pela função `ehSenhaReset()`.
 */
require_once __DIR__ . '/../includes/funcoes_config.php';

class FuncoesConfigTest extends TestCase
{
    public function testSenhaConfereWithHash()
    {
        $password = 'minhaSenhaSegura123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertTrue(senhaConfere($password, $hash, null));
    }

    public function testSenhaConfereWithHashOnlyDoesNotAcceptPin()
    {
        $data = '1990-05-12';
        $pin = preg_replace('/\D+/', '', $data);

        $this->assertFalse(senhaConfere($pin, '', $data));
        $this->assertFalse(senhaConfere($pin, 'hash-invalido', $data));
    }

    public function testSenhaConfereEmptyReturnsFalse()
    {
        $this->assertFalse(senhaConfere('', '', null));
    }

    public function testEhSenhaReset()
    {
        $data = '2000-01-02';
        $pin = preg_replace('/\D+/', '', $data);
        $this->assertTrue(ehSenhaReset($pin, $data));
        $this->assertFalse(ehSenhaReset('0000', $data));
    }

    public function testEhSenhaResetVariousFormats()
    {
        // Same effective pin across different date formats
        $data1 = '1990-05-12';
        $data2 = '1990/05/12';
        $data3 = '19900512';
        $pin = '19900512';

        $this->assertTrue(ehSenhaReset($pin, $data1));
        $this->assertTrue(ehSenhaReset($pin, $data2));
        $this->assertTrue(ehSenhaReset($pin, $data3));
    }

    public function testEhSenhaResetRejectsFormattedInputAndEmpty()
    {
        $data = '1990-05-12';
        // If senhaDigitada contains non-digit formatting it should not match
        $this->assertFalse(ehSenhaReset('1990-05-12', $data));
        $this->assertFalse(ehSenhaReset('', $data));
    }

    public function testEhSenhaResetRejectsZeroDate()
    {
        $data = '0000-00-00';
        $this->assertFalse(ehSenhaReset('00000000', $data));
    }
}
