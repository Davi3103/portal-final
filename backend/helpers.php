<?php
// ============================================================
//  HELPERS CENTRAIS
//  Inclua este arquivo em todos os endpoints da API
// ============================================================

require_once __DIR__ . '/config.php';

// ── CORS ─────────────────────────────────────────────────────
// Permite que o HTML (mesmo em outro domínio/porta) acesse a API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');          // em produção: troque * pelo domínio real
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── RESPOSTAS PADRÃO ─────────────────────────────────────────
function ok(mixed $data = null, string $msg = ''): void {
    echo json_encode(['ok' => true, 'data' => $data, 'msg' => $msg]);
    exit;
}

function erro(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'erro' => $msg]);
    exit;
}

// ── BODY JSON ────────────────────────────────────────────────
function bodyJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── JWT SIMPLES (sem biblioteca externa) ─────────────────────
function jwtCreate(array $payload): string {
    $header  = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + SESSION_TTL;
    $body    = base64url(json_encode($payload));
    $sig     = base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwtVerify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    if (!$payload || $payload['exp'] < time()) return null;
    return $payload;
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ── AUTENTICAÇÃO VIA BEARER TOKEN ────────────────────────────
// Chame requireAuth() nos endpoints que exigem login
function requireAuth(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) {
        erro('Token de autenticação não fornecido.', 401);
    }
    $token   = substr($auth, 7);
    $payload = jwtVerify($token);
    if (!$payload) {
        erro('Token inválido ou expirado. Faça login novamente.', 401);
    }
    return $payload;   // retorna ['usuario_id', 'nome', 'email', 'perfil', 'exp']
}
