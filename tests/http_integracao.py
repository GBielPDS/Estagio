"""Fluxos reais HTTP contra banco e servidor temporários, sem contas reais."""
import concurrent.futures
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = 'C:/xampp/php/php.exe'
MYSQL = 'C:/xampp/mysql/bin/mysql.exe'
DB = 'gestsaude_http_' + secrets.token_hex(6)

def sql(text, database=None):
    args = [MYSQL, '-u', 'root', '--default-character-set=utf8mb4', '-N']
    if database:
        args.append(database)
    return subprocess.check_output(args, input=text.encode(), stderr=subprocess.STDOUT).decode()

class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
    def request(self, path, data=None):
        request = urllib.request.Request(BASE + path, data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            response = self.opener.open(request)
        except urllib.error.HTTPError as error:
            response = error
        self.headers = response.headers
        return response.status, response.read().decode(), response.geturl()
    def token(self, path):
        status, html, _ = self.request(path)
        assert status == 200, html
        return re.search(r'name="csrf" value="([a-f0-9]+)"', html)[1]
    def login(self, email, password='Inicial123!'):
        token = self.token('pages/login.php')
        return self.request('pages/login.php', {'csrf':token, 'email':email, 'senha':password})

server = None
sql(f'CREATE DATABASE `{DB}` CHARACTER SET utf8mb4;')
try:
    schema = (ROOT / 'bd/criar-bd.sql').read_text(encoding='utf-8')
    sql(schema.replace('CREATE DATABASE almoxarifado;', '').replace('USE almoxarifado;', ''), DB)
    env = dict(os.environ, GESTSAUDE_DB=DB)
    def administer(command, data=''):
        result = subprocess.run([PHP, str(ROOT/'bd/administrar.php'), command], env=env, input=data.encode(), stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=25)
        return result.stdout.decode()
    sql('DROP TABLE confirmacao_identidade; ALTER TABLE usuario DROP COLUMN versao_sessao;', DB)
    assert 'Banco atualizado' in administer('migrar')
    assert 'Banco atualizado' in administer('migrar')
    assert 'Instalação inicializada' in administer('inicializar', 'Primeiro Admin\nprimeiro@teste.local\nInicial123!\nn\n')
    assert 'bloqueada' in administer('inicializar')
    assert sql("SELECT COUNT(*) FROM usuario WHERE tipo='Administrador' AND ativo=1", DB).strip() == '1'
    assert sql('SELECT COUNT(*) FROM categoria', DB).strip() == '6'
    assert sql('SELECT COUNT(*) FROM unidade_saude', DB).strip() == '1'
    sql('DELETE FROM log; DELETE FROM usuario; ALTER TABLE usuario AUTO_INCREMENT=1;', DB)
    # Dependências parciais e catálogo: responder inválido não equivale a aceitar.
    sql("DELETE FROM categoria WHERE nome <> 'Limpeza';", DB)
    assert 'Instalação inicializada' in administer('inicializar', 'Admin\nnovo@teste.local\nInicial123!\nx\ns\n')
    total_catalogo = int(sql('SELECT COUNT(*) FROM produto', DB).strip())
    assert total_catalogo > 100
    assert sql('SELECT COUNT(*) FROM produto WHERE estoque <> 0', DB).strip() == '0'
    assert sql('SELECT COUNT(*) FROM categoria', DB).strip() == '6'
    sql('UPDATE usuario SET ativo=0;', DB)
    assert 'bloqueada' in administer('inicializar')
    sql('DELETE FROM log; DELETE FROM usuario; ALTER TABLE usuario AUTO_INCREMENT=1;', DB)
    assert 'Instalação inicializada' in administer('inicializar', 'Admin\nnovo@teste.local\nInicial123!\n')
    assert int(sql('SELECT COUNT(*) FROM produto', DB).strip()) == total_catalogo
    sql('DELETE FROM log; DELETE FROM usuario; DELETE FROM produto; ALTER TABLE usuario AUTO_INCREMENT=1;', DB)
    assert 'e-mail válido' in administer('inicializar', 'Admin\nadmin\nInicial123!\n')
    assert sql('SELECT COUNT(*) FROM usuario', DB).strip() == '0'
    # Falha na importação deve desfazer também o administrador e dependências novas.
    sql("DELETE FROM categoria; DELETE FROM unidade_saude; CREATE TRIGGER falha_catalogo BEFORE INSERT ON produto FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';", DB)
    assert 'desfeitas' in administer('inicializar', 'Admin\nnovo@teste.local\nInicial123!\ns\n')
    assert sql('SELECT COUNT(*) FROM usuario', DB).strip() == '0'
    assert sql('SELECT COUNT(*) FROM categoria', DB).strip() == '0'
    sql('DROP TRIGGER falha_catalogo;', DB)
    sql("CREATE TRIGGER falha_auditoria BEFORE INSERT ON log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';", DB)
    assert 'desfeitas' in administer('inicializar', 'Admin\nnovo@teste.local\nInicial123!\ns\n')
    assert sql('SELECT COUNT(*) FROM usuario', DB).strip() == '0'
    assert sql('SELECT COUNT(*) FROM produto', DB).strip() == '0'
    sql('DROP TRIGGER falha_auditoria;', DB)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda _: administer('inicializar', 'Admin\nnovo@teste.local\nInicial123!\nn\n'), range(2)))
    assert sum('Instalação inicializada' in result for result in results) == 1, results
    assert sql('SELECT COUNT(*) FROM usuario', DB).strip() == '1'
    sql('DELETE FROM log; DELETE FROM usuario; ALTER TABLE usuario AUTO_INCREMENT=1;', DB)
    password_hash = subprocess.check_output([PHP, '-r', 'echo password_hash("Inicial123!", PASSWORD_DEFAULT);']).decode()
    sql("INSERT INTO usuario(nome,email,senha,tipo) VALUES " + ','.join(
        f"('{name}','{name}@teste.local','{password_hash}','{role}')" for name, role in [('admin','Administrador'),('segundo','Administrador'),('operador','Usuario')]) + ';', DB)
    with tempfile.TemporaryDirectory(prefix='gestsaude-http-') as temp:
        session_dir = Path(temp) / 'sessions'
        session_dir.mkdir()
        router = Path(temp) / 'router.php'
        root = ROOT.as_posix()
        router.write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); "
            "$path=substr($path,strlen('/git/ESTAGIO/')); "
            f"$file='{root}/'.$path; "
            "if (!is_file($file)) {http_response_code(404);exit;} chdir(dirname($file)); require $file;", encoding='utf-8')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        BASE = f'http://127.0.0.1:{port}/git/ESTAGIO/'
        env = dict(os.environ, GESTSAUDE_DB=DB)
        with open(Path(temp) / 'server.log', 'w') as output:
            server = subprocess.Popen([PHP, '-d', 'session.save_path='+str(session_dir), '-S', f'127.0.0.1:{port}', str(router)], cwd=ROOT, env=env, stdout=output, stderr=output)
            try:
                for _ in range(50):
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=.1):
                            break
                    except OSError:
                        time.sleep(.1)
                admin, second, user, other = Browser(), Browser(), Browser(), Browser()
                assert admin.request('bd/administrar.php')[0] == 404
                assert admin.request('setup.php')[0] == 404
                login_html = admin.request('pages/login.php')[1]
                assert login_html.count('id="formLogin"') == 1
                assert admin.request('pages/login.php', {'email':'admin@teste.local','senha':'Inicial123!'})[0] == 403
                sql("UPDATE usuario SET ativo=0 WHERE id_usuario=3", DB)
                assert 'desativado' not in user.login('operador@teste.local', 'errada')[1]
                assert 'desativado' in user.login('operador@teste.local')[1]
                sql("UPDATE usuario SET ativo=1 WHERE id_usuario=3", DB)
                assert admin.login('admin@teste.local')[2].endswith('index.php')
                # Histórico de autenticação: isolamento, reautenticação, prazo e limite persistente.
                auth_path = 'pages/logs.php?visualizacao=login'
                auth_token = admin.token(auth_path)
                auth_data = {'csrf':auth_token,'confirmar_historico_login':'1','senha_confirmacao':'Inicial123!'}
                sql("INSERT INTO log(acao,descricao,usuario_id) VALUES ('Login','EVENTO_LOGIN_PROTEGIDO',1),('Logout','EVENTO_LOGOUT_PROTEGIDO',1),('Edição','EVENTO_OPERACIONAL',1)", DB)
                operational = admin.request('pages/logs.php')[1]
                assert 'EVENTO_OPERACIONAL' in operational and 'EVENTO_LOGIN_PROTEGIDO' not in operational and 'EVENTO_LOGOUT_PROTEGIDO' not in operational
                assert 'EVENTO_LOGIN_PROTEGIDO' not in admin.request(auth_path)[1]
                assert 'no-store' in admin.headers.get('Cache-Control','')
                assert admin.request(auth_path, dict(auth_data,csrf='invalido'))[0] == 403
                sql('DROP TABLE confirmacao_identidade;', DB)
                assert admin.request(auth_path, auth_data)[0] == 503
                assert admin.request('pages/produtos.php')[0] == 200
                assert 'Banco atualizado' in administer('migrar')
                assert 'Senha incorreta' in admin.request(auth_path,dict(auth_data,senha_confirmacao='errada'))[1]
                authorized = admin.request(auth_path,auth_data)[1]
                assert 'EVENTO_LOGIN_PROTEGIDO' in authorized and 'EVENTO_LOGOUT_PROTEGIDO' in authorized
                assert 'EVENTO_OPERACIONAL' not in authorized
                assert sql("SELECT COUNT(*) FROM log WHERE acao='Acesso ao histórico de autenticação'",DB).strip() == '1'
                assert 'EVENTO_LOGIN_PROTEGIDO' in admin.request(auth_path+'&usuario=1')[1]
                assert sql("SELECT COUNT(*) FROM log WHERE acao='Acesso ao histórico de autenticação'",DB).strip() == '1'
                # Alterar apenas a sessão de teste pelo PHP CLI, sem endpoint auxiliar na aplicação.
                def session_fixture(browser, code):
                    session_id = next(c.value for c in browser.cookies if c.name == 'PHPSESSID')
                    subprocess.check_call([PHP,'-d','session.save_path='+str(session_dir),'-r',
                        'session_id($argv[1]); session_start(); '+code+'; session_write_close();',session_id])
                session_fixture(admin, "$_SESSION['confirmacoes_identidade']['historico_autenticacao']['expira_em']=time()-1")
                assert 'EVENTO_LOGIN_PROTEGIDO' not in admin.request(auth_path)[1]
                admin.request(auth_path,auth_data)
                session_fixture(admin, "$_SESSION['confirmacoes_identidade']['historico_autenticacao']['finalidade']='outro_escopo'")
                assert 'EVENTO_LOGIN_PROTEGIDO' not in admin.request(auth_path)[1]
                admin.request(auth_path,auth_data)
                admin.login('admin@teste.local')
                assert 'EVENTO_LOGIN_PROTEGIDO' not in admin.request(auth_path)[1]
                assert user.login('operador@teste.local')[2].endswith('index.php')
                assert user.request(auth_path)[0] == 403
                sql("UPDATE usuario SET tipo='Suporte' WHERE id_usuario=3",DB)
                assert user.request('pages/logs.php')[0] == 200
                assert user.request(auth_path)[0] == 403
                assert user.request(auth_path,dict(auth_data,csrf=user.token('pages/perfil.php')))[0] == 403
                sql("UPDATE usuario SET tipo='Usuario' WHERE id_usuario=3",DB)
                # Cinco erros somados entre dois navegadores da mesma conta.
                parallel_admin = Browser(); parallel_admin.login('admin@teste.local')
                parallel_data = dict(auth_data,csrf=parallel_admin.token(auth_path),senha_confirmacao='errada')
                for attempt in range(5):
                    browser, payload = (admin,dict(auth_data,senha_confirmacao='errada')) if attempt % 2 == 0 else (parallel_admin,parallel_data)
                    status = browser.request(auth_path,payload)[0]
                    assert status == (429 if attempt == 4 else 200)
                audit_count = sql("SELECT COUNT(*) FROM log WHERE acao IN ('Confirmação sensível recusada','Confirmação sensível bloqueada')",DB).strip()
                assert parallel_admin.request(auth_path,dict(parallel_data,senha_confirmacao='Inicial123!'))[0] == 429
                assert sql("SELECT COUNT(*) FROM log WHERE acao IN ('Confirmação sensível recusada','Confirmação sensível bloqueada')",DB).strip() == audit_count
                assert admin.request('pages/produtos.php')[0] == 200
                parallel_admin.login('admin@teste.local')
                assert parallel_admin.request(auth_path,dict(parallel_data,senha_confirmacao='Inicial123!'))[0] == 429
                sql("UPDATE confirmacao_identidade SET bloqueado_ate=UNIX_TIMESTAMP()-1,falhas=CONCAT('[',UNIX_TIMESTAMP()-1000,']') WHERE usuario_id=1",DB)
                assert 'EVENTO_LOGIN_PROTEGIDO' in admin.request(auth_path,auth_data)[1]
                # Falha de auditoria impede autorização, mas preserva o contador de erros.
                sql("CREATE TRIGGER falha_confirmacao BEFORE INSERT ON log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';",DB)
                assert admin.request(auth_path,auth_data)[0] == 503
                assert 'EVENTO_LOGIN_PROTEGIDO' not in admin.request(auth_path)[1]
                assert admin.request(auth_path,dict(auth_data,senha_confirmacao='errada'))[0] == 200
                assert sql('SELECT JSON_LENGTH(falhas) FROM confirmacao_identidade WHERE usuario_id=1',DB).strip() == '1'
                sql('DROP TRIGGER falha_confirmacao;',DB)
                # Revogar perfil e restaurá-lo sem visitar o histórico no intervalo não restaura a liberação.
                second.login('segundo@teste.local')
                second_auth = dict(auth_data,csrf=second.token(auth_path))
                assert 'EVENTO_LOGIN_PROTEGIDO' in second.request(auth_path,second_auth)[1]
                edit_second = {'csrf':auth_token,'nome':'Segundo','senha':'','tipo':'Suporte','ativo':'1'}
                admin.request('pages/editar_usuario.php?id=2',edit_second)
                admin.request('pages/editar_usuario.php?id=2',dict(edit_second,tipo='Administrador'))
                assert 'EVENTO_LOGIN_PROTEGIDO' not in second.request(auth_path)[1]
                second.request(auth_path,second_auth)
                second.request('pages/perfil.php',{'csrf':second_auth['csrf'],'nome':'Segundo','senha':'Temporaria123!','confirmar_senha':'Temporaria123!'})
                assert 'EVENTO_LOGIN_PROTEGIDO' not in second.request(auth_path)[1]
                second.request('pages/perfil.php',{'csrf':second_auth['csrf'],'nome':'Segundo','senha':'Inicial123!','confirmar_senha':'Inicial123!'})
                second.request(auth_path,second_auth)
                admin.request('pages/usuarios.php',{'csrf':auth_token,'excluir_id':2})
                admin.request('pages/usuarios.php',{'csrf':auth_token,'reativar_id':2})
                assert second.request(auth_path)[2].endswith('login.php')
                second.login('segundo@teste.local')
                second_auth['csrf'] = second.token(auth_path)
                second.request(auth_path,second_auth)
                admin.request('pages/editar_usuario.php?id=2',dict(edit_second,tipo='Administrador',senha='Inicial123!'))
                assert second.request(auth_path)[2].endswith('login.php')
                # A fixture de concorrência posterior usa versão 1; conta sem liberação após novo login.
                sql('UPDATE usuario SET versao_sessao=1 WHERE id_usuario=2',DB)
                second.login('segundo@teste.local')
                second_auth['csrf'] = second.token(auth_path)
                second.request(auth_path,second_auth)
                second.request('script/logout.php',{'csrf':second_auth['csrf']})
                second.login('segundo@teste.local')
                assert 'EVENTO_LOGIN_PROTEGIDO' not in second.request(auth_path)[1]
                assert sql("SELECT COUNT(*) FROM log WHERE descricao LIKE '%Inicial123!%' OR descricao LIKE '%errada%'",DB).strip() == '0'
                # Função de consulta também recusa acesso sem autorização; não depende só do HTML.
                direct = "require $argv[1]; $_SESSION=['id_usuario'=>1,'versao_sessao'=>1]; $c=new mysqli('localhost','root','',$argv[2]); require $argv[3]; try {buscarHistoricoLogin($c); echo 'ERRO';} catch (DomainException $e) {echo 'BLOQUEADO';}"
                output = subprocess.check_output([PHP,'-d','session.save_path='+str(session_dir),'-r',direct,str(ROOT/'script/sessao.php'),DB,str(ROOT/'script/funcoes_logs.php')]).decode()
                assert output == 'BLOQUEADO'
                # Conexões simultâneas: não ultrapassar cinco verificações por janela.
                sql("UPDATE confirmacao_identidade SET falhas='[]',bloqueado_ate=0 WHERE usuario_id=1", DB)
                worker_auth = "require $argv[1]; $_SESSION=['id_usuario'=>1,'versao_sessao'=>1]; $c=new mysqli('localhost','root','',$argv[2]); $c->set_charset('utf8mb4'); echo json_encode(confirmarIdentidade($c,'historico_autenticacao','falha-paralela'));"
                def fail_auth(_):
                    return subprocess.check_output([PHP,'-d','session.save_path='+str(session_dir),'-r',worker_auth,str(ROOT/'script/sessao.php'),DB]).decode()
                with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
                    results = list(pool.map(fail_auth,range(6)))
                assert sum('"status":429' in result for result in results) == 2, results
                assert sql('SELECT JSON_LENGTH(falhas) FROM confirmacao_identidade WHERE usuario_id=1',DB).strip() == '5'
                sql("UPDATE confirmacao_identidade SET falhas='[]',bloqueado_ate=0 WHERE usuario_id=1", DB)
                print('Confirmação: histórico isolado, CSRF, expiração, limite entre sessões, revogação e falha de auditoria aprovados.')
                for page in ['usuarios', 'cadastrar_usuario', 'cadastrar_produto', 'produtos', 'perfil', 'estoque', 'alertas', 'historico', 'graficos', 'logs', 'lancamentos']:
                    status, html, _ = admin.request('pages/' + page + '.php')
                    assert status == 200 and 'Fatal error' not in html and '<b>Warning</b>' not in html, page
                    assert 'name="csrf"' in html, page
                launch_token = admin.token('pages/lancamentos.php?tipo=Saida')
                status, html, _ = admin.request('pages/lancamentos.php', {'csrf':launch_token, 'ajax':'1', 'tipo':'Saida', 'produtos[0][produto_id]':'1', 'produtos[0][quantidade]':'1.5'})
                assert '"sucesso":false' in html and 'Nenhum item foi salvo' in html
                assert sql('SELECT COUNT(*) FROM movimentacao', DB).strip() == '0'
                assert second.login('segundo@teste.local')[2].endswith('index.php')
                assert user.login('operador@teste.local')[2].endswith('index.php')
                assert other.login('operador@teste.local')[2].endswith('index.php')
                # Administração de unidades: testar os formulários realmente renderizados.
                central = int(sql("SELECT id_unidade FROM unidade_saude WHERE nome='Secretaria de Saúde'", DB).strip())
                unit_token = admin.token('pages/cadastrar_unidade.php')
                unit_data = {'csrf':unit_token, 'nome':'UBS Teste', 'endereco':'Rua Inicial', 'telefone':'(00) 1234-5678'}
                assert admin.request('pages/cadastrar_unidade.php', dict(unit_data, csrf='invalido'))[0] == 403
                assert admin.request('pages/cadastrar_unidade.php', unit_data)[2].endswith('unidades.php')
                uid = int(sql("SELECT id_unidade FROM unidade_saude WHERE nome='UBS Teste'", DB).strip())
                assert 'Já existe' in admin.request('pages/cadastrar_unidade.php', unit_data)[1]
                assert 'até 100' in admin.request('pages/cadastrar_unidade.php', dict(unit_data, nome='Á'*101))[1]
                assert 'até 255' in admin.request('pages/cadastrar_unidade.php', dict(unit_data, endereco='a'*256))[1]
                assert 'até 20' in admin.request('pages/cadastrar_unidade.php', dict(unit_data, telefone='1'*21))[1]
                assert 'obrigatório' in admin.request('pages/cadastrar_unidade.php', dict(unit_data, nome=' '))[1]
                for role in ['Usuario']:
                    sql(f"UPDATE usuario SET tipo='{role}' WHERE id_usuario=3", DB)
                    assert 'pages/unidades.php' not in user.request('index.php')[1]
                    for path in ['unidades.php', 'cadastrar_unidade.php', f'editar_unidade.php?id={uid}']:
                        assert user.request('pages/'+path)[0] == 403
                        assert user.request('pages/'+path, dict(unit_data, csrf=user.token('pages/perfil.php')))[0] == 403
                sql("UPDATE usuario SET tipo='Usuario' WHERE id_usuario=3", DB)
                assert 'pages/unidades.php' in admin.request('index.php')[1]
                assert admin.request(f'pages/editar_unidade.php?id={uid}', dict(unit_data, endereco='Rua Nova'))[2].endswith('unidades.php')
                assert sql(f'SELECT endereco FROM unidade_saude WHERE id_unidade={uid}', DB).strip() == 'Rua Nova'
                html = admin.request(f'pages/editar_unidade.php?id={uid}', dict(unit_data, nome='', endereco='Preservar preenchimento'))[1]
                assert 'Preservar preenchimento' in html
                assert admin.request('pages/editar_unidade.php?id=999999')[0] == 404
                html = admin.request(f'pages/editar_unidade.php?id={central}', unit_data)[1]
                assert 'não pode ser alterado' in html
                central_data = dict(unit_data, nome='Secretaria de Saúde', endereco='Endereço central')
                assert admin.request(f'pages/editar_unidade.php?id={central}', central_data)[2].endswith('unidades.php')
                assert 'não pode ser desativada' in admin.request('pages/unidades.php', {'csrf':unit_token,'desativar_id':central})[1]
                assert 'não encontrada' in admin.request('pages/unidades.php', {'csrf':unit_token,'desativar_id':999999})[1]
                listing = admin.request('pages/unidades.php')[1]
                assert '<?=' not in listing
                forms = re.findall(r'<form\b[^>]*>(.*?)</form>', listing, re.S)
                form = next(f for f in forms if re.search(r'name="desativar_id"\s+value="'+str(uid)+'"', f))
                actual_token = re.search(r'name="csrf" value="([a-f0-9]+)"', form)[1]
                assert admin.request('pages/unidades.php', {'desativar_id':uid})[0] == 403
                admin.request('pages/unidades.php', {'csrf':actual_token,'desativar_id':uid})
                assert sql(f'SELECT ativo FROM unidade_saude WHERE id_unidade={uid}', DB).strip() == '0'
                assert 'UBS Teste' not in admin.request('pages/lancamentos.php?tipo=Saida')[1]
                # Uma saída forjada para unidade inativa é recusada pelo servidor.
                sql("INSERT INTO produto(nome,unidade,estoque,estoque_minimo,categoria_id) SELECT 'Produto UBS','Unidade',5,0,MIN(id_categoria) FROM categoria", DB)
                pid = int(sql("SELECT id_produto FROM produto WHERE nome='Produto UBS'", DB).strip())
                movement = {'csrf':unit_token,'ajax':'1','tipo':'Saida','unidade_destino':uid,'produtos[0][produto_id]':pid,'produtos[0][quantidade]':1}
                assert '"sucesso":false' in admin.request('pages/lancamentos.php', movement)[1]
                inactive = admin.request('pages/unidades.php?status=inativos')[1]
                form = next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>', inactive, re.S) if 'reativar_id' in f)
                assert re.search(r'name="csrf" value="([a-f0-9]+)"', form)
                admin.request('pages/unidades.php', {'csrf':unit_token,'reativar_id':uid})
                assert '"sucesso":true' in admin.request('pages/lancamentos.php', movement)[1]
                count = sql(f'SELECT COUNT(*) FROM movimentacao WHERE unidade_destino_id={uid}', DB).strip()
                admin.request('pages/unidades.php', {'csrf':unit_token,'desativar_id':uid})
                assert sql(f'SELECT COUNT(*) FROM movimentacao WHERE unidade_destino_id={uid}', DB).strip() == count
                sql("CREATE TRIGGER falha_unidade_log BEFORE INSERT ON log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';", DB)
                assert 'Nenhuma alteração' in admin.request('pages/cadastrar_unidade.php', dict(unit_data, nome='Nao gravar'))[1]
                assert 'Nenhuma alteração' in admin.request(f'pages/editar_unidade.php?id={uid}', dict(unit_data, endereco='Nao gravar'))[1]
                assert 'Nenhuma alteração' in admin.request('pages/unidades.php', {'csrf':unit_token,'reativar_id':uid})[1]
                assert sql("SELECT COUNT(*) FROM unidade_saude WHERE nome='Nao gravar'", DB).strip() == '0'
                assert sql(f'SELECT endereco FROM unidade_saude WHERE id_unidade={uid}', DB).strip() == 'Rua Nova'
                assert sql(f'SELECT ativo FROM unidade_saude WHERE id_unidade={uid}', DB).strip() == '0'
                sql('DROP TRIGGER falha_unidade_log;', DB)
                # Suporte: permissões intermediárias sem mudança de acesso ou saldo.
                sql("INSERT INTO usuario(nome,email,senha,tipo) SELECT 'Apoio','apoio@teste.local',senha,'Suporte' FROM usuario WHERE id_usuario=1", DB)
                support = Browser()
                assert support.login('apoio@teste.local')[2].endswith('index.php')
                sid = int(sql("SELECT id_usuario FROM usuario WHERE email='apoio@teste.local'", DB).strip())
                st = support.token('pages/perfil.php')
                # Proteção de e-mails: HTML, permissões, confirmação e transação.
                email_path = 'pages/editar_usuario.php?id=3'
                et = admin.token(email_path)
                reveal = {'csrf':et,'acao':'consultar_email','senha_confirmacao':'Inicial123!'}
                correction = {'csrf':et,'acao':'corrigir_email','novo_email':'corrigido@teste.local','senha_confirmacao':'Inicial123!'}
                for browser in [admin, support]:
                    assert 'operador@teste.local' not in browser.request('pages/usuarios.php')[1]
                    assert 'operador@teste.local' not in browser.request(email_path)[1]
                    assert 'no-store' in browser.headers.get('Cache-Control','')
                assert 'operador@teste.local' in user.request('pages/perfil.php')[1]
                assert 'name="email"' not in user.request('pages/perfil.php')[1]
                assert user.request('pages/perfil.php',{'csrf':user.token('pages/perfil.php'),'nome':'Operador','email':'invasor@teste.local'})[0] == 403
                assert support.request(email_path,dict(reveal,csrf=st))[0] == 403
                assert support.request(email_path,dict(correction,csrf=st))[0] == 403
                assert support.request(email_path,{'csrf':st,'nome':'Operador','email':'invasor@teste.local'})[0] == 403
                assert admin.request(email_path,dict(correction,csrf='invalido'))[0] == 403
                assert admin.request(email_path,{'csrf':et,'nome':'Operador','email':'invasor@teste.local','tipo':'Usuario','ativo':'1'})[0] == 403
                # Uma autorização do histórico não libera e-mails.
                admin.request(auth_path,{'csrf':et,'confirmar_historico_login':'1','senha_confirmacao':'Inicial123!'})
                assert 'Senha incorreta' in admin.request(email_path,dict(reveal,senha_confirmacao=''))[1]
                assert 'operador@teste.local' in admin.request(email_path,reveal)[1]
                assert 'operador@teste.local' not in admin.request(email_path)[1]
                assert 'operador@teste.local' in admin.request(email_path,dict(reveal,senha_confirmacao=''))[1]
                assert sql("SELECT COUNT(*) FROM log WHERE acao='Consulta de e-mail' AND descricao='Conta consultada: ID 3.'",DB).strip() == '2'
                session_fixture(admin,"$_SESSION['confirmacoes_identidade']['consultar_email']['expira_em']=time()-1")
                assert 'operador@teste.local' not in admin.request(email_path,dict(reveal,senha_confirmacao=''))[1]
                admin.request(email_path,reveal)
                # Consulta liberada não substitui senha na correção.
                assert 'Senha incorreta' in admin.request(email_path,dict(correction,senha_confirmacao='errada'))[1]
                assert sql('SELECT email FROM usuario WHERE id_usuario=3',DB).strip() == 'operador@teste.local'
                assert 'e-mail válido' in admin.request(email_path,dict(correction,novo_email='invalido'))[1]
                sql('UPDATE usuario SET ativo=0 WHERE id_usuario=2',DB)
                assert 'já está cadastrado' in admin.request(email_path,dict(correction,novo_email='segundo@teste.local'))[1]
                sql('UPDATE usuario SET ativo=1 WHERE id_usuario=2',DB)
                # Falha somente no log da correção: confirmação pode passar, mas dado volta.
                sql("DELIMITER $$\nCREATE TRIGGER falha_email BEFORE INSERT ON log FOR EACH ROW BEGIN IF NEW.acao='Correção de e-mail' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada'; END IF; END$$\nDELIMITER ;\n",DB)
                assert 'Nenhuma alteração' in admin.request(email_path,correction)[1]
                assert sql('SELECT email FROM usuario WHERE id_usuario=3',DB).strip() == 'operador@teste.local'
                sql('DROP TRIGGER falha_email',DB)
                assert 'E-mail corrigido' in admin.request(email_path,correction)[1]
                assert sql('SELECT email FROM usuario WHERE id_usuario=3',DB).strip() == 'corrigido@teste.local'
                assert 'corrigido@teste.local' in user.request('pages/perfil.php')[1]
                login_check = Browser()
                assert 'incorretos' in login_check.login('operador@teste.local')[1]
                assert login_check.login('corrigido@teste.local')[2].endswith('index.php')
                assert 'Senha incorreta' in admin.request(email_path,dict(correction,novo_email='outro@teste.local',senha_confirmacao=''))[1]
                assert 'E-mail corrigido' in admin.request(email_path,dict(correction,novo_email='operador@teste.local'))[1]
                # Administrador corrige o próprio identificador sem perder a sessão.
                assert 'E-mail corrigido' in admin.request('pages/editar_usuario.php?id=1',dict(correction,novo_email='adminnovo@teste.local'))[1]
                assert 'adminnovo@teste.local' in admin.request('pages/perfil.php')[1]
                assert 'E-mail corrigido' in admin.request('pages/editar_usuario.php?id=1',dict(correction,novo_email='admin@teste.local'))[1]
                # Auditoria sensível não aparece no histórico operacional do suporte.
                operational = support.request('pages/logs.php')[1]
                assert 'Consulta de e-mail' not in operational and 'Correção de e-mail' not in operational
                assert sql("SELECT COUNT(*) FROM log WHERE acao IN ('Consulta de e-mail','Correção de e-mail','Confirmação de identidade para e-mail') AND (descricao LIKE '%@%' OR descricao LIKE '%Inicial123!%')",DB).strip() == '0'
                # Falha no log também impede revelar o endereço.
                sql("DELIMITER $$\nCREATE TRIGGER falha_consulta_email BEFORE INSERT ON log FOR EACH ROW BEGIN IF NEW.acao='Consulta de e-mail' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada'; END IF; END$$\nDELIMITER ;\n",DB)
                assert 'operador@teste.local' not in admin.request(email_path,reveal)[1]
                sql('DROP TRIGGER falha_consulta_email',DB)
                print('E-mails: máscaras, hierarquia, escopos, prazo, senha por correção, duplicidade, login e rollback aprovados.')
                for path in ['usuarios.php', 'logs.php', 'unidades.php', 'cadastrar_unidade.php', f'editar_produto.php?id={pid}']:
                    assert support.request('pages/'+path)[0] == 200, path
                listing = support.request('pages/usuarios.php')[1]
                assert 'pages/cadastrar_usuario.php' not in listing and 'name="excluir_id"' not in listing
                for target in [1, 2, sid]:
                    assert support.request(f'pages/editar_usuario.php?id={target}')[0] == 403
                data = {'csrf':st, 'nome':'Operador Corrigido'}
                edit_path = 'pages/editar_usuario.php?id=3'
                html = support.request(edit_path)[1]
                for field in ['tipo','ativo']:
                    assert f'name="{field}"' not in html
                    assert support.request(edit_path, dict(data, **{field:'1'}))[0] == 403
                assert support.request(edit_path, data)[2].endswith('usuarios.php')
                assert sql('SELECT nome FROM usuario WHERE id_usuario=3', DB).strip() == 'Operador Corrigido'
                assert 'name="senha"' in html
                version = sql('SELECT versao_sessao FROM usuario WHERE id_usuario=3', DB).strip()
                assert '8 e 72' in support.request(edit_path, dict(data, senha='1234'))[1]
                assert sql('SELECT versao_sessao FROM usuario WHERE id_usuario=3', DB).strip() == version
                for target in [1, 2, sid]:
                    assert support.request(f'pages/editar_usuario.php?id={target}', dict(data, senha='ResetTeste123!'))[0] == 403
                assert support.request(edit_path, dict(data, senha='ResetTeste123!'))[2].endswith('usuarios.php')
                assert user.request('index.php')[2].endswith('login.php')
                assert other.request('index.php')[2].endswith('login.php')
                assert support.request('pages/usuarios.php')[0] == 200
                assert 'incorretos' in user.login('operador@teste.local')[1]
                assert user.login('operador@teste.local', 'ResetTeste123!')[2].endswith('index.php')
                assert sql("SELECT COUNT(*) FROM log WHERE acao='Redefinição de senha pelo suporte' AND usuario_id="+str(sid), DB).strip() == '1'
                assert sql("SELECT COUNT(*) FROM log WHERE descricao LIKE '%ResetTeste123!%'", DB).strip() == '0'
                # Restaurar a credencial da fixture pelo fluxo real para os cenários seguintes.
                support.request(edit_path, dict(data, senha='Inicial123!'))
                user.login('operador@teste.local')
                other.login('operador@teste.local')

                for action in ['excluir_id','reativar_id']:
                    assert support.request('pages/usuarios.php', {'csrf':st,action:3})[0] == 403
                assert support.request('pages/cadastrar_usuario.php', dict(data, senha='Inicial123!',tipo='Usuario'))[0] == 403
                for change, restore in [("ativo=0","ativo=1"),("tipo='Suporte'","tipo='Usuario'"),("tipo='Administrador'","tipo='Usuario'")]:
                    sql(f'UPDATE usuario SET {change} WHERE id_usuario=3', DB)
                    assert support.request(edit_path, dict(data, senha='Proibida123!'))[0] == 403
                    sql(f'UPDATE usuario SET {restore} WHERE id_usuario=3', DB)
                unit_payload = {'csrf':st,'nome':'UBS Suporte','endereco':'Rua','telefone':'123'}
                assert support.request('pages/cadastrar_unidade.php', unit_payload)[2].endswith('unidades.php')
                suid = int(sql("SELECT id_unidade FROM unidade_saude WHERE nome='UBS Suporte'", DB).strip())
                assert support.request(f'pages/editar_unidade.php?id={suid}', dict(unit_payload,endereco='Outra Rua'))[2].endswith('unidades.php')
                assert support.request(f'pages/editar_unidade.php?id={central}')[0] == 403
                assert support.request('pages/unidades.php', {'csrf':st,'desativar_id':suid})[0] == 403
                assert support.request(f'pages/editar_unidade.php?id={suid}', dict(unit_payload,ativo='0'))[0] == 403
                category = int(sql('SELECT MIN(id_categoria) FROM categoria', DB).strip())
                product_data = {'csrf':st,'nome':'Produto Corrigido','categoria_id':category,'unidade':'Unidade','estoque_minimo':2}
                product_path = f'pages/editar_produto.php?id={pid}'
                html = support.request(product_path)[1]
                for field in ['estoque','estoque_original','justificativa','excluir_produto']:
                    assert f'name="{field}"' not in html
                    assert support.request(product_path,dict(product_data,**{field:'1'}))[0] == 403
                # Saldo atualizado por outra operação depois da abertura da edição.
                sql(f'UPDATE produto SET estoque=3 WHERE id_produto={pid}', DB)
                assert support.request(product_path,product_data)[2].endswith('produtos.php')
                assert sql(f'SELECT estoque FROM produto WHERE id_produto={pid}', DB).strip() == '3'
                assert sql(f'SELECT estoque_minimo FROM produto WHERE id_produto={pid}', DB).strip() == '2'
                assert support.request(product_path,dict(product_data,csrf='invalido'))[0] == 403
                sql("CREATE TRIGGER falha_suporte_log BEFORE INSERT ON log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada';", DB)
                assert 'Nenhuma alteração' in support.request(edit_path,dict(data,nome='Nao salvar',senha='NaoSalvar123!'))[1]
                assert 'Nenhuma alteração' in support.request(product_path,dict(product_data,nome='Nao salvar'))[1]
                assert 'Nenhuma alteração' in support.request(f'pages/editar_unidade.php?id={suid}',dict(unit_payload,endereco='Nao salvar'))[1]
                assert sql('SELECT nome FROM usuario WHERE id_usuario=3', DB).strip() == 'Operador Corrigido'
                assert sql(f'SELECT nome FROM produto WHERE id_produto={pid}', DB).strip() == 'Produto Corrigido'
                assert sql(f'SELECT endereco FROM unidade_saude WHERE id_unidade={suid}', DB).strip() == 'Outra Rua'
                sql('DROP TRIGGER falha_suporte_log;', DB)
                assert user.login('operador@teste.local')[2].endswith('index.php')
                assert support.request('pages/perfil.php', {'csrf':st,'nome':'Apoio','senha':'OutraSenha123!','confirmar_senha':'OutraSenha123!'})[0] == 200
                assert support.request('pages/usuarios.php')[0] == 200
                sql(f"UPDATE usuario SET tipo='Usuario' WHERE id_usuario={sid}", DB)
                assert support.request('pages/usuarios.php')[0] == 403
                print('Suporte: hierarquia, campos proibidos, UBS, produtos, saldo preservado e auditoria aprovados.')
                print('UBS: formulários, permissões, validação, Secretaria, histórico e rollback aprovados.')
                assert user.request('pages/cadastrar_usuario.php')[0] == 403
                assert admin.request('pages/usuarios.php', {'excluir_id':3})[0] == 403
                token = admin.token('pages/usuarios.php')
                admin.request('pages/usuarios.php', {'csrf':token,'excluir_id':3})
                assert user.request('index.php')[2].endswith('login.php')
                admin.request('pages/usuarios.php', {'csrf':token,'reativar_id':3})
                assert other.request('index.php')[2].endswith('login.php')
                assert user.login('operador@teste.local')[2].endswith('index.php')
                assert other.login('operador@teste.local')[2].endswith('index.php')
                own = user.token('pages/perfil.php')
                user.request('pages/perfil.php', {'csrf':own,'nome':'Operador','senha':'NovaSenha123!','confirmar_senha':'NovaSenha123!'})
                assert user.request('index.php')[2].endswith('index.php')
                assert other.request('index.php')[2].endswith('login.php')
                edit = {'csrf':token,'nome':'Segundo','tipo':'Usuario','ativo':'1','senha':''}
                admin.request('pages/editar_usuario.php?id=2', edit)
                assert second.request('pages/usuarios.php')[0] == 403
                status, html, _ = admin.request('pages/editar_usuario.php?id=1', dict(edit,nome='Admin'))
                assert 'pelo menos um administrador' in html
                status, html, _ = admin.request('pages/editar_usuario.php?id=1', dict(edit,nome='Admin',tipo='Administrador',ativo='0'))
                assert 'própria conta' in html
                assert admin.request('script/logout.php')[0] == 405
                assert admin.request('script/logout.php', {'csrf':token})[2].endswith('login.php')
                # Duas conexões independentes tentam remover os dois administradores ao mesmo tempo.
                sql("UPDATE usuario SET tipo='Administrador' WHERE id_usuario IN (1,2)", DB)
                worker = "require $argv[1]; $c=new mysqli('localhost','root','',$argv[2]); $c->set_charset('utf8mb4'); $id=(int)$argv[3]; $_SESSION=['id_usuario'=>$id,'versao_sessao'=>1]; $u=buscarUsuarioPorId($c,$id); echo json_encode(atualizarUsuario($c,$id,$u['nome'],$u['email'],'','Usuario',1));"
                def demote(identifier):
                    return subprocess.check_output([PHP, '-r', worker, str(ROOT/'script/funcoes_usuarios.php'), DB, str(identifier)]).decode()
                with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                    results = list(pool.map(demote, [1,2]))
                assert sum('"sucesso":true' in result for result in results) == 1, results
                assert sql("SELECT COUNT(*) FROM usuario WHERE tipo='Administrador' AND ativo=1", DB).strip() == '1'
                print('Inicialização, migração, páginas e rejeição de item inválido aprovadas. HTTP: login, CSRF, permissões, desativação, reativação, troca própria, último administrador e logout aprovados.')
            finally:
                server.terminate()
                server.wait(timeout=10)
                server = None

finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
    sql(f'DROP DATABASE `{DB}`;')
