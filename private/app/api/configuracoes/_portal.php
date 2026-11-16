<?php
declare(strict_types=1);

require_once __DIR__.'/../_bootstrap.php';

const PRIMEWAY_PORTAL_KEYS = [
    'banner_ativo' => '0',
    'banner_texto' => '',
    'privacidade_email' => '',
    'privacidade_aviso' => '',
    'seo_titulo' => '',
    'seo_descricao' => ''
];

function primewayPortalRead(PDO $pdo): array {
    $values=PRIMEWAY_PORTAL_KEYS;
    $stmt=$pdo->prepare("SELECT chave,valor FROM configuracoes_sistema WHERE grupo='portal' AND chave IN
      ('banner_ativo','banner_texto','privacidade_email','privacidade_aviso','seo_titulo','seo_descricao')");
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(array_key_exists($row['chave'],$values))$values[$row['chave']]=(string)($row['valor']??'');
    }
    return $values;
}
function primewayPortalPublic(array $values): array {
    return [
        'bannerEnabled'=>$values['banner_ativo']==='1',
        'bannerText'=>$values['banner_texto'],
        'privacyEmail'=>$values['privacidade_email'],
        'privacyNotice'=>$values['privacidade_aviso']
    ];
}
