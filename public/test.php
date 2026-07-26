<?php
// Teste simples para verificar se o servidor está funcionando
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Servidor PHP funcionando',
    'time' => date('Y-m-d H:i:s')
]);
