<?php
// ============================================================
//  /api/unidades.php
//
//  GET  → lista unidades (usado nos autocompletes do portal)
//  POST → cria (admin)
//  PUT  → edita (admin)
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search   = $_GET['search']   ?? '';
    $situacao = $_GET['situacao'] ?? 'ativa';

    $where  = ['situacao = ?'];
    $params = [$situacao];
    if ($search) {
        $where[]  = '(nome LIKE ? OR codigo LIKE ?)';
        $like     = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    $stmt = $db->prepare(
        'SELECT * FROM unidades WHERE ' . implode(' AND ', $where) . ' ORDER BY nome'
    );
    $stmt->execute($params);
    ok($stmt->fetchAll());
}

if ($method === 'POST') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();
    if (empty($b['codigo']) || empty($b['nome'])) erro('Código e nome são obrigatórios.');
    $stmt = $db->prepare('INSERT INTO unidades (codigo, nome, situacao) VALUES (?, ?, ?)');
    $stmt->execute([$b['codigo'], $b['nome'], $b['situacao'] ?? 'ativa']);
    ok(['id' => $db->lastInsertId()], 'Unidade criada.');
}

if ($method === 'PUT') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();
    if (empty($b['id'])) erro('ID não informado.');
    $db->prepare('UPDATE unidades SET codigo=?, nome=?, situacao=? WHERE id=?')
       ->execute([$b['codigo'], $b['nome'], $b['situacao'] ?? 'ativa', $b['id']]);
    ok(null, 'Unidade atualizada.');
}

erro('Método não permitido.', 405);
