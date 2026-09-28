"""Login: banco temporário, HTTP e processos PHP concorrentes. Não usa contas reais."""
import concurrent.futures
import http.cookiejar
import json
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
DB = 'gestsaude_login_' + secrets.token_hex(6)
ENV = dict(os.environ, GESTSAUDE_DB=DB, GESTSAUDE_RECAPTCHA_ATIVO='0')

def sql(query, database=DB):
    return subprocess.check_output([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', '-N', database], input=query.encode()).decode().strip()

def migrate():
    result = subprocess.run([PHP, str(ROOT/'bd/administrar.php'), 'migrar'], env=ENV, capture_output=True)
    assert result.returncode == 0, result.stderr.decode()

server = None
subprocess.check_call([MYSQL, '-u', 'root', '-e', f'CREATE DATABASE `{DB}` CHARACTER SET utf8mb4'])
try:
    schema = (ROOT/'bd/criar-bd.sql').read_text(encoding='utf-8').replace('CREATE DATABASE almoxarifado;', '').replace('USE almoxarifado;', '')
    sql(schema)
    # Banco antigo sem tabela e banco com migração legada; reexecução preserva contas.
    sql('DROP TABLE tentativa_login')
    migrate(); migrate()
    assert sql("SHOW COLUMNS FROM tentativa_login LIKE 'falhas'")
    sql('DROP TABLE tentativa_login')
    sql((ROOT/'bd/migracoes/003-tentativa-login.sql').read_text(encoding='utf-8'))
    sql("INSERT INTO tentativa_login(identificador,tipo,tentativas) VALUES ('legado@teste.local','conta',4)")
    # Simula interrupção após a primeira coluna: o executor deve completar as demais.
    sql('ALTER TABLE tentativa_login ADD COLUMN falhas TEXT NULL')
    migrate(); migrate()
    assert sql("SELECT tentativas FROM tentativa_login WHERE identificador='legado@teste.local'") == '4'
    hash_value = subprocess.check_output([PHP, '-r', 'echo password_hash("Inicial123!", PASSWORD_DEFAULT);']).decode()
    sql(f"INSERT INTO usuario(nome,email,senha,tipo,ativo) VALUES ('Admin','admin@teste.local','{hash_value}','Administrador',1),('Inativo','inativo@teste.local','{hash_value}','Usuario',0)")
    with tempfile.TemporaryDirectory(prefix='gestsaude-login-') as temp:
        temp = Path(temp)
        runner = temp/'login.php'
        runner.write_text("<?php require '"+ROOT.as_posix()+"/script/conexao.php'; require '"+ROOT.as_posix()+"/script/funcoes_login.php'; echo json_encode(autenticarLogin($conn,$argv[1],$argv[2],$argv[3]));", encoding='utf-8')
        def login(email='admin@teste.local', password='errada', ip='192.0.2.1'):
            result = subprocess.run([PHP,str(runner),email,password,ip],env=ENV,capture_output=True,timeout=30)
            assert result.returncode == 0, result.stderr.decode()
            return json.loads(result.stdout)
        def clear(): sql('DELETE FROM tentativa_login')
        clear()
        for _ in range(4): assert login()['status'] == 401
        assert login()['status'] == 429
        deadline = sql("SELECT bloqueio_epoch FROM tentativa_login WHERE tipo='conta'")
        assert login(password='Inicial123!')['status'] == 429
        assert sql("SELECT bloqueio_epoch FROM tentativa_login WHERE tipo='conta'") == deadline
        assert login(ip='192.0.2.2')['status'] == 429
        sql("UPDATE tentativa_login SET bloqueio_epoch=UNIX_TIMESTAMP()-1 WHERE tipo='conta'")
        assert login(password='Inicial123!')['status'] == 200
        assert sql("SELECT tentativas FROM tentativa_login WHERE tipo='ip' AND identificador='192.0.2.1'") == '5'
        clear()
        for _ in range(4): login()
        sql("UPDATE tentativa_login SET falhas=CONCAT('[',UNIX_TIMESTAMP()-901,',',UNIX_TIMESTAMP()-902,',',UNIX_TIMESTAMP()-903,',',UNIX_TIMESTAMP()-904,']')")
        assert login()['status'] == 401
        assert sql("SELECT tentativas FROM tentativa_login WHERE tipo='conta'") == '1'
        # Uma falha expira, as outras três continuam dentro da janela.
        sql("UPDATE tentativa_login SET falhas=CONCAT('[',UNIX_TIMESTAMP()-901,',',UNIX_TIMESTAMP()-20,',',UNIX_TIMESTAMP()-10,',',UNIX_TIMESTAMP()-5,']') WHERE tipo='conta'")
        assert login()['status'] == 401
        assert sql("SELECT tentativas FROM tentativa_login WHERE tipo='conta'") == '4'
        assert login()['status'] == 429
        clear()
        for n in range(9): assert login(f'ausente{n}@teste.local')['status'] == 401
        assert login('ausente9@teste.local')['status'] == 429
        assert login(password='Inicial123!')['status'] == 429
        assert login(password='Inicial123!',ip='192.0.2.2')['status'] == 200
        clear()
        assert login('inativo@teste.local', 'Inicial123!')['status'] == 401
        assert login('INATIVO@TESTE.LOCAL')['status'] == 401
        assert sql("SELECT COUNT(*) FROM tentativa_login WHERE tipo='conta'") == '1'
        clear()
        # Processos independentes: não dependem do servidor PHP single-thread ou da sessão.
        with concurrent.futures.ThreadPoolExecutor(max_workers=12) as pool:
            results = list(pool.map(lambda n: login(ip=f'192.0.2.{n+1}'), range(12)))
        assert sum(r['status']==401 for r in results)==4, results
        assert sum(r['status']==429 for r in results)==8, results
        assert sql("SELECT tentativas FROM tentativa_login WHERE tipo='conta'") == '5'
        clear()
        with concurrent.futures.ThreadPoolExecutor(max_workers=14) as pool:
            results = list(pool.map(lambda n: login(f'inexistente{n}@teste.local'), range(14)))
        assert sum(r['status']==401 for r in results)==9, results
        assert sum(r['status']==429 for r in results)==5, results
        assert sql("SELECT tentativas FROM tentativa_login WHERE tipo='ip'") == '10'
        clear()
        # Falha na gravação não libera conta válida e desfaz registros parciais.
        sql("CREATE TRIGGER falha_limite BEFORE UPDATE ON tentativa_login FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha simulada'")
        assert login(password='Inicial123!')['status'] == 503
        assert sql('SELECT COUNT(*) FROM tentativa_login') == '0'
        sql('DROP TRIGGER falha_limite')
        sql('RENAME TABLE tentativa_login TO tentativa_login_indisponivel')
        assert login(password='Inicial123!')['status'] == 503
        sql('RENAME TABLE tentativa_login_indisponivel TO tentativa_login')
        sql("INSERT INTO tentativa_login(identificador,tipo,falhas,atividade_epoch) VALUES ('velho@teste.local','conta','[]',UNIX_TIMESTAMP()-90000)")
        assert login(password='Inicial123!')['status'] == 200
        assert sql("SELECT COUNT(*) FROM tentativa_login WHERE identificador='velho@teste.local'") == '0'
        # CAPTCHA: transporte simulado, sem credenciais reais ou requisições ao Google.
        captcha_runner = temp/'captcha.php'
        captcha_runner.write_text("<?php require '"+ROOT.as_posix()+"/script/conexao.php'; require '"+ROOT.as_posix()+"/script/funcoes_login.php'; $fake=fn($dados)=>json_decode($argv[2],true); echo json_encode(autenticarLogin($conn,'admin@teste.local',$argv[3],'192.0.2.9',$argv[1],$fake));",encoding='utf-8')
        captcha_env = dict(ENV, GESTSAUDE_RECAPTCHA_ATIVO='1', GESTSAUDE_RECAPTCHA_SITE_KEY='site-teste', GESTSAUDE_RECAPTCHA_SECRET_KEY='segredo-sintetico', GESTSAUDE_RECAPTCHA_HOSTNAMES='sistema.exemplo.local')
        def captcha(token='token-teste', body=None, status=200, password='Inicial123!', env=None):
            if body is None: body=json.dumps(dict(success=True,hostname='sistema.exemplo.local'))
            result=subprocess.run([PHP,str(captcha_runner),token,json.dumps(dict(status=status,body=body)),password],env=env or captcha_env,capture_output=True,timeout=30)
            assert result.returncode == 0,result.stderr.decode()
            return json.loads(result.stdout)
        clear()
        assert captcha(token='')['status']==400
        assert captcha(status=0,body=False)['status']==503
        assert captcha(body='invalid-json')['status']==503
        assert captcha(body=json.dumps(dict(success=True,hostname='outro.local')))['status']==400
        assert captcha(body=json.dumps({'success':False,'error-codes':['timeout-or-duplicate']}))['status']==400
        assert captcha(body=json.dumps({'success':False,'error-codes':['invalid-input-secret']}))['status']==503
        assert captcha(env=dict(captcha_env,GESTSAUDE_RECAPTCHA_SECRET_KEY=''))['status']==503
        assert captcha(env=dict(captcha_env,GESTSAUDE_RECAPTCHA_ATIVO='erro'))['status']==503
        assert sql('SELECT COUNT(*) FROM tentativa_login')=='0'
        assert captcha()['status']==200
        for n in range(5): assert captcha(password='errada')['status']==(429 if n==4 else 401)
        assert captcha()['status']==429
        # HTTP: sessões diferentes, CSRF, Retry-After e sessão só após sucesso.
        clear()
        sessions = temp/'sessions'; sessions.mkdir()
        router = temp/'router.php'
        router.write_text("<?php $p=substr(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),strlen('/git/ESTAGIO/')); $f='"+ROOT.as_posix()+"/'.$p; if(!is_file($f)){http_response_code(404);exit;} chdir(dirname($f)); require $f;",encoding='utf-8')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
        base=f'http://127.0.0.1:{port}/git/ESTAGIO/'
        log=(temp/'server.log').open('w')
        server=subprocess.Popen([PHP,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}',str(router)],env=ENV,stdout=log,stderr=log)
        try:
            for _ in range(60):
                try:
                    with socket.create_connection(('127.0.0.1',port),timeout=.1): break
                except OSError: time.sleep(.1)
            def browser(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            def req(opener,path,data=None):
                try: response=opener.open(base+path,urllib.parse.urlencode(data).encode() if data is not None else None)
                except urllib.error.HTTPError as e: response=e
                return response.status,response.read().decode(),response.headers,response.url
            for n in range(5):
                path='pages/login_com_recaptcha.php' if n%2 else 'pages/login.php'
                b=browser(); html=req(b,path)[1]
                assert 'google.com/recaptcha/api.js' not in html
                token=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
                result=req(b,path,dict(csrf=token,email='admin@teste.local',senha='errada'))
                assert result[0] == (429 if n==4 else 401),result
            assert int(result[2]['Retry-After']) > 0
            assert req(b,'pages/login.php',dict(email='admin@teste.local',senha='Inicial123!'))[0]==403
            assert req(b,'pages/perfil.php')[3].endswith('login.php')
            sql('UPDATE tentativa_login SET bloqueio_epoch=UNIX_TIMESTAMP()-1')
            token=re.search(r'name="csrf" value="([a-f0-9]+)"',req(b,'pages/login.php')[1])[1]
            result=req(b,'pages/login.php',dict(csrf=token,email='admin@teste.local',senha='Inicial123!'))
            assert result[3].endswith('index.php'),result
            assert req(b,'pages/perfil.php')[0]==200
        finally:
            server.terminate();server.wait();server=None;log.close()
        # As duas URLs exigem CAPTCHA quando ativado, inclusive POST direto na comum.
        for configured in [False, True]:
            env_http = dict(captcha_env)
            if not configured: env_http['GESTSAUDE_RECAPTCHA_SECRET_KEY']=''
            log=(temp/'server-captcha.log').open('w')
            server=subprocess.Popen([PHP,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}',str(router)],env=env_http,stdout=log,stderr=log)
            try:
                for _ in range(60):
                    try:
                        with socket.create_connection(('127.0.0.1',port),timeout=.1): break
                    except OSError: time.sleep(.1)
                clear()
                for path in ['pages/login.php','pages/login_com_recaptcha.php']:
                    b=browser(); html=req(b,path)[1]
                    assert 'segredo-sintetico' not in html
                    assert ('google.com/recaptcha/api.js' in html)==configured
                    token=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
                    result=req(b,path,dict(csrf=token,email='admin@teste.local',senha='Inicial123!'))
                    assert result[0]==(400 if configured else 503),result
                    assert req(b,'pages/perfil.php')[3].endswith('login.php')
                assert sql('SELECT COUNT(*) FROM tentativa_login')=='0'
            finally:
                server.terminate();server.wait();server=None;log.close()
    print('OK: migrações, janela móvel, expiração, limites, concorrência real, falhas do banco, limpeza, HTTP nas duas URLs e CAPTCHA simulado.')
finally:
    if server: server.terminate(); server.wait()
    subprocess.check_call([MYSQL,'-u','root','-e',f'DROP DATABASE `{DB}`'])
