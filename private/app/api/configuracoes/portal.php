<?php
declare(strict_types=1);
require_once __DIR__.'/_portal.php';
primewayExigirMetodo('GET');
$user=primewayExigirPerfis(['admin','secretaria','professor','aluno','responsavel']);
try {
    $values=primewayPortalRead(primewayPdo());
    $result=['success'=>true,'settings'=>primewayPortalPublic($values)];
    // SEO é uma configuração institucional, não uma informação pessoal dos alunos.
    if($user['perfil']==='admin'){
        $result['settings']['seoTitle']=$values['seo_titulo'];
        $result['settings']['seoDescription']=$values['seo_descricao'];
    }
    primewayResponderJson($result);
} catch(Throwable $e){
    error_log('PrimeWay portal settings read: '.$e->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível consultar as preferências institucionais.'],500);
}
