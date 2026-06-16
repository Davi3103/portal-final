<?php
// ============================================================
//  TESTE DE CONEXÃO — acesse pelo navegador:
//  http://localhost/portal-compras/backend/teste_conexao.php
//
//  APAGUE este arquivo após confirmar que tudo funciona!
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Teste de Conexão — Portal Compras</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
  .ok  { background: #d4edda; border: 1px solid #28a745; color: #155724; padding: 16px; border-radius: 8px; margin: 10px 0; }
  .err { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 16px; border-radius: 8px; margin: 10px 0; }
  code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  h2   { color: #333; }
</style>
</head>
<body>
<h2>🔌 Teste de Conexão — Portal de Compras</h2>

<?php

// ── 1. Verifica extensão PDO MySQL ────────────────────────────
if (!extension_loaded('pdo_mysql')) {
    echo '<div class="err">❌ <strong>Extensão pdo_mysql não está ativa.</strong><br>
    Abra o arquivo <code>php.ini</code> (XAMPP → Apache → Config → php.ini),<br>
    procure por <code>;extension=pdo_mysql</code> e remova o <code>;</code> do início.<br>
    Depois reinicie o Apache no XAMPP.</div>';
} else {
    echo '<div class="ok">✅ Extensão <code>pdo_mysql</code> está ativa.</div>';
}

// ── 2. Tenta conectar ao banco ────────────────────────────────
echo '<hr><h3>Tentando conectar ao banco <code>' . DB_NAME . '</code>...</h3>';
echo '<p>Host: <code>' . DB_HOST . ':' . DB_PORT . '</code> &nbsp;|&nbsp; Usuário: <code>' . DB_USER . '</code></p>';

try {
    $pdo = getDB();

    // Pega versão do servidor
    $version = $pdo->query('SELECT VERSION() AS v')->fetchColumn();
    echo '<div class="ok">✅ <strong>Conexão bem-sucedida!</strong><br>
    Servidor: <code>' . htmlspecialchars($version) . '</code></div>';

    // ── 3. Verifica se as tabelas existem ─────────────────────
    echo '<hr><h3>Verificando tabelas do portal...</h3>';
    $tabelas = ['usuarios','unidades','destinos','categorias','produtos','solicitacoes','solicitacao_itens','solicitacao_analises'];
    $stmt    = $pdo->query("SHOW TABLES");
    $existentes = array_column($stmt->fetchAll(PDO::FETCH_NUM), 0);

    foreach ($tabelas as $t) {
        if (in_array($t, $existentes)) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo '<div class="ok">✅ Tabela <code>' . $t . '</code> existe — <strong>' . $count . ' registro(s)</strong></div>';
        } else {
            echo '<div class="err">❌ Tabela <code>' . $t . '</code> <strong>NÃO encontrada</strong>.<br>
            Execute o arquivo <code>portal_compras_schema.sql</code> no phpMyAdmin.</div>';
        }
    }

} catch (Exception $e) {
    echo '<div class="err">❌ <strong>Falha na conexão:</strong><br><code>' . htmlspecialchars($e->getMessage()) . '</code>
    <br><br><strong>Verifique:</strong><ul>
    <li>O MySQL/MariaDB está rodando no XAMPP? (deve estar verde)</li>
    <li>O banco <code>' . DB_NAME . '</code> foi criado? (execute o .sql primeiro)</li>
    <li>A senha está correta no <code>config.php</code>? (padrão XAMPP é vazia)</li>
    </ul></div>';
}
?>

<hr>
<p style="color:#999;font-size:12px">⚠️ Apague este arquivo (<code>teste_conexao.php</code>) após confirmar que tudo funciona.</p>
</body>
</html>
