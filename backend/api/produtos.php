<?php
// ============================================================
//  /api/produtos.php
//
//  GET  → lista produtos (com filtro por categoria e busca)
//  POST → cria produto   (admin)
//  PUT  → edita produto  (admin)
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $search    = $_GET['search']      ?? '';
    $categoria = (int)($_GET['categoria'] ?? 0);
    $situacao  = $_GET['situacao']    ?? 'ativo';

    $where  = ['p.situacao = ?'];
    $params = [$situacao];

    if ($search) {
        $where[]  = '(p.nome LIKE ? OR p.codigo LIKE ?)';
        $like     = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
    if ($categoria) {
        $where[]  = 'p.categoria_id = ?';
        $params[] = $categoria;
    }

    $sql = "
        SELECT p.*, c.nome AS categoria_nome
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.nome, p.nome
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    ok($stmt->fetchAll());
}

// ── POST: criar ───────────────────────────────────────────────
if ($method === 'POST') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();
    if (empty($b['nome']))   erro('Nome é obrigatório.');
    if (empty($b['codigo'])) erro('Código é obrigatório.');

    $stmt = $db->prepare("
        INSERT INTO produtos (codigo, nome, categoria_id, unidade_med, preco_ref, situacao)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $b['codigo'],
        $b['nome'],
        $b['categoria_id']  ?? null,
        $b['unidade_med']   ?? 'UN',
        $b['preco_ref']     ?? null,
        $b['situacao']      ?? 'ativo',
    ]);
    ok(['id' => $db->lastInsertId()], 'Produto criado.');
}

// ── PUT: editar ───────────────────────────────────────────────
if ($method === 'PUT') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();
    if (empty($b['id'])) erro('ID não informado.');

    $stmt = $db->prepare("
        UPDATE produtos
        SET codigo = ?, nome = ?, categoria_id = ?, unidade_med = ?,
            preco_ref = ?, situacao = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $b['codigo'],
        $b['nome'],
        $b['categoria_id']  ?? null,
        $b['unidade_med']   ?? 'UN',
        $b['preco_ref']     ?? null,
        $b['situacao']      ?? 'ativo',
        $b['id'],
    ]);
    ok(null, 'Produto atualizado.');
}

erro('Método não permitido.', 405);
