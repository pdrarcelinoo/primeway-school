<?php
declare(strict_types=1);
require_once __DIR__ . '/../../calendario/_portal.php';
primewayExigirMetodo('GET');
$usuario=primewayExigirPerfis(['aluno']);
try {
    primewayResponderJson(primewayCalendarioPortal(primewayPdo(),$usuario,(string)($_GET['mes']??date('Y-m'))));
} catch (Throwable $erro) {
    error_log('PrimeWay calendário aluno: '.$erro->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível carregar o calendário.'],500);
}
