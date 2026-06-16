<?php
// ============================================================
//  POST /api/login.php
//  Body: { "email": "...", "senha": "..." }
//  Retorna: { ok: true, token: "...", usuario: {...} }
// ============================================================

require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') erro('Método não permitido.', 405);

$body  = bodyJson();
$email = trim($body['email'] ?? '');
$senha = $body['senha'] ?? '';

if (!$email || !$senha) erro('E-mail e senha são obrigatórios.');

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? AND situacao = "ativo" LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($senha, $user['senha_hash'])) {
    erro('E-mail ou senha inválidos.', 401);
}

// Gera token JWT com dados básicos do usuário
$token = jwtCreate([
    'usuario_id' => $user['id'],
    'nome'       => $user['nome'],
    'email'      => $user['email'],
    'perfil'     => $user['perfil'],
]);

// Remove campo sensível antes de retornar
unset($user['senha_hash']);

ok(['token' => $token, 'usuario' => $user]);
