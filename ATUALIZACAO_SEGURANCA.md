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

A inicialização cria o administrador, a Secretaria de Saúde, seis categorias básicas e um registro de auditoria. Ela é bloqueada se já houver usuários, inclusive inativos, e não pode ser executada pelo navegador. Não cria UBS fictícias. Se não houver produtos, pergunta no terminal se deve importar o catálogo versionado em `bd/inserir-produtos.sql`: responda `s` ou `n`. Uma resposta inválida é solicitada novamente. Se já houver produtos, preserva-os e pula o catálogo. A importação usa saldo zero e integra a transação da instalação, incluindo auditoria. Categorias e Secretaria são conferidas individualmente; tabelas parcialmente preenchidas não impedem completar as dependências. Novas senhas devem ter de 8 a 72 bytes; caracteres acentuados podem ocupar mais de um byte.

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


## Consolidação da instalação e login

A ferramenta única é `bd/administrar.php`; o antigo `setup.php` web foi retirado. A configuração de conexão é compartilhada com `script/conexao.php`. Não há credenciais iniciais fixas: informe um e-mail válido. A senha ainda fica visível durante a digitação no terminal; leitura oculta multiplataforma não foi implementada. Não execute a instalação em terminal compartilhado ou gravado.

O login conserva a logo, um único formulário, token CSRF, renovação do identificador e versão da sessão. O status de conta desativada só é informado após conferir a senha. Não há exceção para o identificador `admin` sem e-mail válido.

Baixar o código pelo Git não executa instalação nem migração. Em banco com usuários, não execute `inicializar`; aplique apenas migrações necessárias. No ambiente de produção, ajuste credenciais e endereço-base e mantenha ferramentas, SQL e testes fora do acesso público. Os testes atuais usam XAMPP local e bancos descartáveis, não o banco de uso.

O importador aceita o formato atual do catálogo conhecido, sem executar seu comando USE nem aceitar caminhos fornecidos pelo usuário. Mudanças futuras no formato SQL exigem revisar o importador e seus testes; não é um executor genérico de SQL.


## Permissões do Suporte

A tabela abaixo descreve a regra vigente e prevalece sobre descrições anteriores de acesso exclusivo do administrador aos módulos inteiros.

| Ação | Usuário | Suporte | Administrador |
|---|---|---|---|
| Operação existente: cadastro de produtos, entradas, saídas e consultas | Sim | Sim | Sim |
| Editar dados cadastrais e mínimo de produto | Não | Sim | Sim |
| Ajustar saldo pela edição ou excluir produto sem histórico | Não | Não | Sim |
| Consultar e cadastrar UBS | Não | Sim | Sim |
| Editar UBS | Não | Sim | Sim |
| Desativar ou reativar unidade | Não | Não | Sim |
| Editar endereço/telefone da Secretaria | Não | Não | Sim |
| Renomear ou desativar Secretaria | Não | Não | Não |
| Consultar logs | Não | Sim, somente leitura | Sim |
| Consultar usuários ativos e inativos | Não | Sim | Sim |
| Editar nome/e-mail de usuário comum ativo | Não | Sim | Sim |
| Editar outras contas pela administração | Não | Não | Sim |
| Criar contas ou alterar perfil/status | Não | Não | Sim, respeitando proteção do último administrador e autodesativação |
| Editar próprios dados e senha em Meu Perfil | Sim | Sim | Sim |

Suporte não administra outros suportes, administradores nem contas inativas. Alterar e-mail muda o login e gera auditoria; não há notificação automática. As páginas recusam campos administrativos enviados indevidamente. As operações revalidam o autor e o alvo no banco durante a transação. A edição de produtos pelo Suporte não escreve a coluna estoque: uma movimentação ocorrida após abrir a tela não é sobrescrita. Alterações de contas, produtos e unidades continuam vinculadas à auditoria na mesma transação.

Esta atualização não precisa de migração. Os testes HTTP usam bancos temporários e cobrem a hierarquia, campos forjados, perfis alterados após abrir a página, CSRF, preservação do saldo e rollback por falha de auditoria.


O Suporte também pode redefinir a senha de usuários comuns ativos. Campo vazio mantém a senha; senha nova segue o limite de 8 a 72 bytes, invalida as sessões anteriores e gera auditoria específica, sem senha ou hash na descrição. Administradores, outros suportes e contas inativas continuam fora dessa permissão. A própria senha é alterada em Meu Perfil.


## Confirmação de identidade — etapa 1

O histórico de autenticação é exclusivo de administrador e exige a própria senha. A confirmação dura cinco minutos e está vinculada ao usuário, à versão de sessão, ao perfil, à finalidade e à revisão de autorização no banco. Autorizar esse histórico não libera outras finalidades. A auditoria de liberação precisa ser gravada antes da concessão; ela registra uma janela de consulta, não cada filtro consultado.

Login, Logout, acesso ao histórico e falhas/bloqueios de confirmação ficam no histórico protegido, fora dos logs operacionais do Suporte. Dados já exibidos não são apagados pela expiração. As respostas de logs usam `Cache-Control: no-store`; isso não impede cópias ou capturas de tela por pessoas autorizadas.

Após cinco senhas incorretas em uma janela móvel de quinze minutos, novas confirmações ficam bloqueadas por quinze minutos a partir do quinto erro, inclusive em outros navegadores. Conta, finalidade operacional e login normal não são desativados pelo bloqueio. Sucesso zera as falhas anteriores. O contador é associado à conta e protegido por transação/bloqueio de linha. Durante bloqueio, pedidos adicionais não criam logs nem prolongam o prazo. Até cinco erros são auditados por ciclo; se o log falhar, o contador persiste e o acesso continua negado. Auditoria de sucesso indisponível impede qualquer liberação.

Novo login e logout removem autorizações daquela sessão. Alterações de senha, perfil ou status revogam liberações anteriores nas demais sessões pela revisão persistente. A proteção não revoga uma resposta que já foi entregue.

### Migração 002 (a aplicar separadamente no banco de uso)

Arquivo: `bd/migracoes/002-confirmacao-identidade.sql`. Cria somente a tabela InnoDB `confirmacao_identidade`, com uma linha por conta que tenta confirmar: ID do usuário, horários das últimas falhas, término do bloqueio e revisão da autorização. Não contém senhas, hashes, e-mails ou cópia do histórico. O SQL é repetível (`IF NOT EXISTS`) e não altera contas ou logs. Instalações novas já recebem a tabela em `bd/criar-bd.sql`.

O comando `php bd/administrar.php migrar` aplica também essa migração, além da coluna de versão de sessão se estiver faltando. Escolha um caminho: comando ou execução do SQL no banco correto. Preserve um backup conforme o procedimento de implantação.

Antes de aplicar a migração, o login e as funções comuns continuam disponíveis; confirmar o histórico apresenta indisponibilidade e não libera dados. As antigas liberações do histórico não são aproveitadas. A criação dessa tabela no banco real não é executada automaticamente ao abrir o site.

Testes existentes foram ampliados com bancos temporários: migração repetida, ausência de estrutura, autorização no servidor, expiração simulada, escopo, novo login, troca de senha, mudança de perfil, limite entre sessões e concorrência, falha de auditoria e regressões dos módulos. E-mails e suas permissões ainda não foram alterados nesta etapa; criptografia também não foi implementada. Estas medidas não constituem declaração de adequação integral à LGPD.


## Proteção de e-mails — etapa 2

Esta seção substitui descrições anteriores que permitiam editar e-mail em Meu Perfil ou pelo Suporte. A edição comum mantém nome e senha; perfil/status continuam exclusivos do administrador. Cadastro de uma nova conta continua recebendo o e-mail inicial.

- O próprio e-mail permanece visível em Meu Perfil para todos os perfis, sem campo para alterá-lo. Administrador tem link para corrigir o próprio endereço no fluxo protegido.
- Listagem e edição comum mostram apenas máscara produzida no servidor. Não enviam o endereço completo em atributos, campos ocultos ou scripts. A máscara não é anonimização: continua sendo dado pessoal parcialmente oculto.
- Em `pages/editar_usuario.php`, somente administrador pode consultar e corrigir endereços, inclusive de contas inativas. Suporte mantém edição de nome e redefinição de senha somente de usuários comuns ativos.
- Consultar exige a própria senha do administrador, com autorização de cinco minutos na finalidade `consultar_email`. Cada clique de consulta passa por POST com CSRF, verifica o acesso atual e registra o ID da conta consultada antes de devolver o endereço. Abrir a página por GET mantém a máscara mesmo durante a autorização.
- Corrigir exige a própria senha em toda operação (`corrigir_email`), sem aproveitar autorizações anteriores. A autorização transitória é consumida mesmo em caso de falha. Formato/tamanho e duplicidade incluindo contas inativas são validados; alteração e auditoria ficam na mesma transação. Não há envio de e-mail nem comprovação de titularidade do endereço nesta etapa.
- O novo endereço passa a valer no próximo login. A correção não encerra sessões existentes; Meu Perfil lê o endereço atualizado. Senhas, perfis e status mantêm as regras de invalidação anteriores.
- Confirmação do histórico, consulta de e-mail e correção de e-mail são finalidades diferentes. Compartilham o limite persistente de cinco erros em quinze minutos; bloqueio de quinze minutos afeta confirmações sensíveis, não o login normal.
- Eventos de confirmação, consulta e correção ficam no histórico protegido de autenticação, fora do histórico operacional do Suporte. Descrições registram finalidade/ID, nunca os endereços antigo e novo, senhas ou hashes. Erros do serviço de usuários registram somente código, sem argumentos de chamadas.
- Páginas de usuários, edição e perfil enviam `Cache-Control: no-store`. Expirar a autorização não apaga informações já exibidas ou copiadas.

Não há migração nova nesta etapa: reutiliza `confirmacao_identidade`, criada pela migração 002. Cada instalação existente precisa dessa migração; receber arquivos pelo Git não altera o banco automaticamente. E-mails permanecem armazenados como antes, sem criptografia de campo. Esta etapa não representa adequação completa à LGPD.

Testes automatizados usam exclusivamente bancos temporários: verificam ausência de e-mail completo nas respostas comuns, hierarquia, POST forjado, CSRF, escopos e expiração, senha em cada correção, endereço duplicado/inativo, atualização do login, correção do próprio administrador e rollback por falha de auditoria.
