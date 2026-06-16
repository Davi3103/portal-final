<?php
// ============================================================
//  /api/categorias.php
//
//  GET  → lista todas as categorias
//  POST → cria categoria (admin)
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query('SELECT * FROM categorias ORDER BY nome');
    ok($stmt->fetchAll());
}

if ($method === 'POST') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();
    if (empty($b['nome'])) erro('Nome é obrigatório.');
    $stmt = $db->prepare('INSERT INTO categorias (nome) VALUES (?)');
    $stmt->execute([$b['nome']]);
    ok(['id' => $db->lastInsertId()], 'Categoria criada.');
}

erro('Método não permitido.', 405);
