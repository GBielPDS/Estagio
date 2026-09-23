# Atualização de contas, sessões e integridade

## Regras implementadas

- Contas desativadas perdem acesso na próxima requisição. Reativar a conta não restaura sessões antigas.
- O perfil é consultado no banco em cada requisição protegida. Uma redução de perfil passa a valer no próximo acesso.
- Redefinir a senha pela administração invalida as sessões anteriores. Na troca pelo próprio perfil, a sessão atual é preservada e as demais deixam de funcionar.
- Não é permitido desativar a própria conta nem retirar o último administrador ativo. Transações e bloqueios também protegem contra duas alterações simultâneas.
- Cadastro e reativação são ações administrativas distintas. E-mail duplicado, perfil, status e dados obrigatórios são validados no servidor.
- Formulários de alteração usam token CSRF. Sair exige POST com token válido.
- Alterações de contas e seus registros de auditoria são salvos juntos. Senhas e seus hashes não são incluídos na descrição dos registros.
- Lançamentos, itens, saldo e auditoria são confirmados na mesma transação. Um item inválido rejeita todo o envio. Saídas exigem unidade ativa diferente da Secretaria.
- Alterar saldo pela edição administrativa exige justificativa e cria uma movimentação. Uma edição aberta com saldo antigo não sobrescreve movimentações posteriores.
- Produtos novos com saldo positivo geram entrada de saldo inicial. Ajustes e saldos iniciais aparecem no histórico sem unidade de destino e integram os totais de movimentos dos gráficos; não representam necessariamente distribuição para UBS.

As sessões abertas antes desta atualização precisam de novo login. Revogação é verificada na próxima requisição; não desfaz uma operação já concluída ou que já passou pela autorização.

## Atualizar uma instalação existente

Faça backup do banco antes de atualizar. Não importe `bd/criar-bd.sql` sobre uma instalação em uso.

Na pasta do projeto, com MySQL ativo:

```powershell
C:\xampp\php\php.exe bd/administrar.php migrar
```

O comando acrescenta `usuario.versao_sessao`, preserva os registros e pode ser repetido. O arquivo `bd/migracoes/001-versao-sessao.sql` é uma alternativa manual para execução única. As tabelas precisam usar InnoDB para garantir as transações. O código requer PHP 8.1 ou superior com mysqli.

## Primeira instalação

1. Importe `bd/criar-bd.sql` em um banco novo.
2. Execute `C:\xampp\php\php.exe bd/administrar.php inicializar` no terminal local.
3. Informe nome, e-mail e senha do primeiro administrador. A senha aparece durante a digitação nesse terminal; não há senha padrão no código.
4. Cadastre as UBS reais no banco com apoio do responsável técnico, antes das primeiras saídas. O sistema ainda não oferece uma tela para administrar essas unidades.

A inicialização cria o administrador, a Secretaria de Saúde, seis categorias básicas e um registro de auditoria. Ela é bloqueada se já houver usuários, inclusive inativos, e não pode ser executada pelo navegador. Não importa produtos nem cria UBS fictícias. Novas senhas devem ter de 8 a 72 bytes; caracteres acentuados podem ocupar mais de um byte.

## Verificação

Com PHP, Python e MySQL locais disponíveis:

```powershell
C:\xampp\php\php.exe tests/usuarios_integracao.php
$env:PYTHONUTF8='1'
python tests/http_integracao.py
```

Os testes criam e removem bancos temporários próprios. Precisam de permissão para criar bancos; não executam lançamentos no banco de uso. Incluem revogação de sessões, CSRF, permissões, preservação do último administrador sob concorrência e rollback quando a auditoria falha. A mensagem de falha simulada de auditoria no primeiro teste é esperada.

## Limites e documentação anterior

Esta atualização não reconstrói movimentações ausentes do histórico antigo nem transforma o sistema em controle de saldo individual por UBS. Também não representa uma avaliação completa de segurança ou adequação à LGPD. As decisões sobre tratamento de dados, acesso, retenção e operação serão discutidas separadamente.

Este documento prevalece sobre descrições antigas de contas, sessões, inicialização e ajustes de saldo na documentação técnica e no PDF existentes.
