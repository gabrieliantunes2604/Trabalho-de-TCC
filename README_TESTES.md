README - Testes e comportamento de senhaConfere
=============================================

Resumo rápido
- `senhaConfere($senhaDigitada, $hash, $dataNascimento = null)` valida apenas senhas armazenadas como hash (ex.: `password_hash`).
- `ehSenhaReset($senhaDigitada, $dataNascimento)` valida o PIN derivado da `data_nascimento` (formato aceito: `YYYY-MM-DD`, `YYYY/MM/DD`, `YYYYMMDD`), usado como senha temporária/reset.

Regras
- Não assuma que `senhaConfere()` aceita PINs: use `ehSenhaReset()` para esse propósito.

Como rodar os testes (Windows + XAMPP)
1. Abra PowerShell e vá para a pasta do projeto:
   cd "c:\xampp\htdocs\projeto concluído atualizado\eng_academy"
2. Rodar todos os testes:
   c:\xampp\php\php.exe phpunit.phar tests
3. Rodar um teste específico:
   c:\xampp\php\php.exe phpunit.phar tests\FuncoesConfigTest.php

Notas
- Arquivo de conexão de teste criado: `includes/conexao_test.php` (usado pelos testes isolados).
- Se preferir que `senhaConfere()` tente hash e então PIN automaticamente, avise que eu ajusto a função.
