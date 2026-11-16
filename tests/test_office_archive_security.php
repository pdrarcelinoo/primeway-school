<?php
declare(strict_types=1);

// Executa apenas com arquivos sintéticos em /tmp. Não toca nas contas nem nos
// documentos da escola e exercita a mesma validação dos uploads reais.
require_once dirname(__DIR__).'/private/app/api/chat/_attachments.php';

function ensure(bool $ok,string $description):void {
    if (!$ok) throw new RuntimeException($description);
}
function makeZip(string $root,string $name,array $entries):string {
    $path=$root.'/'.$name.'.zip';
    $zip=new PharData($path);
    foreach ($entries as $entry=>$data) $zip->addFromString($entry,$data);
    unset($zip);
    return $path;
}
function rejected(callable $callback,string $description):void {
    $denied=false;
    try {$callback();} catch (PrimewayChatUploadValidationError $error) {$denied=true;}
    ensure($denied,$description);
}

$dir=sys_get_temp_dir().'/primeway-office-audit-'.bin2hex(random_bytes(6));
ensure(mkdir($dir,0700,true),'Diretório descartável indisponível.');
try {
    $safe=makeZip($dir,'safe',[
        '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
        'word/document.xml'=>'<w:document/>'
    ]);
    primewayChatValidateContent($safe,'docx','application/zip');
    // Simula um upload de PHP cujo arquivo temporário não tem extensão .zip.
    $phpTemporary=$dir.'/php-upload-sem-extensao';
    ensure(copy($safe,$phpTemporary),'Não foi possível simular um upload Office.');
    primewayChatValidateContent($phpTemporary,'docx','application/zip');
    ensure(!file_exists($phpTemporary.'.zip'),'Arquivo temporário de inspeção não foi removido.');
    $zip=new PharData($safe);
    ensure(primewayChatOfficePackageSafe($zip,'word/document.xml',filesize($safe)),
        'Um DOCX legítimo simples precisa ser aceito.');
    ensure(!primewayChatOfficePackageSafe($zip,'word/document.xml',filesize($safe),1),
        'DOCX com mais entradas que o permitido deve ser rejeitado.');
    ensure(!primewayChatOfficePackageSafe($zip,'word/document.xml',filesize($safe),1500,8),
        'DOCX com tamanho descompactado acima do limite deve ser rejeitado.');
    ensure(!primewayChatOfficePackageSafe($zip,'xl/workbook.xml',filesize($safe)),
        'DOCX não pode ser aceito como XLSX.');

    $macro=makeZip($dir,'macro',[
        '[Content_Types].xml'=>'<Types/>',
        'word/document.xml'=>'<document/>',
        'word/vbaProject.bin'=>'macro'
    ]);
    rejected(static fn()=>primewayChatValidateContent($macro,'docx','application/zip'),
        'Macros são proibidas mesmo dentro de documento ZIP.');
    $fake=$dir.'/fake.zip';
    file_put_contents($fake,'não sou um arquivo ZIP');
    rejected(static fn()=>primewayChatValidateContent($fake,'docx','application/zip'),
        'Conteúdo falsificado precisa ser rejeitado.');
    rejected(static fn()=>primewayChatValidateContent($safe,'docx','image/png'),
        'MIME incompatível deve ser rejeitado.');
    rejected(static fn()=>primewayChatValidateContent($safe,'pdf','application/zip'),
        'ZIP não pode ser enviado como PDF.');

    // Limita o fator de expansão: mesmo arquivos pequenos no disco não
    // podem obrigar a aplicação a descomprimir centenas de megabytes.
    $expanded=makeZip($dir,'expanded',[
        '[Content_Types].xml'=>'<Types/>',
        'word/document.xml'=>str_repeat('A',5*1024*1024)
    ]);
    $archive=new PharData($expanded);
    ensure(!primewayChatOfficePackageSafe($archive,'word/document.xml',1,1500,120*1024*1024,150),
        'Um fator de compressão anormal precisa ser rejeitado.');
    echo "Arquivos Office: válido, MIME falso, ZIP falso, macro, entradas, tamanho e expansão testados.\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($dir);
}
