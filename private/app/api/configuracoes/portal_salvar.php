<?php
declare(strict_types=1);
require_once __DIR__.'/_portal.php';
primewayExigirMetodo('POST');
$user=primewayExigirPerfis(['admin']);
primewayExigirCsrf();
$input=primewayLerJson();
$expected=['bannerEnabled','bannerText','privacyEmail','privacyNotice','seoTitle','seoDescription'];
foreach(array_keys($input) as $key){
    if(!in_array($key,$expected,true)){
        primewayResponderJson(['success'=>false,'message'=>'Campo de configuração não reconhecido.'],422);
    }
}
if(count($input)!==count($expected)){
    primewayResponderJson(['success'=>false,'message'=>'Preencha os campos de configuração institucional.'],422);
}
if(!is_bool($input['bannerEnabled'])){
    primewayResponderJson(['success'=>false,'message'=>'O indicador de aviso urgente deve ser verdadeiro ou falso.'],422);
}
$limits=['bannerText'=>320,'privacyEmail'=>190,'privacyNotice'=>1400,'seoTitle'=>70,'seoDescription'=>160];
foreach($limits as $field=>$limit){
    if(!is_string($input[$field]) || mb_strlen($input[$field])>$limit){
        primewayResponderJson(['success'=>false,'message'=>'Campo '.$field.' inválido ou longo demais.'],422);
    }
    $input[$field]=trim($input[$field]);
}
if($input['bannerEnabled'] && $input['bannerText']===''){
    primewayResponderJson(['success'=>false,'message'=>'Informe o texto do aviso antes de publicá-lo.'],422);
}
if($input['privacyEmail']!=='' && !filter_var($input['privacyEmail'],FILTER_VALIDATE_EMAIL)){
    primewayResponderJson(['success'=>false,'message'=>'Informe um e-mail de privacidade válido.'],422);
}
$values=[
    'banner_ativo'=>$input['bannerEnabled']?'1':'0',
    'banner_texto'=>$input['bannerText'],
    'privacidade_email'=>$input['privacyEmail'],
    'privacidade_aviso'=>$input['privacyNotice'],
    'seo_titulo'=>$input['seoTitle'],
    'seo_descricao'=>$input['seoDescription']
];
try{
    $pdo=primewayPdo();
    $pdo->beginTransaction();
    $old=primewayPortalRead($pdo);
    $sql=$pdo->prepare("INSERT INTO configuracoes_sistema
      (grupo,chave,valor,tipo,descricao,editavel,atualizado_por_usuario_id)
      VALUES ('portal',:chave,:valor,'texto','Configuração institucional do portal',1,:usuario)
      ON DUPLICATE KEY UPDATE
        valor=IF(editavel=1,VALUES(valor),valor),
        atualizado_por_usuario_id=IF(editavel=1,VALUES(atualizado_por_usuario_id),atualizado_por_usuario_id)");
    foreach($values as $key=>$value){
        $sql->execute(['chave'=>$key,'valor'=>$value,'usuario'=>(int)$user['id']]);
    }
    $audit=$pdo->prepare("INSERT INTO auditoria
      (usuario_id,acao,entidade,descricao,dados_anteriores,dados_novos,endereco_ip,user_agent)
      VALUES (:usuario,'ATUALIZAR_PORTAL','configuracoes_sistema',
      'Preferências institucionais de aviso, privacidade e SEO atualizadas.',
      :anteriores,:novos,:ip,:agent)");
    $audit->execute([
        'usuario'=>(int)$user['id'],
        'anteriores'=>json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        'novos'=>json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        'ip'=>$_SERVER['REMOTE_ADDR']??null,
        'agent'=>isset($_SERVER['HTTP_USER_AGENT'])?mb_substr((string)$_SERVER['HTTP_USER_AGENT'],0,500):null
    ]);
    $pdo->commit();
    primewayResponderJson(['success'=>true,'message'=>'Preferências institucionais salvas.']);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    error_log('PrimeWay portal settings save: '.$e->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível salvar as preferências institucionais.'],500);
}
