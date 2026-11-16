<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/storage.php';

final class PrimewayChatUploadValidationError extends RuntimeException {}

function primewayChatStorageRoot(): string
{
    $configured = primewayStorageBase();

    if (is_string($configured) && trim($configured) !== '') {
        $base = rtrim(trim($configured), "\\/");
    } else {
        $localAppData = getenv('LOCALAPPDATA');
        $userProfile = getenv('USERPROFILE');

        if (is_string($localAppData) && trim($localAppData) !== '') {
            $base = rtrim(trim($localAppData), "\\/") . DIRECTORY_SEPARATOR . 'PrimeWay' . DIRECTORY_SEPARATOR . 'storage';
        } elseif (is_string($userProfile) && trim($userProfile) !== '') {
            $base = rtrim(trim($userProfile), "\\/") . DIRECTORY_SEPARATOR . 'PrimeWayStorage';
        } else {
            $base = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'primeway-storage';
        }
    }

    return $base . DIRECTORY_SEPARATOR . 'chat';
}

function primewayChatEnsureStorageDirectory(string $relativeDirectory): string
{
    $root = primewayChatStorageRoot();
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento do chat.');
    }

    return $path;
}

function primewayChatAllowedExtensions(): array
{
    return [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'json',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'webm', 'ogg', 'mp3', 'm4a', 'wav', 'mp4'
    ];
}

function primewayChatAllowedMimeTypes(): array
{
    return [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/csv',
        'application/json',
        // O tipo genérico só passa após conferência do conteúdo Office.
        'application/octet-stream',
        'application/x-ole-storage',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'audio/webm',
        'video/webm',
        'audio/ogg',
        'application/ogg',
        'audio/mpeg',
        'audio/mp4',
        'video/mp4',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/vnd.wave'
    ];
}

function primewayChatValidateUpload(array $file): array
{
    foreach (['error','tmp_name','name','type','size'] as $key) {
        if (isset($file[$key]) && !is_scalar($file[$key])) {
            throw new PrimewayChatUploadValidationError('Arquivo enviado inválido.');
        }
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'O envio do arquivo foi interrompido.',
            UPLOAD_ERR_NO_FILE => 'Selecione um arquivo para enviar.',
            default => 'Não foi possível receber o arquivo enviado.'
        };
        throw new PrimewayChatUploadValidationError($message);
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $originalName = basename(str_replace('\\', '/', trim((string) ($file['name'] ?? ''))));
    $clientMimeType = strtolower(trim((string) ($file['type'] ?? '')));
    $size = (int) ($file['size'] ?? 0);

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new PrimewayChatUploadValidationError('Arquivo enviado inválido.');
    }
    $size = (int)filesize($temporaryPath);
    if ($size <= 0) {
        throw new PrimewayChatUploadValidationError('O arquivo está vazio.');
    }
    if ($size > 20 * 1024 * 1024) {
        throw new PrimewayChatUploadValidationError('O arquivo deve possuir no máximo 20 MB.');
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, primewayChatAllowedExtensions(), true)) {
        throw new PrimewayChatUploadValidationError('Formato de arquivo não permitido no chat.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = strtolower((string) $finfo->file($temporaryPath));

    if ($extension === 'webm' && $mimeType === 'video/webm' && str_starts_with($clientMimeType, 'audio/webm')) {
        $mimeType = 'audio/webm';
    }
    if ($extension === 'ogg' && $mimeType === 'application/ogg' && str_starts_with($clientMimeType, 'audio/ogg')) {
        $mimeType = 'audio/ogg';
    }
    if ($extension === 'm4a' && $mimeType === 'video/mp4') {
        $mimeType = 'audio/mp4';
    }

    if (!in_array($mimeType, primewayChatAllowedMimeTypes(), true)) {
        throw new PrimewayChatUploadValidationError('O tipo real do arquivo não é permitido no chat.');
    }

    if ($mimeType === 'application/zip' && !in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
        throw new PrimewayChatUploadValidationError('Arquivos ZIP não são permitidos no chat.');
    }

    primewayChatValidateContent($temporaryPath, $extension, $mimeType);

    $audioExtensions = ['webm', 'ogg', 'mp3', 'm4a', 'wav'];
    $isAudio = in_array($extension, $audioExtensions, true)
        && (
            str_starts_with($mimeType, 'audio/')
            || in_array($mimeType, ['video/webm', 'application/ogg'], true)
        );

    if ($extension === 'webm' && $mimeType === 'video/webm' && str_starts_with($clientMimeType, 'video/')) {
        $isAudio = false;
    }

    if (in_array($extension, ['ogg', 'mp3', 'm4a', 'wav'], true) && !$isAudio) {
        throw new PrimewayChatUploadValidationError('O arquivo selecionado não é um áudio válido.');
    }

    $safeOriginalName = preg_replace('/[^\pL\pN._()\- ]+/u', '_', $originalName);
    $safeOriginalName = trim((string) $safeOriginalName);
    if ($safeOriginalName === '') {
        $safeOriginalName = 'arquivo.' . $extension;
    }

    return [
        'temporaryPath' => $temporaryPath,
        'originalName' => mb_substr($safeOriginalName, 0, 255),
        'extension' => $extension,
        'mimeType' => $mimeType,
        'size' => $size,
        'kind' => $isAudio ? 'audio' : primewayChatAttachmentKind($mimeType)
    ];
}

/**
 * Analisa apenas metadados do ZIP, sem extrair nem abrir arquivos para escrita.
 * Limites reduzem abuso por arquivos Office com milhares de entradas ou
 * conteúdo descompactado desproporcional. Usado pelo chat e pelas atividades.
 */
function primewayChatOfficePackageSafe(
    PharData $archive,
    string $requiredEntry,
    int $archiveBytes,
    int $maxEntries = 1500,
    int $maxExpandedBytes = 125829120,
    int $maxRatio = 150
): bool {
    if (!$archive->isFileFormat(Phar::ZIP)
        || !isset($archive['[Content_Types].xml'], $archive[$requiredEntry])) {
        return false;
    }
    $entries = 0;
    $expanded = 0;
    try {
        foreach (new RecursiveIteratorIterator($archive) as $entry) {
            if (!$entry instanceof PharFileInfo || !$entry->isFile() || $entry->isLink()) return false;
            if (strtolower($entry->getFilename()) === 'vbaproject.bin') return false;
            $bytes = $entry->getSize();
            if (!is_int($bytes) || $bytes < 0 || $bytes > $maxExpandedBytes) return false;
            $expanded += $bytes;
            $entries++;
            if ($entries > $maxEntries || $expanded > $maxExpandedBytes) return false;
        }
        if ($archiveBytes < 1) return false;
        // Arquivos pequenos podem ser muito compressíveis sem serem perigosos.
        if ($expanded > 4 * 1024 * 1024 && $expanded > $archiveBytes * $maxRatio) return false;
        return $entries >= 2;
    } catch (Throwable $error) {
        return false;
    }
}

// MIME real deve corresponder à extensão; ZIP genérico não é documento Office.
function primewayChatValidateContent(string $path, string $extension, string $mime): void
{
    $office = [
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','word/document.xml'],
        'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xl/workbook.xml'],
        'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','ppt/presentation.xml']
    ];
    if (isset($office[$extension])) {
        if (!in_array($mime, [$office[$extension][0],'application/zip','application/octet-stream'], true)) {
            throw new PrimewayChatUploadValidationError('O conteúdo não corresponde ao documento informado.');
        }
        // O PHP grava uploads em arquivo temporário sem extensão. PharData
        // precisa do sufixo .zip para inspecionar os arquivos Office OOXML.
        // A cópia é privada, temporária e é sempre removida após a validação.
        $inspectionPath = $path . '.zip';
        $valid = false;
        try {
            if (!copy($path, $inspectionPath)) {
                throw new RuntimeException('Falha ao preparar inspeção do documento.');
            }
            $archive = new PharData($inspectionPath);
            $valid = primewayChatOfficePackageSafe($archive, $office[$extension][1], (int)filesize($path));
        } catch (Throwable $error) {
            $valid = false;
        } finally {
            if (is_file($inspectionPath)) @unlink($inspectionPath);
        }
        if (!$valid) throw new PrimewayChatUploadValidationError('Documento Office inválido ou com macros não permitidas.');
        return;
    }
    $types = [
        'pdf'=>['application/pdf'], 'png'=>['image/png'], 'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'],
        'gif'=>['image/gif'], 'webp'=>['image/webp'],
        'txt'=>['text/plain'], 'csv'=>['text/plain','text/csv','application/csv'],
        'json'=>['text/plain','application/json'],
        'doc'=>['application/msword'], 'xls'=>['application/vnd.ms-excel'], 'ppt'=>['application/vnd.ms-powerpoint'],
        'webm'=>['audio/webm','video/webm'], 'ogg'=>['audio/ogg','application/ogg'],
        'mp3'=>['audio/mpeg'], 'm4a'=>['audio/mp4','video/mp4','audio/x-m4a'],
        'wav'=>['audio/wav','audio/x-wav','audio/vnd.wave'],
        'mp4'=>['video/mp4']
    ];
    if (in_array($extension, ['doc','xls','ppt'], true) && in_array($mime,['application/octet-stream','application/x-ole-storage'],true)) {
        $handle=fopen($path,'rb');$magic=$handle?fread($handle,8):'';if($handle)fclose($handle);
        if ($magic === hex2bin('d0cf11e0a1b11ae1')) return;
    }
    if (!in_array($mime, $types[$extension] ?? [], true)) {
        throw new PrimewayChatUploadValidationError('O conteúdo do arquivo não corresponde ao tipo informado.');
    }
}

function primewayChatAttachmentMessageType(string $kind, string $content): string
{
    if ($kind === 'audio') {
        if (trim($content) !== '') {
            throw new PrimewayChatUploadValidationError(
                'Envie o áudio separadamente, sem texto na mesma mensagem.'
            );
        }
        return 'audio';
    }

    return trim($content) !== '' ? 'misto' : 'arquivo';
}

function primewayChatAttachmentKind(string $mimeType): string
{
    if (str_starts_with($mimeType, 'image/')) {
        return 'image';
    }
    if ($mimeType === 'application/pdf') {
        return 'pdf';
    }
    if (in_array($mimeType, ['text/plain','text/csv','application/csv','application/json'], true)) {
        return 'text';
    }
    if (str_starts_with($mimeType, 'video/')) {
        return 'video';
    }
    if (
        str_starts_with($mimeType, 'audio/')
        || $mimeType === 'application/ogg'
    ) {
        return 'audio';
    }
    return 'document';
}
