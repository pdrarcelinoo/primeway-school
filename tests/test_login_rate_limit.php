<?php
declare(strict_types=1);

// Teste sem banco, rede, contas reais ou efeitos no storage de produção.
$root=sys_get_temp_dir().'/primeway-login-rate-test-'.bin2hex(random_bytes(8));
if (!mkdir($root,0700,true)) throw new RuntimeException('Sem diretório temporário.');
putenv('PRIMEWAY_STORAGE_DIR='.$root);
putenv('PRIMEWAY_APP_ENV=test');
$_SERVER['REMOTE_ADDR']='192.0.2.17';
require_once dirname(__DIR__).'/private/app/config/login_rate_limit.php';
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$email='conta-de-teste@example.invalid';
try {
    $initial=primewayLoginRateStatus($email);
    // A consulta de credenciais desconhecidas não precisa criar arquivo no disco.
    $filesBefore=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)
    );
    foreach($filesBefore as $entry) {
        check($entry->isDir() || $entry->getExtension()!=='json',
            'Consulta inicial não deve criar um arquivo de contador.');
    }

    check(!$initial['bloqueado'],'Nova conta não pode iniciar bloqueada.');
    for($n=1;$n<=9;$n++){
        $s=primewayLoginRateFailure($email);
        check(!$s['bloqueado'],"Bloqueio antes da décima falha: $n");
    }
    $blocked=primewayLoginRateFailure($email);
    check($blocked['bloqueado'],'Dez falhas devem bloquear a dupla conta/IP.');
    check($blocked['restante']===0,'Contagem deve chegar a zero.');
    check($blocked['tentar_novamente_em']>0,'Retry-After precisa ser positivo.');
    // O estado reside no volume, e não no cookie da sessão.
    $_SESSION=[];
    check(primewayLoginRateStatus($email)['bloqueado'],'Nova sessão não pode ignorar o limite.');
    // Outra origem não deve ficar bloqueada por falhas da primeira.
    $_SERVER['REMOTE_ADDR']='198.51.100.42';
    check(!primewayLoginRateStatus($email)['bloqueado'],'Outro IP deve manter seu próprio limite.');
    $_SERVER['REMOTE_ADDR']='192.0.2.17';
    primewayLoginRateClear($email);
    check(!primewayLoginRateStatus($email)['bloqueado'],'Login válido deve liberar o par conta/IP.');
    check(!primewayLoginRateStatus('outra-conta@example.invalid')['bloqueado'],'Uma conta não pode bloquear outra no mesmo IP.');
    // Repetição de falhas de contas distintas deve contar também para o IP.
    $ip=primewayLoginRateIpKey();
    for($n=0;$n<290;$n++)primewayLoginRateCounter($ip,300,'failure');
    check(primewayLoginRateCounter($ip,300)['bloqueado'],'Limite de IP deve conter rotação de e-mails.');
    check(primewayLoginRateStatus('terceira-conta@example.invalid')['bloqueado'],'Trocar e-mail não pode ignorar limite de IP.');
    // Mesmo bloqueado, consultas de endereços aleatórios não geram
    // arquivos novos, prevenindo esgotamento do armazenamento.
    $beforeCount=0;
    $before=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)
    );
    foreach($before as $entry)if($entry->isFile())++$beforeCount;
    for($n=0;$n<15;$n++) {
        check(primewayLoginRateStatus('rotativo-'.$n.'@example.invalid')['bloqueado'],
            'Limite global deve permanecer ativo.');
        primewayLoginRateFailure('rotativo-'.$n.'@example.invalid');
    }
    $afterCount=0;
    $after=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)
    );
    foreach($after as $entry)if($entry->isFile())++$afterCount;
    check($afterCount===$beforeCount,'IP bloqueado não deve criar contadores arbitrários.');

    echo "Segurança do login: persistência, limites, origem e recuperação aprovados.\n";
} finally {
    $iterator=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach($iterator as $item){
        $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    }
    rmdir($root);
}
