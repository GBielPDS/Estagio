### Instalação da biblioteca PHP dotenv

Para utilizar arquivos `.env` no projeto PHP, é necessário instalar a biblioteca **PHP dotenv**, desenvolvida por `vlucas`. Essa biblioteca permite carregar variáveis de configuração armazenadas em um arquivo `.env` e acessá-las através do PHP.

A instalação é realizada utilizando o **Composer**, gerenciador de dependências do PHP.

Primeiramente, é necessário verificar se o Composer está instalado no computador. No terminal, execute:

bash
composer --version


Caso o Composer esteja instalado, será exibida a versão instalada. Caso o comando não seja reconhecido, é necessário instalar o Composer antes de continuar.

Após instalar o Composer, abra o terminal na pasta raiz do projeto. Por exemplo:

bash
cd /c/xampp/htdocs/git/Estagio


Em seguida, execute o seguinte comando:

bash
composer require vlucas/phpdotenv


O Composer fará o download da biblioteca e criará a pasta `vendor`, além dos arquivos `composer.json` e `composer.lock`, caso ainda não existam.

A estrutura do projeto ficará semelhante a:

text
Estagio/
├── .env
├── .gitignore
├── composer.json
├── composer.lock
├── vendor/
│   ├── autoload.php
│   └── vlucas/
│       └── phpdotenv/
├── pages/
└── script/


Depois da instalação, a biblioteca pode ser carregada no PHP através do autoloader do Composer:

php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();


Com isso, as variáveis armazenadas no arquivo `.env` podem ser acessadas através de `$_ENV`.

Por exemplo, considerando o seguinte arquivo `.env`:

env
GESTSAUDE_RECAPTCHA_ATIVO=1
GESTSAUDE_RECAPTCHA_SITE_KEY=chave_do_site
GESTSAUDE_RECAPTCHA_SECRET_KEY=chave_secreta
GESTSAUDE_RECAPTCHA_HOSTNAMES=localhost


O valor de uma variável pode ser obtido no PHP através de:

php
$site = $_ENV['GESTSAUDE_RECAPTCHA_SITE_KEY'] ?? '';


O arquivo `.env` deve ser incluído no `.gitignore`, pois pode conter informações sensíveis, como chaves secretas e credenciais de serviços externos.

gitignore
.env
/vendor/


Dessa forma, as configurações específicas do ambiente ficam separadas do código-fonte e não são enviadas para o repositório Git.
