<?php
// ============================================================
//  CONFIGURAÇÃO — InfinityFree
// ============================================================

define('DB_HOST', 'sql201.infinityfree.com');
define('DB_USER', 'if0_42147856');
define('DB_PASS', 'Appa05969');
define('DB_NAME', 'if0_42147856_portal');
define('DB_PORT', 3306);

define('JWT_SECRET', 'portal-compras-jwt-2025-!@#Appa');
define('SESSION_TTL', 8 * 3600);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT
             . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'erro' => 'Falha na conexão: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
