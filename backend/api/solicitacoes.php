<?php
// ============================================================
//  /api/solicitacoes.php
//
//  GET    → lista solicitações (com filtros opcionais)
//  POST   → cria nova solicitação
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user = requireAuth();
$db   = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: listar ───────────────────────────────────────────────
if ($method === 'GET') {

    // Filtros vindos da URL (usados em Minhas Solicitações e Relatórios)
    $status  = $_GET['status']  ?? '';
    $prio    = $_GET['prio']    ?? '';
    $periodo = $_GET['periodo'] ?? '';  // ex: "2024-01"  (YYYY-MM)
    $search  = $_GET['search']  ?? '';

    // Solicitante comum só vê as próprias; comprador/admin vê todas
    $somenteMinhas = $user['perfil'] === 'solicitante';

    $where  = ['1=1'];
    $params = [];

    if ($somenteMinhas) {
        $where[]  = 's.solicitante_id = ?';
        $params[] = $user['usuario_id'];
    }
    if ($status) {
        $where[]  = 's.status = ?';
        $params[] = $status;
    }
    if ($prio) {
        $where[]  = 's.prioridade = ?';
        $params[] = $prio;
    }
    if ($periodo) {
        $where[]  = "DATE_FORMAT(s.data_solicitacao, '%Y-%m') = ?";
        $params[] = $periodo;
    }
    if ($search) {
        $where[]  = '(s.numero LIKE ? OR u_solic.nome LIKE ? OR d.nome LIKE ?)';
        $like     = "%$search%";
        $params   = array_merge($params, [$like, $like, $like]);
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "
        SELECT
            s.id,
            s.numero,
            s.data_solicitacao   AS data,
            s.status,
            s.prioridade,
            u_solic.nome         AS solicitante,
            un_solic.nome        AS unidade,
            d.nome               AS destino,
            u_comp.nome          AS comprador,
            COUNT(si.id)         AS qtd_itens
        FROM solicitacoes s
        JOIN usuarios  u_solic  ON u_solic.id  = s.solicitante_id
        JOIN unidades  un_solic ON un_solic.id = s.unid_solicitante_id
        JOIN destinos  d        ON d.id        = s.destino_id
        LEFT JOIN usuarios u_comp ON u_comp.id = s.comprador_id
        LEFT JOIN solicitacao_itens si ON si.solicitacao_id = s.id
        WHERE $whereSQL
        GROUP BY s.id
        ORDER BY s.criado_em DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    ok($stmt->fetchAll());
}

// ── POST: criar nova solicitação ──────────────────────────────
if ($method === 'POST') {

    $b = bodyJson();

    // Campos obrigatórios
    $required = ['data_solicitacao', 'unid_lancamento_id', 'unid_solicitante_id',
                 'destino_id', 'prioridade', 'itens'];
    foreach ($required as $campo) {
        if (empty($b[$campo])) erro("Campo obrigatório ausente: $campo");
    }

    $itens = $b['itens'];
    if (!is_array($itens) || count($itens) === 0) {
        erro('A solicitação deve ter ao menos um item.');
    }

    // Valida itens
    foreach ($itens as $i => $item) {
        if (empty($item['produto_id']))  erro("Item $i: produto_id ausente.");
        if (empty($item['quantidade'])) erro("Item $i: quantidade ausente.");
    }

    $db->beginTransaction();
    try {
        // Insere cabeçalho
        $stmt = $db->prepare("
            INSERT INTO solicitacoes
              (data_solicitacao, data_lancamento,
               solicitante_id, unid_lancamento_id, unid_solicitante_id,
               destino_id, comprador_id,
               prioridade, tipo_justificativa, justificativa, observacoes, nome_referencia,
               status)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')
        ");
        $stmt->execute([
            $b['data_solicitacao'],
            $b['data_lancamento']      ?? null,
            $user['usuario_id'],
            $b['unid_lancamento_id'],
            $b['unid_solicitante_id'],
            $b['destino_id'],
            $b['comprador_id']         ?? null,
            $b['prioridade'],
            $b['tipo_justificativa']   ?? null,
            $b['justificativa']        ?? null,
            $b['observacoes']          ?? null,
            $b['nome_referencia']      ?? null,
        ]);
        $solicitacaoId = $db->lastInsertId();

        // Insere itens
        $stmtItem = $db->prepare("
            INSERT INTO solicitacao_itens
              (solicitacao_id, produto_id, quantidade, preco_unitario, observacao_item)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($itens as $item) {
            $stmtItem->execute([
                $solicitacaoId,
                $item['produto_id'],
                $item['quantidade'],
                $item['preco_unitario']  ?? null,
                $item['observacao_item'] ?? null,
            ]);
        }

        // Registra no histórico
        $db->prepare("
            INSERT INTO solicitacao_analises (solicitacao_id, usuario_id, acao, observacao)
            VALUES (?, ?, 'Recebido', 'Solicitação criada pelo solicitante.')
        ")->execute([$solicitacaoId, $user['usuario_id']]);

        $db->commit();

        // Retorna a solicitação criada com número gerado
        $nova = $db->prepare('SELECT id, numero, status FROM solicitacoes WHERE id = ?');
        $nova->execute([$solicitacaoId]);
        ok($nova->fetch(), 'Solicitação criada com sucesso.');

    } catch (Exception $e) {
        $db->rollBack();
        erro('Erro ao salvar a solicitação: ' . $e->getMessage(), 500);
    }
}

erro('Método não permitido.', 405);
