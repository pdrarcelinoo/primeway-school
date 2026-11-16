<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

/**
 * Bloqueio independente de cookie: par conta/IP (10 falhas) e IP de origem
 * (300 falhas) em janela deslizante de 15 minutos.
 *
 * Nunca confia em X-Forwarded-For do navegador. O armazenamento fica no
 * volume privado e os nomes de arquivo não contêm e-mail nem endereço IP.
 */
function primewayLoginRateIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'origem-desconhecida';
    }
    return $ip;
}

function primewayLoginRateCounter(string $key, int $maximum, string $action = 'status'): array
{
    if (!in_array($action, ['status', 'failure', 'clear'], true)) {
        throw new InvalidArgumentException('Ação de limite inválida.');
    }
    $root = primewayStorageBase() ?? dirname(__DIR__) . '/storage';
    $hash = hash('sha256', $key);
    $dir = $root . '/security/login/' . substr($hash, 0, 2);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Armazenamento de segurança indisponível.');
    }
    if (is_link($dir)) {
        throw new RuntimeException('Diretório de segurança inválido.');
    }

    $path = $dir . '/' . $hash . '.json';
    if (is_link($path)) {
        throw new RuntimeException('Arquivo de segurança inválido.');
    }
    // Consultas de um e-mail novo e login válido sem falhas não criam
    // arquivos desnecessários: o contador nasce somente na primeira falha.
    if ($action !== 'failure' && !is_file($path)) {
        return [
            'bloqueado' => false,
            'restante' => $maximum,
            'tentar_novamente_em' => 0
        ];
    }
    $stream = fopen($path, 'c+b');
    if ($stream === false) throw new RuntimeException('Bloqueio de segurança indisponível.');
    try {
        if (!flock($stream, LOCK_EX)) {
            throw new RuntimeException('Não foi possível obter bloqueio de segurança.');
        }
        chmod($path, 0600);
        rewind($stream);
        $contents = stream_get_contents($stream);
        if ($contents === false) throw new RuntimeException('Não foi possível ler o limite de login.');
        $decoded = json_decode($contents, true);
        $entries = is_array($decoded) ? $decoded : [];
        $now = time();
        $window = 15 * 60;
        $entries = array_values(array_filter($entries, static fn(mixed $value): bool =>
            is_int($value) && $value <= $now && $value > $now - $window
        ));
        if ($action === 'clear') $entries = [];
        if ($action === 'failure' && count($entries) < $maximum) $entries[] = $now;

        if (ftruncate($stream, 0) === false || rewind($stream) === false) {
            throw new RuntimeException('Não foi possível atualizar o limite de login.');
        }
        $encoded = json_encode($entries, JSON_THROW_ON_ERROR);
        if (fwrite($stream, $encoded) !== strlen($encoded) || !fflush($stream)) {
            throw new RuntimeException('Não foi possível persistir o limite de login.');
        }
        $blocked = count($entries) >= $maximum;
        return [
            'bloqueado' => $blocked,
            'restante' => max(0, $maximum - count($entries)),
            'tentar_novamente_em' => $blocked ? max(1, $window - ($now - $entries[0])) : 0
        ];
    } finally {
        flock($stream, LOCK_UN);
        fclose($stream);
    }
}

function primewayLoginRateAccountKey(string $email): string
{
    return 'login-account-ip:' . strtolower(trim($email)) . "\n" . primewayLoginRateIp();
}

function primewayLoginRateIpKey(): string
{
    return 'login-ip:' . primewayLoginRateIp();
}

function primewayLoginRateCombine(array ...$statuses): array
{
    return [
        'bloqueado' => in_array(true, array_column($statuses, 'bloqueado'), true),
        'restante' => min(array_column($statuses, 'restante')),
        'tentar_novamente_em' => max(array_column($statuses, 'tentar_novamente_em'))
    ];
}

function primewayLoginRateStatus(string $email): array
{
    // Testar primeiro o limite global de IP; quando bloqueado, nunca abrir
    // um novo contador de e-mail arbitrário que possa encher o volume.
    $byIp = primewayLoginRateCounter(primewayLoginRateIpKey(), 300);
    if ($byIp['bloqueado']) return $byIp;
    return primewayLoginRateCombine(
        $byIp,
        primewayLoginRateCounter(primewayLoginRateAccountKey($email), 10)
    );
}

function primewayLoginRateFailure(string $email): array
{
    // A falha conta primeiro no IP; quando o limite global for atingido,
    // não são criados novos arquivos por e-mails inventados.
    $byIp = primewayLoginRateCounter(primewayLoginRateIpKey(), 300, 'failure');
    if ($byIp['bloqueado']) return $byIp;
    return primewayLoginRateCombine(
        $byIp,
        primewayLoginRateCounter(primewayLoginRateAccountKey($email), 10, 'failure')
    );
}

function primewayLoginRateClear(string $email): void
{
    // Só a contagem específica da conta é limpa após autenticação válida.
    // Uma conta legítima não pode apagar falhas de outros e-mails do IP.
    primewayLoginRateCounter(primewayLoginRateAccountKey($email), 10, 'clear');
}
