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


## Alteração protegida de senhas

Esta seção substitui a orientação antiga de deixar a senha vazia na edição cadastral. Meu Perfil separa Salvar dados de Alterar minha senha. Editar usuário separa os dados da conta de Redefinir senha. Administrador que edita a própria conta recebe um link para Meu Perfil.

- Troca própria exige senha atual, nova senha e confirmação. Administrador e Suporte confirmam a própria senha ao redefinir a de terceiros. Suporte continua limitado a usuários comuns ativos; não alcança administradores, outros suportes ou inativos.
- As finalidades `alterar_propria_senha` e `redefinir_senha` não liberam consulta de e-mails ou histórico. Cada chamada confirma novamente o operador; a autorização transitória é removida ao terminar, inclusive após falhas. Não existe janela para várias trocas sem senha.
- O limite persistente existente é compartilhado por conta do operador: cinco confirmações incorretas em quinze minutos bloqueiam novas confirmações por quinze minutos. Campos obrigatórios vazios e confirmação da nova senha diferente são erros de preenchimento, não tentativas de conferir a senha atual. Login e navegação comuns continuam disponíveis.
- A operação revalida autor, alvo, hierarquia e autorização durante a transação. Nova senha continua com limite de 8 a 72 bytes e hash pelo mecanismo existente. Senha e auditoria são confirmadas juntas; falha da auditoria desfaz senha, versão de sessão e revisão de autorização.
- Troca própria mantém a sessão usada, renova seu identificador e invalida outras sessões. Redefinição por terceiro invalida as sessões da conta atendida. As próximas solicitações detectam a invalidação. Autorizações sensíveis da conta afetada são revogadas.
- A edição cadastral não escreve a coluna senha. Os formulários recusam campos de senha no caminho antigo; funções legadas recusam uma senha não vazia. Cadastro inicial de conta continua definindo a primeira senha.
- A confirmação de identidade para senha fica no histórico protegido. Os eventos de troca/redefinição mantêm sua classificação anterior e registram somente ator e ID da conta, sem senha ou hash. Nenhum campo de senha é repopulado na resposta.

Sem arquivos novos, sem nova migração: reutiliza a tabela da migração 002. Não há alteração automática das senhas existentes. Testes em bancos temporários cobrem formulários, CSRF, credencial do operador, hierarquia, limite de tentativas, invalidação de sessões, caminho antigo e rollback.


## Apresentação e comprimento das senhas

Os formulários de troca e redefinição apresentam nova senha e confirmação primeiro, com a senha atual do operador abaixo e botão no rodapé. Mensagens de erro aparecem no cartão da operação, sem instrução permanente sobre bytes.

A validação compartilhada de senhas novas exige no mínimo oito caracteres visuais (grupos Unicode, contados com PCRE `\X`) e mantém o limite técnico de 72 bytes do hash atual. Mensagens: “A senha deve conter no mínimo 8 caracteres.” ou “A senha informada é muito longa.”. A regra alcança cadastro, inicialização e troca/redefinição; não revalida o comprimento de senhas existentes durante login ou confirmação. Sem migração.


## Controle de visibilidade das senhas

`script/senhas.js` é compartilhado por login, cadastro de usuário, perfil, edição de usuário e histórico. Cada campo inicia oculto, com botão próprio de mostrar/ocultar acessível por teclado, rótulo e estado para leitores de tela. O controle só alterna o tipo do input; não copia, armazena nem transmite seu valor. Mantém os atributos de preenchimento automático e a validação existente. Sem JavaScript, os formulários permanecem utilizáveis com campos ocultos.

Ao sair do conjunto campo/botão, pressionar Escape, enviar/resetar formulário ou sair da janela/página, o texto volta a ser ocultado. A alternância de foco entre campo e botão permanece possível para usuários de teclado. Senhas já salvas no banco não podem ser visualizadas por esse recurso.

Botão de alteração/redefinição ocupa a coluna ao lado da confirmação de identidade, alinhado pela base; em telas estreitas ocupa a linha seguinte inteira. Nenhuma regra de autorização ou banco foi alterada.


## Reenvio e formulários desatualizados de contas

Escopo: edição cadastral em Meu Perfil e Editar usuário, troca própria de senha, redefinição e correção de e-mail. Consulta de e-mail continua uma operação explícita de leitura auditada. Produtos, lançamentos e UBS não receberam esta proteção nesta etapa.

- Cada formulário de alteração recebe um identificador aleatório de uso único, separado do CSRF, vinculado na sessão ao autor, versão da sessão, perfil, operação e alvo. Validade: 30 minutos; até 60 formulários abertos por sessão. O mais antigo é descartado se o limite for atingido. Duas abas normais recebem identificadores distintos.
- O identificador é consumido antes de conferir a senha, inclusive se a tentativa resultar em erro. A resposta apresenta novos formulários para uma tentativa corrigida. Token ausente, usado, expirado, de outra finalidade ou alvo retorna conflito (409), sem contar erro de senha.
- O estado esperado é uma impressão HMAC guardada somente na sessão. Inclui os dados atuais da conta e sua versão; senha/hash não são enviados ao navegador nem copiados para o registro do formulário. A comparação é conservadora: uma mudança em qualquer campo da conta exige revisão do formulário antigo. Não é um histórico de todas as mudanças intermediárias caso os dados voltem exatamente ao mesmo estado.
- Nas operações sensíveis, a comparação preliminar acontece antes da confirmação de identidade; a conferência definitiva ocorre com as linhas bloqueadas na transação, antes de gravar. Edição cadastral também compara dentro da transação. Isso cobre concorrência entre sessões diferentes, além do bloqueio da sessão PHP entre pedidos da mesma sessão. As funções internas recebem o contexto esperado; as páginas protegidas sempre o exigem.
- Sucesso usa redirecionamento 303 para GET, retornando ao cartão correto na mesma página. A mensagem temporária tem identificador próprio, página/alvo/ação e validade de três minutos; é lida uma vez. Atualizar a página não repete a gravação. Administrador que reduz o próprio perfil retorna a Meu Perfil.
- Em erro, campos de senha permanecem vazios. Dados cadastrais comuns são preservados quando apropriado; em conflito são apresentados os dados atuais do banco. Nenhuma senha ou conteúdo do formulário é guardado na mensagem temporária.
- Login, mudança de perfil detectada e troca própria de senha limpam os controles anteriores junto das confirmações sensíveis. Sessões invalidadas continuam sujeitas à verificação normal de acesso.

Não há nova migração. Esta é uma proteção de formulários ligada à sessão e à comparação do estado existente, não uma fila persistente ou garantia geral de processamento único após falhas de infraestrutura. Tokens/mensagens perdidos exigem atualizar a página e conferir o estado salvo; não se deve repetir automaticamente uma operação incerta. Transações e auditoria continuam indivisíveis.

Os testes HTTP incluem redirecionamento, mensagens isoladas por página/conta, repetição, token expirado, finalidade/alvo incorretos, duas abas, dados comuns preservados e falha de auditoria. Duas conexões PHP independentes testam concorrência de gravação do mesmo estado esperado. Todos usam bancos temporários.


## Classificação dos eventos de consulta

“Consulta de e-mail” e “Acesso ao histórico de autenticação” aparecem somente nos logs comuns, acessíveis a Administrador e Suporte. Esta regra substitui as descrições anteriores que colocavam esses dois eventos no histórico protegido. A mudança é no filtro de consulta: registros existentes não são alterados nem apagados.

Login, Logout, o evento legado “Acesso ao histórico de login”, confirmações de identidade, tentativas recusadas/bloqueadas e correções de e-mail continuam na listagem protegida. A permissão de consultar o e-mail completo e o histórico de autenticação continua exclusiva do administrador com confirmação de identidade. O evento de acesso ao histórico registra a liberação da janela de cinco minutos, não cada visita/filtro.


## Proteção do login - janela móvel e concorrência (28/09/2026)

O login usa `script/funcoes_login.php`: cinco falhas por e-mail ou dez por IP em uma janela móvel de quinze minutos bloqueiam novas autenticações por quinze minutos. Tentativas bloqueadas não estendem o prazo. Sessões abertas e status da conta não são alterados. O endereço é aparado e convertido para minúsculas; o IP é validado e normalizado, obtido exclusivamente de `REMOTE_ADDR`. Cabeçalhos de proxy não são aceitos automaticamente. Uma futura implantação atrás de proxy exige definir proxies confiáveis; dez falhas por IP podem afetar funcionários que compartilham a conexão.

Login correto limpa as falhas do e-mail, mas preserva as falhas recentes da origem. E-mails válidos inexistentes também têm contagem e passam por verificação com hash fictício (que não pertence a nenhuma conta). Campos vazios ou formato inválido são recusados antes de conferir credenciais; não geram falhas de senha. Conta inativa nunca autentica. A informação de desativação só aparece após senha correta. CSRF é validado antes do fluxo.

A transação InnoDB bloqueia primeiro a origem e depois o e-mail durante verificação e atualização. Deadlocks ou timeout de lock provocam rollback e até quatro repetições limitadas; falha persistente retorna 503 e não autentica. Falha de consulta/gravação também retorna 503. Bloqueios retornam 429 com `Retry-After`; credenciais incorretas retornam 401. Somente após auditoria de login bem-sucedida a página estabelece a nova sessão. Uma mensagem anterior de cadastro não interfere na contagem.

Os horários da proteção são segundos Unix (`time()`), não dependem do fuso configurado no PHP ou MySQL. Isso não padroniza os horários do restante do projeto, que continuam previstos para a parte 2. As falhas são armazenadas em JSON e limitadas pelos próprios limiares. A limpeza oportunista remove até cem registros sem atividade há mais de 24 horas e sem bloqueio ativo, depois de uma autenticação processada (correta ou incorreta). Sem tráfego, a limpeza não ocorre; esse prazo não constitui garantia de eliminação em horário exato. A limpeza usa exclusão por chave com nova conferência da idade, para não apagar contadores renovados concorrentemente.

A tabela ainda contém identificadores de e-mail e IP em texto; não contém senhas nem seus hashes. Esta entrega não implementa criptografia ou anonimização e não substitui CAPTCHA nem controles de infraestrutura. Bloqueio por conta pode ser provocado por terceiros; é temporário, sem mecanismo de bloqueio permanente.

### Atualização necessária antes de usar o novo login

Em banco existente, executar pelo terminal da pasta do projeto:

```text
C:\xampp\php\php.exe bd/administrar.php migrar
```

O comando agora inclui a migração 003 e a nova 004. A 003 cria a tabela se ausente; a 004 acrescenta `falhas`, `bloqueio_epoch` e `atividade_epoch`. O executor verifica cada coluna antes do ALTER e garante InnoDB. É seguro repetir pelo comando acima, inclusive após aplicação parcial. O SQL 004 isolado não deve ser reaplicado manualmente: a verificação de existência está no executor. Instalações novas usam a estrutura final de `bd/criar-bd.sql`.

Usuários, senhas, produtos e movimentações não são modificados. Contadores legados não têm os horários individuais das falhas e não podem ser convertidos fielmente para uma janela móvel: são preservados inicialmente, mas ignorados pela nova regra e posteriormente atualizados ou limpos. Isso reinicia uma vez os bloqueios temporários antigos. Não executar a implantação do código sem a atualização do banco: sem as novas colunas o login falha fechado, com mensagem de indisponibilidade. SQL DDL pode confirmar alterações imediatamente; se a migração for interrompida, corrigir a causa e repetir o comando, sem assumir rollback de estrutura.

### Verificação

`python -X utf8 tests/login_integracao.py` cria e remove seu próprio banco temporário. Cobre migração ausente/legada/repetida, expiração individual de falhas, limites, prazo fixo, sucesso sem zerar origem, contas inativas, novas sessões HTTP, CSRF, indisponibilidade, rollback, limpeza e concorrência com processos PHP independentes. Não usa senhas ou contas reais. Executar também as suítes existentes `tests/http_integracao.py` e `tests/usuarios_integracao.php` para regressões.


## Login unificado e reCAPTCHA v2 opcional (28/09/2026)

`pages/login_com_recaptcha.php` preserva o endereço criado anteriormente, mas inclui `pages/login.php`, compartilhando formulário, mensagens, CSRF, autenticação, auditoria, sessão e contadores. Não são dois processamentos independentes. Sem configuração, ambos funcionam como login comum e não carregam o script do Google. Quando ativado, ambos exigem o CAPTCHA, inclusive um POST direto em `login.php`; não existe uma URL alternativa sem a verificação.

Configuração por variáveis do ambiente do servidor (nenhuma chave real deve ser escrita no Git):

| Variável | Valor esperado |
| --- | --- |
| `GESTSAUDE_RECAPTCHA_ATIVO` | Ausente, `0`, `false` ou `off`: desativado. `1`, `true` ou `on`: ativado. Outro valor falha fechado. |
| `GESTSAUDE_RECAPTCHA_SITE_KEY` | Chave pública do reCAPTCHA v2, usada no HTML do widget. |
| `GESTSAUDE_RECAPTCHA_SECRET_KEY` | Chave secreta do reCAPTCHA v2, usada somente no servidor. |
| `GESTSAUDE_RECAPTCHA_HOSTNAMES` | Lista de nomes de domínio autorizados, separados por vírgula, sem protocolo, caminho ou porta. Correspondência exata; listar subdomínios explicitamente. |

As chaves do CAPTCHA são diferentes das futuras chaves de criptografia dos e-mails. Configure no ambiente efetivo do PHP/Apache; definir uma variável somente no terminal não significa que o Apache já a recebeu. Pode ser necessário reiniciar o serviço após configurar. Nenhuma configuração real foi ativada nesta entrega. O modo suportado é v2 com checkbox, não v3. A instituição deverá cadastrar os domínios corretos e validar a integração real antes da implantação.

A verificação é no servidor, pela API oficial fixa `https://www.google.com/recaptcha/api/siteverify`, por HTTPS com validação de certificado, sem redirecionamentos e com timeout. Resposta inválida, erro de rede, configuração incompleta ou chave secreta inválida impedem o login. O hostname devolvido precisa estar na lista configurada (não se confia no cabeçalho Host da requisição). Não enviar chave secreta, token ou resposta bruta ao HTML/logs. O IP não é enviado ao Google pelo backend; o widget continua sendo um serviço externo.

O CAPTCHA é validado antes de abrir a transação de autenticação, evitando espera de rede com locks. Tokens ausentes/rejeitados não são falhas de senha e não avançam os contadores; com CAPTCHA aprovado, aplicam-se os mesmos limites persistentes, inclusive entre as duas URLs. Cada novo envio precisa de nova validação; não existe autorização de CAPTCHA salva na sessão. O provedor controla validade e uso único do token: https://developers.google.com/recaptcha/docs/verify .

`tests/login_integracao.py` cobre ambos os endereços sem CAPTCHA, configuração obrigatória incompleta, tentativa de contornar pela URL comum, ausência de token e segredo ausente do HTML. O serviço é testado com transporte simulado para sucesso, hostname errado, token expirado/reutilizado, falha de rede/JSON e limites após CAPTCHA aceito. A dependência de transporte só pode ser passada por código PHP de teste; não existe endpoint alternativo configurável, parâmetro HTTP ou modo de bypass. Esses testes não substituem o teste real do widget e das credenciais institucionais. Nenhuma migração adicional é necessária para esta unificação.


## Parte 2 - horários e datas de movimentações (28/09/2026)

`script/configuracao.php` centraliza `America/Bahia`. É carregado pela sessão, conexão e funções de lançamento. Cada conexão da aplicação configura `time_zone` com o deslocamento atual desse fuso (atualmente -03:00), dispensando a instalação das tabelas de fusos do MySQL. Isso não modifica o fuso global do servidor ou o php.ini, nem converte registros antigos. Ferramentas externas que gravem diretamente no banco devem usar a mesma convenção. O relógio do servidor precisa estar sincronizado; configurar fuso não corrige relógio físico errado. Se a legislação de horário mudar, atualizar a base de fusos do PHP e revisar a configuração da implantação.

Datas informadas seguem estritamente `YYYY-MM-DDTHH:mm`, com segundos zerados e ano compatível com DATETIME. Datas impossíveis ou iguais/posteriores ao instante atual são rejeitadas. Toda escolha de data anterior exige observação no servidor. Modo de data desconhecido é recusado; a opção ausente mantém compatibilidade com o registro imediato. A tela usa a hora inicial do servidor e o tempo decorrido para apresentar o limite no fuso institucional, sem depender do fuso do navegador. O PHP continua sendo a autoridade da validação. Erros preservam data, modo, observação, unidade e linhas válidas de preenchimento, tanto pelo fluxo AJAX quanto na resposta HTML.

`movimentacao.data_hora` permanece como data de ocorrência. O novo campo `cadastrado_em` representa quando o INSERT foi realizado, automaticamente pelo banco. Ele não é aceito como campo editável do formulário; tentativas de enviá-lo não alteram o horário gerado. O mesmo padrão automático atende saldos iniciais e ajustes de estoque, sem duplicar código nesses módulos. O valor representa o momento da inserção, não o instante posterior de commit. Lançamentos retroativos continuam alterando o saldo atual, sem reconstruir ou validar o estoque histórico de cada instante passado.

O histórico apresenta “Data da movimentação” e “Data do cadastro”. Os filtros “Movimentação: de/até” continuam selecionando a ocorrência, não o cadastro. Registros anteriores aparecem com “Não informado (registro anterior)” no novo campo. Observações são serializadas com escape adequado ao JavaScript e exibidas como texto no Grid.js. As permissões do histórico não foram alteradas.

### Migração 005

Antes de usar o histórico atualizado, executar `php bd/administrar.php migrar`. A migração `005-data-cadastro-movimentacao.sql` primeiro acrescenta `cadastrado_em` como NULL, preservando a ausência de informação nos registros antigos, e depois define CURRENT_TIMESTAMP como padrão somente para novos INSERTs. O executor verifica a coluna e pode ser repetido ou retomar após a criação parcial. Não importar novamente `criar-bd.sql` em banco existente. Não aplicar indiscriminadamente novamente o SQL 005 isolado: a verificação está no executor.

A migração não preenche datas antigas com o horário de hoje e não desloca horários existentes. Instalações novas recebem o campo pelo SQL de criação. Aplicar a atualização do banco junto com esta versão; a consulta do histórico necessita da nova coluna. DDL pode confirmar alterações imediatamente, portanto repetir o executor após corrigir uma interrupção, sem presumir rollback da estrutura.

### Testes da parte 2

`tests/usuarios_integracao.php` verifica fuso PHP/conexão, comparação de relógios, virada de dia, formatos inválidos, datas futuras, segundos zerados, observação obrigatória, saldo preservado em erros e separação da ocorrência/cadastro. `tests/http_integracao.py` verifica migração repetida preservando dados antigos, rejeições pelo formulário, preservação dos campos sem AJAX, cadastro automático não sobrescrito por POST, filtros do histórico e sintaxe do JavaScript renderizado. As suítes usam bancos temporários; não modificam contas ou estoque reais. A validação visual interativa das telas não é substituída por esses testes.


## Configuração local por .env (28/09/2026)

O CAPTCHA agora lê uma configuração central carregada por `script/ambiente.php`, com a biblioteca `vlucas/phpdotenv` instalada pelo Composer. `composer.json` e `composer.lock` devem ser versionados; `vendor/` é instalado e não entra no Git. `script/configuracao.php` inicializa o carregamento; `script/captcha.php` usa `valorConfiguracao()`. Não há escrita via `putenv`, importante no PHP ZTS. O ambiente do servidor (`$_ENV`, `$_SERVER` e leitura de `getenv`) prevalece sobre o arquivo, inclusive valores vazios explicitamente definidos. Remover antigas variáveis do Apache se a intenção for usar exclusivamente o .env.

### Preparação em outro computador

1. Instalar o Composer e executar `composer install --no-dev --prefer-dist` na pasta do projeto. Usar `install`, não `update`, para respeitar `composer.lock`. O PHP do Composer precisa de OpenSSL para download HTTPS; ZIP ou uma ferramenta de extração é necessária para instalar os pacotes. Neste computador foi utilizado Composer temporário com OpenSSL/ZIP habilitados somente na linha de comando, sem modificar o php.ini.
2. Copiar `.env.example` para `.env`, sem sobrescrever uma configuração particular existente.
3. Para continuar sem CAPTCHA, manter `GESTSAUDE_RECAPTCHA_ATIVO=0`.
4. Para usar reCAPTCHA v2 checkbox, definir ATIVO=1, SITE_KEY (pública), SECRET_KEY (secreta) e HOSTNAMES (domínios autorizados separados por vírgula). No teste com endereço localhost, configurar localhost também no Google. Não usar chaves v3.
5. Recarregar a página. Alterações no arquivo são lidas em novas requisições PHP; mudanças em variáveis do serviço Apache podem exigir reinício.

O .env local foi criado com CAPTCHA desativado e chaves vazias. Nunca colocar a chave secreta no Git, em capturas de tela ou em mensagens de erro. As duas URLs de login continuam compartilhadas. CAPTCHA ativo sem chaves válidas ou indisponibilidade do serviço impede autenticação. Não existe ativação exclusiva da página alternativa, que permitiria contorno pela página comum. A integração real com Google requer HTTPS disponível no PHP que atende a página (OpenSSL), acesso de rede e certificados válidos; testes simulados não comprovam esses requisitos da implantação.

O arquivo .env é opcional para ambientes que fornecem configuração no servidor. Quando presente, deve conter a opção explícita GESTSAUDE_RECAPTCHA_ATIVO (ou ela deve existir no ambiente). Arquivo inválido ou dependências ausentes causam indisponibilidade com mensagem genérica; erros do parser, que podem conter segredos, não são exibidos nem registrados. A configuração existente do banco não foi migrada para o .env nesta entrega.

### Proteção do arquivo

`.gitignore` ignora .env e suas variantes, exceto o modelo .env.example. Isso protege o versionamento, não o acesso web. O novo .htaccess na raiz bloqueia arquivos cujo nome começa com .env, incluindo o exemplo e cópias de segurança. Apache precisa permitir essa regra (AllowOverride apropriado). O Apache local foi verificado e respondeu 403 para .env e .env.example, sem conteúdo do arquivo; os dois logins responderam 200. Revalidar essa proteção em cada implantação antes de inserir chaves reais.

Nginx não lê .htaccess: configurar uma regra equivalente no bloco server, por exemplo `location ~ /\.env { deny all; }`, respeitando a precedência das demais locations, e testar a resposta HTTP. O servidor embutido `php -S` também não aplica .htaccess; não expor a raiz contendo .env sem um roteador que bloqueie esse acesso. Esta configuração na raiz é adequada somente quando o servidor impede downloads; uma futura reorganização para webroot público separado pode mover segredos para fora da área publicada.

Testes: `python -X utf8 tests/ambiente_integracao.py` usa exclusivamente configuração sintética em diretório temporário para testar leitura, precedência, valores vazios, arquivo ausente/inválido e dependências ausentes. As suítes de login/CAPTCHA e HTTP continuam verificando as proteções anteriores. Nenhuma migração de banco é necessária para esta alteração.

Referência da biblioteca: https://github.com/vlucas/phpdotenv .
