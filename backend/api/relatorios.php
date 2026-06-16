<?php
// ============================================================
//  /api/relatorios.php
//
//  GET ?tipo=usuarios|produtos|destinos|geral
//      &periodo=YYYY-MM   (opcional)
//      &status=...        (opcional)
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user = requireAuth();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') erro('Método não permitido.', 405);

// Apenas admins, aprovadores e compradores acessam relatórios
if (!in_array($user['perfil'], ['admin', 'comprador', 'aprovador'])) {
    erro('Sem permissão para acessar relatórios.', 403);
}

$tipo    = $_GET['tipo']    ?? 'geral';
$periodo = $_GET['periodo'] ?? '';   // YYYY-MM
$status  = $_GET['status']  ?? '';

// Filtro de período reutilizável
$periodoSQL = $periodo ? "AND DATE_FORMAT(s.data_solicitacao, '%Y-%m') = ?" : '';
$periodoParam = $periodo ? [$periodo] : [];

$statusSQL   = $status ? "AND s.status = ?" : '';
$statusParam = $status ? [$status] : [];

// ── RELATÓRIO: USUÁRIOS ───────────────────────────────────────
if ($tipo === 'usuarios') {
    $stmt = $db->prepare("
        SELECT
            u.id,
            u.nome,
            u.email,
            u.perfil,
            COUNT(s.id)                          AS total_solicitacoes,
            SUM(s.status = 'Aprovado')            AS aprovadas,
            SUM(s.status IN ('Pendente','Em análise')) AS pendentes,
            SUM(s.status = 'Recusado')            AS recusadas,
            u.situacao
        FROM usuarios u
        LEFT JOIN solicitacoes s ON s.solicitante_id = u.id
            $periodoSQL $statusSQL
        GROUP BY u.id
        ORDER BY total_solicitacoes DESC
    ");
    $stmt->execute(array_merge($periodoParam, $statusParam));
    ok($stmt->fetchAll());
}

// ── RELATÓRIO: PRODUTOS ───────────────────────────────────────
if ($tipo === 'produtos') {
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.codigo,
            p.nome,
            c.nome                               AS categoria,
            COUNT(DISTINCT si.solicitacao_id)    AS total_solicitacoes,
            SUM(s.status = 'Aprovado')            AS aprovadas,
            SUM(s.status IN ('Pendente','Em análise')) AS pendentes,
            SUM(s.status = 'Recusado')            AS recusadas,
            p.situacao
        FROM produtos p
        LEFT JOIN categorias c          ON c.id = p.categoria_id
        LEFT JOIN solicitacao_itens si  ON si.produto_id = p.id
        LEFT JOIN solicitacoes s        ON s.id = si.solicitacao_id
            $periodoSQL $statusSQL
        GROUP BY p.id
        ORDER BY total_solicitacoes DESC
    ");
    $stmt->execute(array_merge($periodoParam, $statusParam));
    ok($stmt->fetchAll());
}

// ── RELATÓRIO: DESTINOS ───────────────────────────────────────
if ($tipo === 'destinos') {
    $stmt = $db->prepare("
        SELECT
            d.id,
            d.codigo,
            d.nome                               AS destino,
            COUNT(s.id)                          AS total_solicitacoes,
            SUM(s.status = 'Aprovado')            AS aprovadas,
            SUM(s.status = 'Em análise')          AS em_analise,
            SUM(s.status = 'Pendente')            AS pendentes,
            SUM(s.status = 'Recusado')            AS recusadas,
            d.situacao
        FROM destinos d
        LEFT JOIN solicitacoes s ON s.destino_id = d.id
            $periodoSQL $statusSQL
        GROUP BY d.id
        ORDER BY total_solicitacoes DESC
    ");
    $stmt->execute(array_merge($periodoParam, $statusParam));
    ok($stmt->fetchAll());
}

// ── RELATÓRIO: GERAL (dashboard) ─────────────────────────────
if ($tipo === 'geral') {
    // Totais por status
    $stmt = $db->prepare("
        SELECT status, COUNT(*) AS total
        FROM solicitacoes
        WHERE 1=1 $periodoSQL
        GROUP BY status
    ");
    $stmt->execute($periodoParam);
    $porStatus = $stmt->fetchAll();

    // Totais por prioridade
    $stmt2 = $db->prepare("
        SELECT prioridade, COUNT(*) AS total
        FROM solicitacoes
        WHERE 1=1 $periodoSQL
        GROUP BY prioridade
    ");
    $stmt2->execute($periodoParam);
    $porPrioridade = $stmt2->fetchAll();

    // Últimas 10 solicitações
    $stmt3 = $db->prepare("
        SELECT s.numero, s.data_solicitacao, s.status, s.prioridade,
               u.nome AS solicitante, d.nome AS destino
        FROM solicitacoes s
        JOIN usuarios u ON u.id = s.solicitante_id
        JOIN destinos  d ON d.id = s.destino_id
        WHERE 1=1 $periodoSQL
        ORDER BY s.criado_em DESC
        LIMIT 10
    ");
    $stmt3->execute($periodoParam);
    $recentes = $stmt3->fetchAll();

    ok([
        'por_status'     => $porStatus,
        'por_prioridade' => $porPrioridade,
        'recentes'       => $recentes,
    ]);
}

erro("Tipo de relatório desconhecido: $tipo");
