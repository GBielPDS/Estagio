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
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self, path, data=None):
        request = urllib.request.Request(BASE + path, data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            response = self.opener.open(request)
        except urllib.error.HTTPError as error:
            response = error
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
    sql('ALTER TABLE usuario DROP COLUMN versao_sessao;', DB)
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
            server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', str(router)], cwd=ROOT, env=env, stdout=output, stderr=output)
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
                for role in ['Usuario', 'Suporte']:
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
                user.request('pages/perfil.php', {'csrf':own,'nome':'Operador','email':'operador@teste.local','senha':'NovaSenha123!','confirmar_senha':'NovaSenha123!'})
                assert user.request('index.php')[2].endswith('index.php')
                assert other.request('index.php')[2].endswith('login.php')
                edit = {'csrf':token,'nome':'Segundo','email':'segundo@teste.local','tipo':'Usuario','ativo':'1','senha':''}
                admin.request('pages/editar_usuario.php?id=2', edit)
                assert second.request('pages/usuarios.php')[0] == 403
                status, html, _ = admin.request('pages/editar_usuario.php?id=1', dict(edit,nome='Admin',email='admin@teste.local'))
                assert 'pelo menos um administrador' in html
                status, html, _ = admin.request('pages/editar_usuario.php?id=1', dict(edit,nome='Admin',email='admin@teste.local',tipo='Administrador',ativo='0'))
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
