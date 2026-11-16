<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/_perfil.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??''));
if (!in_array($method,['GET','POST','DELETE'],true)) {
    header('Allow: GET, POST, DELETE');
    primewayResponderJson(['success'=>false,'message'=>'Método não permitido.'],405);
}
$usuario=primewayExigirAutenticacao();
$id=(int)$usuario['id'];
if ($method!=='GET') primewayExigirCsrf();

try {
    if ($method==='GET') {
        $path=primewayPerfilFotoCaminho($id);
        if ($path===null) primewayResponderJson(['success'=>false,'message'=>'Foto não cadastrada.'],404);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime,['image/jpeg','image/png'],true)) {
            primewayResponderJson(['success'=>false,'message'=>'Foto indisponível.'],404);
        }
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: inline; filename="perfil.'.($mime==='image/png'?'png':'jpg').'"');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }

    $dir=primewayPerfilDiretorio($id);
    if ($method==='DELETE') {
        foreach (['jpg','png'] as $ext) {
            $file=$dir.'/foto.'.$ext;
            if (is_file($file) && !is_link($file)) unlink($file);
        }
        primewayResponderJson(['success'=>true,'hasPhoto'=>false]);
    }

    $file=$_FILES['photo']??null;
    if (!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) {
        primewayResponderJson(['success'=>false,'message'=>'Selecione uma imagem JPG ou PNG.'],422);
    }
    $size=(int)($file['size']??0);
    if ($size<1 || $size>2*1024*1024) {
        primewayResponderJson(['success'=>false,'message'=>'A foto deve ter no máximo 2 MB.'],422);
    }
    $temporary=(string)($file['tmp_name']??'');
    if ($temporary==='' || !is_uploaded_file($temporary)) {
        primewayResponderJson(['success'=>false,'message'=>'Arquivo de imagem inválido.'],422);
    }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png',default=>null};
    $dimensions=@getimagesize($temporary);
    if ($ext===null || !is_array($dimensions) || ($dimensions['mime']??'')!==$mime
        || $dimensions[0]<1 || $dimensions[1]<1 || $dimensions[0]>2000 || $dimensions[1]>2000) {
        primewayResponderJson(['success'=>false,'message'=>'Envie um JPG ou PNG válido de até 2000 × 2000 pixels.'],422);
    }
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) {
        throw new RuntimeException('Falha ao criar diretório privado.');
    }
    if (is_link($dir)) throw new RuntimeException('Diretório de foto inválido.');
    $temp=$dir.'/upload-'.bin2hex(random_bytes(12)).'.tmp';
    if (!move_uploaded_file($temporary,$temp)) throw new RuntimeException('Não foi possível salvar a foto.');
    chmod($temp,0600);
    $dest=$dir.'/foto.'.$ext;
    if (!rename($temp,$dest)) {
        @unlink($temp);
        throw new RuntimeException('Não foi possível concluir o upload.');
    }
    $other=$dir.'/foto.'.($ext==='png'?'jpg':'png');
    if (is_file($other) && !is_link($other)) unlink($other);
    primewayResponderJson(['success'=>true,'hasPhoto'=>true]);
} catch (Throwable $error) {
    error_log('PrimeWay foto do perfil: '.$error->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível processar a foto do perfil.'],500);
}
