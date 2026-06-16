<?php
// ============================================================
//  /api/solicitacao.php
//
//  GET  ?id=X   → detalhe completo (cabeçalho + itens + histórico)
//  PUT          → atualiza status  { id, acao, observacao }
//                 ações: "Em análise" | "Aprovado" | "Recusado" | "Cancelado"
// ============================================================

require_once __DIR__ . '/../helpers.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: detalhe ─────────────────────────────────────────────
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) erro('ID não informado.');

    // Cabeçalho
    $stmt = $db->prepare("
        SELECT
            s.*,
            u_solic.nome              AS solicitante_nome,
            u_solic.email             AS solicitante_email,
            un_lanc.nome              AS unid_lancamento_nome,
            un_solic.nome             AS unid_solicitante_nome,
            d.nome                    AS destino_nome,
            u_comp.nome               AS comprador_nome
        FROM solicitacoes s
        JOIN usuarios  u_solic  ON u_solic.id  = s.solicitante_id
        JOIN unidades  un_lanc  ON un_lanc.id  = s.unid_lancamento_id
        JOIN unidades  un_solic ON un_solic.id = s.unid_solicitante_id
        JOIN destinos  d        ON d.id        = s.destino_id
        LEFT JOIN usuarios u_comp ON u_comp.id = s.comprador_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $solic = $stmt->fetch();
    if (!$solic) erro('Solicitação não encontrada.', 404);

    // Itens
    $stmtI = $db->prepare("
        SELECT si.*, p.nome AS produto_nome, p.codigo AS produto_codigo,
               p.unidade_med, c.nome AS categoria_nome
        FROM solicitacao_itens si
        JOIN produtos p ON p.id = si.produto_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE si.solicitacao_id = ?
        ORDER BY si.id
    ");
    $stmtI->execute([$id]);
    $solic['itens'] = $stmtI->fetchAll();

    // Histórico de análises
    $stmtA = $db->prepare("
        SELECT sa.*, u.nome AS usuario_nome
        FROM solicitacao_analises sa
        JOIN usuarios u ON u.id = sa.usuario_id
        WHERE sa.solicitacao_id = ?
        ORDER BY sa.data_acao ASC
    ");
    $stmtA->execute([$id]);
    $solic['historico'] = $stmtA->fetchAll();

    ok($solic);
}

// ── PUT: mudar status / aprovar / recusar ─────────────────────
if ($method === 'PUT') {
    $b  = bodyJson();
    $id = (int)($b['id'] ?? 0);
    if (!$id) erro('ID não informado.');

    $acoePermitidas = ['Em análise', 'Aprovado', 'Recusado', 'Cancelado', 'Comentário'];
    $acao = $b['acao'] ?? '';
    if (!in_array($acao, $acoePermitidas)) erro("Ação inválida: $acao");

    // Verifica se solicitação existe
    $stmt = $db->prepare('SELECT id, status, solicitante_id FROM solicitacoes WHERE id = ?');
    $stmt->execute([$id]);
    $solic = $stmt->fetch();
    if (!$solic) erro('Solicitação não encontrada.', 404);

    // Regra: solicitante só pode cancelar as próprias
    if ($acao === 'Cancelado') {
        if ($user['perfil'] === 'solicitante' && $solic['solicitante_id'] != $user['usuario_id']) {
            erro('Sem permissão para cancelar esta solicitação.', 403);
        }
    }

    // Aprovado/Recusado/Em análise = comprador ou admin
    if (in_array($acao, ['Aprovado', 'Recusado', 'Em análise'])) {
        if (!in_array($user['perfil'], ['comprador', 'aprovador', 'admin'])) {
            erro('Apenas compradores e aprovadores podem executar esta ação.', 403);
        }
    }

    $db->beginTransaction();
    try {
        // Atualiza status na solicitação (exceto Comentário)
        if ($acao !== 'Comentário') {
            $db->prepare('UPDATE solicitacoes SET status = ? WHERE id = ?')
               ->execute([$acao, $id]);
        }

        // Registra no histórico
        $db->prepare("
            INSERT INTO solicitacao_analises (solicitacao_id, usuario_id, acao, observacao)
            VALUES (?, ?, ?, ?)
        ")->execute([$id, $user['usuario_id'], $acao, $b['observacao'] ?? null]);

        $db->commit();
        ok(null, "Solicitação $acao com sucesso.");

    } catch (Exception $e) {
        $db->rollBack();
        erro('Erro ao atualizar a solicitação: ' . $e->getMessage(), 500);
    }
}

erro('Método não permitido.', 405);
