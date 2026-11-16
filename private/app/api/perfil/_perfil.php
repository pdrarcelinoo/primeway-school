<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/storage.php';

/** Fotos privadas: apenas o proprietário autenticado pode ler e modificar. */
function primewayPerfilDiretorio(int $userId): string
{
    if ($userId < 1) throw new RuntimeException('Usuário inválido.');
    $root=primewayStorageBase();
    return ($root??dirname(__DIR__,2).'/storage').'/perfis/'.$userId;
}
function primewayPerfilFotoCaminho(int $userId): ?string
{
    $dir=primewayPerfilDiretorio($userId);
    foreach (['jpg','png'] as $ext) {
        $path=$dir.'/foto.'.$ext;
        if (is_file($path) && !is_link($path)) return $path;
    }
    return null;
}
function primewayPerfilFotoExistente(int $userId): bool
{
    return primewayPerfilFotoCaminho($userId)!==null;
}
