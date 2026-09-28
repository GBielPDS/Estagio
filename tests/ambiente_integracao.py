"""Configuração .env em diretório temporário; não altera o .env real nem o banco."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PHP = 'C:/xampp/php/php.exe'
clean = {k:v for k,v in os.environ.items() if not k.startswith('GESTSAUDE_RECAPTCHA_')}
with tempfile.TemporaryDirectory(prefix='gestsaude-env-') as temp:
    temp = Path(temp)
    (temp/'script').mkdir(); (temp/'vendor').mkdir()
    for name in ['ambiente.php','configuracao.php','captcha.php']:
        shutil.copyfile(ROOT/'script'/name,temp/'script'/name)
    (temp/'vendor/autoload.php').write_text("<?php require '"+ROOT.as_posix()+"/vendor/autoload.php';",encoding='utf-8')
    file = temp/'.env'
    code = "require $argv[1].'/script/configuracao.php'; require $argv[1].'/script/captcha.php'; echo json_encode(configuracaoRecaptcha());"
    def run(extra=None):
        return subprocess.run([PHP,'-r',code,str(temp)],env=dict(clean,**(extra or {})),capture_output=True,text=True,encoding='utf-8')
    # Arquivo ausente: ambiente de servidor continua suportado, padrão local desligado.
    r=run(); assert r.returncode==0 and json.loads(r.stdout)['ativo'] is False
    file.write_text('GESTSAUDE_RECAPTCHA_ATIVO=1\nGESTSAUDE_RECAPTCHA_SITE_KEY="publica-teste"\nGESTSAUDE_RECAPTCHA_SECRET_KEY="segredo-sintetico"\nGESTSAUDE_RECAPTCHA_HOSTNAMES=localhost\n',encoding='utf-8')
    r=run();c=json.loads(r.stdout);assert c['ativo'] and c['valida'] and c['secret']=='segredo-sintetico'
    c=json.loads(run({'GESTSAUDE_RECAPTCHA_ATIVO':'0'}).stdout);assert c['ativo'] is False
    c=json.loads(run({'GESTSAUDE_RECAPTCHA_SECRET_KEY':'segredo-do-servidor'}).stdout);assert c['secret']=='segredo-do-servidor'
    # Valor vazio definido no servidor também tem prioridade.
    c=json.loads(run({'GESTSAUDE_RECAPTCHA_SECRET_KEY':''}).stdout);assert not c['valida']
    file.write_text('GESTSAUDE_RECAPTCHA_ATIVO=1\n',encoding='utf-8')
    c=json.loads(run().stdout);assert c['ativo'] and not c['valida']
    file.write_text('GESTSAUDE_RECAPTCHA_ATIVO=1\nSEGREDO=valor secreto invalido\n',encoding='utf-8')
    r=run();assert r.returncode!=0 and 'valor secreto' not in r.stdout+r.stderr
    file.write_text('GESTSAUDE_RECAPTCHA_SITE_KEY=teste\n',encoding='utf-8')
    assert run().returncode!=0
    file.write_text('GESTSAUDE_RECAPTCHA_ATIVO=0\n',encoding='utf-8')
    (temp/'vendor/autoload.php').unlink()
    r=run();assert r.returncode!=0 and 'Fatal error' not in r.stdout+r.stderr
print('OK: .env, valores vazios, prioridade do servidor, arquivo ausente/inválido e dependências ausentes.')
