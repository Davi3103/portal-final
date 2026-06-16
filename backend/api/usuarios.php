<?php
// ============================================================
//  /api/usuarios.php
//
//  GET    → lista usuários (admin/comprador)
//  POST   → cria usuário  (admin)
//  PUT    → edita usuário (admin ou próprio perfil)
//  DELETE → inativa       (admin)
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!in_array($user['perfil'], ['admin', 'comprador', 'aprovador'])) {
        erro('Sem permissão.', 403);
    }

    $search   = $_GET['search']   ?? '';
    $perfil   = $_GET['perfil']   ?? '';
    $situacao = $_GET['situacao'] ?? '';

    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[]  = '(nome LIKE ? OR email LIKE ?)';
        $like     = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
    if ($perfil)   { $where[] = 'perfil = ?';   $params[] = $perfil; }
    if ($situacao) { $where[] = 'situacao = ?';  $params[] = $situacao; }

    $stmt = $db->prepare(
        'SELECT id, nome, email, perfil, situacao, criado_em
         FROM usuarios WHERE ' . implode(' AND ', $where) . ' ORDER BY nome'
    );
    $stmt->execute($params);
    ok($stmt->fetchAll());
}

// ── POST: criar ───────────────────────────────────────────────
if ($method === 'POST') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $b = bodyJson();

    foreach (['nome', 'email', 'senha', 'perfil'] as $campo) {
        if (empty($b[$campo])) erro("Campo obrigatório: $campo");
    }
    if (!filter_var($b['email'], FILTER_VALIDATE_EMAIL)) erro('E-mail inválido.');
    if (strlen($b['senha']) < 6) erro('A senha deve ter no mínimo 6 caracteres.');

    // Verifica e-mail duplicado
    $check = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $check->execute([$b['email']]);
    if ($check->fetch()) erro('Este e-mail já está cadastrado.');

    $hash = password_hash($b['senha'], PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT INTO usuarios (nome, email, senha_hash, perfil, situacao)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $b['nome'],
        $b['email'],
        $hash,
        $b['perfil'],
        $b['situacao'] ?? 'ativo',
    ]);
    ok(['id' => $db->lastInsertId()], 'Usuário criado.');
}

// ── PUT: editar ───────────────────────────────────────────────
if ($method === 'PUT') {
    $b  = bodyJson();
    $id = (int)($b['id'] ?? 0);
    if (!$id) erro('ID não informado.');

    // Admin pode editar qualquer um; usuário comum só edita a si mesmo
    if ($user['perfil'] !== 'admin' && $user['usuario_id'] != $id) {
        erro('Sem permissão.', 403);
    }

    // Atualiza senha somente se informada
    if (!empty($b['senha'])) {
        if (strlen($b['senha']) < 6) erro('A senha deve ter no mínimo 6 caracteres.');
        $db->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
           ->execute([password_hash($b['senha'], PASSWORD_BCRYPT), $id]);
    }

    // Campos editáveis
    $stmt = $db->prepare("
        UPDATE usuarios SET nome=?, email=?, perfil=?, situacao=? WHERE id=?
    ");
    $stmt->execute([
        $b['nome'],
        $b['email'],
        $b['perfil']   ?? 'solicitante',
        $b['situacao'] ?? 'ativo',
        $id,
    ]);
    ok(null, 'Usuário atualizado.');
}

// ── DELETE: inativar ──────────────────────────────────────────
if ($method === 'DELETE') {
    if ($user['perfil'] !== 'admin') erro('Sem permissão.', 403);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) erro('ID não informado.');
    $db->prepare("UPDATE usuarios SET situacao='inativo' WHERE id=?")->execute([$id]);
    ok(null, 'Usuário inativado.');
}

erro('Método não permitido.', 405);
