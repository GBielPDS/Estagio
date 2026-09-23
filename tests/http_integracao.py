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
        return subprocess.check_output([PHP, str(ROOT/'bd/administrar.php'), command], env=env, input=data.encode()).decode()
    assert 'Banco atualizado' in administer('migrar')
    assert 'Banco atualizado' in administer('migrar')
    assert 'Instalação inicializada' in administer('inicializar', 'Primeiro Admin\nprimeiro@teste.local\nInicial123!\n')
    assert 'bloqueada' in administer('inicializar')
    assert sql("SELECT COUNT(*) FROM usuario WHERE tipo='Administrador' AND ativo=1", DB).strip() == '1'
    assert sql('SELECT COUNT(*) FROM categoria', DB).strip() == '6'
    assert sql('SELECT COUNT(*) FROM unidade_saude', DB).strip() == '1'
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
