<?php

declare(strict_types=1);

function primewaySaidaSeguraConfig(bool $obrigatoria = true): ?array
{
    // Uma escola real deve definir sua própria localização privada.
    $arquivo = dirname(__DIR__, 3) . '/config/saida_segura.local.php';

    // Exclusivo do repositório HML: ponto PÚBLICO de teste, nunca uma escola real.
    if (!is_file($arquivo)) {
        $arquivo = dirname(__DIR__, 3) . '/config/saida_segura.tcc.php';
    }

    if (!is_file($arquivo)) {
        // A ausência de mapa não pode ocultar estudantes e vínculos de retirada.
        if (!$obrigatoria) return null;
        primewayResponderJson([
            'success' => false,
            'message' => 'A localização da escola ainda não foi configurada para a saída segura.'
        ], 503);
    }

    $config = require $arquivo;

    if (!is_array($config)) {
        primewayResponderJson([
            'success' => false,
            'message' => 'A configuração da saída segura é inválida.'
        ], 500);
    }

    $latitude = filter_var($config['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($config['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $raio = filter_var($config['raio_metros'] ?? null, FILTER_VALIDATE_INT);

    if (
        $latitude === false || $latitude < -90 || $latitude > 90 ||
        $longitude === false || $longitude < -180 || $longitude > 180 ||
        $raio === false || $raio < 30 || $raio > 5000
    ) {
        primewayResponderJson([
            'success' => false,
            'message' => 'A configuração da saída segura possui coordenadas ou raio inválidos.'
        ], 500);
    }

    return [
        'nome' => trim((string)($config['nome'] ?? 'Portaria principal')) ?: 'Portaria principal',
        'latitude' => (float)$latitude,
        'longitude' => (float)$longitude,
        'raio_metros' => (int)$raio,
        'demonstracao' => ($config['demonstracao'] ?? false) === true
    ];
}

function primewayDistanciaMetros(
    float $lat1,
    float $lon1,
    float $lat2,
    float $lon2
): float {
    $earthRadius = 6371000.0;

    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $deltaPhi = deg2rad($lat2 - $lat1);
    $deltaLambda = deg2rad($lon2 - $lon1);

    $a =
        sin($deltaPhi / 2) ** 2 +
        cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
