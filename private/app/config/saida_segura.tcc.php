<?php
declare(strict_types=1);

// HOMOLOGACAO / TCC: nao e o endereco de uma escola existente.
// Ponto de referencia publico (regiao do Paco Municipal de Sao Bernardo do Campo).
// GPS e autorizacao permanecem REAIS: nao simula proximidade ou liberacao.
// Para uma escola real, crie saida_segura.local.php em vez de usar este exemplo.
return [
    'nome' => 'Ponto demonstrativo do TCC (não é escola real)',
    'latitude' => -23.69637,
    'longitude' => -46.55150,
    'raio_metros' => 300,
    'demonstracao' => true
];
