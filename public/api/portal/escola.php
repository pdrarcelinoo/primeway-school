<?php
declare(strict_types=1);
// Página pública somente com metadados da escola. Nenhuma sessão ou dado pessoal.
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)){
    header('Allow: GET, HEAD',true,405);exit;
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=120, must-revalidate');
header('X-Content-Type-Options: nosniff');
$template=@file_get_contents('/var/www/html/escola.html');
if(!is_string($template)){
    http_response_code(503);exit;
}
$title='PrimeWay School';
$description='PrimeWay School — plataforma acadêmica para alunos, professores, responsáveis e escola.';
try{
    require_once '/opt/primeway/app/config/database.php';
    $stmt=primewayPdo()->prepare(
        "SELECT chave,valor FROM configuracoes_sistema
         WHERE grupo='portal' AND chave IN ('seo_titulo','seo_descricao')"
    );
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        if($row['chave']==='seo_titulo' && trim((string)$row['valor'])!==''){
            $title=mb_substr(trim((string)$row['valor']),0,70);
        }
        if($row['chave']==='seo_descricao' && trim((string)$row['valor'])!==''){
            $description=mb_substr(trim((string)$row['valor']),0,160);
        }
    }
}catch(Throwable $error){
    // A página de apresentação deve permanecer acessível mesmo sem conexão ao banco.
    error_log('PrimeWay SEO público: '.$error->getMessage());
}
$t=htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$d=htmlspecialchars($description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$template=preg_replace_callback('~<title>.*?</title>~is',
    static fn(array $m):string=>'<title>'.$t.'</title>',$template,1)??$template;
$template=preg_replace_callback('~<meta\s+name="description"\s+content="[^"]*"\s*/?>~i',
    static fn(array $m):string=>'<meta name="description" content="'.$d.'">',$template,1)??$template;
$template=str_replace('</head>',
    '<meta property="og:title" content="'.$t.'">'."\n".
    '<meta property="og:description" content="'.$d.'">'."\n".
    '</head>',$template);
if(($_SERVER['REQUEST_METHOD']??'')==='GET')echo $template;
