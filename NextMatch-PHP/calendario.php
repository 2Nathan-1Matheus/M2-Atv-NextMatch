<?php
require 'include/funcoes.php';

/* Mês exibido: ?mes=2026-11 (padrão: mês atual) */
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
$inicio_mes = strtotime($mes . '-01');
$fim_mes = strtotime(date('Y-m-t', $inicio_mes));
$anterior = date('Y-m', strtotime('-1 month', $inicio_mes));
$seguinte = date('Y-m', strtotime('+1 month', $inicio_mes));
$modalidade = get_int('modalidade');

/* Eventos: início de campeonatos + partidas */
$eventos = []; // 'Y-m-d' => [ [tipo, hora, texto, link], ... ]

$sql = "SELECT c.id, c.nome, c.data_inicio, c.data_fim, m.nome AS modalidade FROM campeonatos c JOIN modalidades m ON m.id = c.modalidade_id
         WHERE c.data_inicio <= ? AND COALESCE(c.data_fim, c.data_inicio) >= ?" . ($modalidade ? ' AND c.modalidade_id = ?' : '');
$st = $pdo->prepare($sql);
$st->execute(array_merge([date('Y-m-d', $fim_mes), date('Y-m-d', $inicio_mes)], $modalidade ? [$modalidade] : []));
foreach ($st->fetchAll() as $c) {
    $eventos[$c['data_inicio']][] = ['camp', '', $c['nome'] . ' (início)', 'detalhes-competicao.php?id=' . $c['id']];
    if ($c['data_fim'] && $c['data_fim'] !== $c['data_inicio']) {
        $eventos[$c['data_fim']][] = ['camp', '', $c['nome'] . ' (encerramento)', 'detalhes-competicao.php?id=' . $c['id']];
    }
}

$sql = "SELECT p.*, c.nome AS campeonato, COALESCE(ea.nome, ua.nome) AS nome_a, COALESCE(eb.nome, ub.nome) AS nome_b
          FROM partidas p JOIN campeonatos c ON c.id = p.campeonato_id
     LEFT JOIN inscricoes ia ON ia.id = p.inscricao_a_id LEFT JOIN equipes ea ON ea.id = ia.equipe_id LEFT JOIN usuarios ua ON ua.id = ia.atleta_id
     LEFT JOIN inscricoes ib ON ib.id = p.inscricao_b_id LEFT JOIN equipes eb ON eb.id = ib.equipe_id LEFT JOIN usuarios ub ON ub.id = ib.atleta_id
         WHERE p.data_hora BETWEEN ? AND ?
           AND NOT (p.status = 'finalizada' AND (p.inscricao_a_id IS NULL OR p.inscricao_b_id IS NULL))"
     . ($modalidade ? ' AND c.modalidade_id = ?' : '') . ' ORDER BY p.data_hora';
$st = $pdo->prepare($sql);
$st->execute(array_merge([date('Y-m-d 00:00:00', $inicio_mes), date('Y-m-d 23:59:59', $fim_mes)], $modalidade ? [$modalidade] : []));
foreach ($st->fetchAll() as $p) {
    $dia = substr($p['data_hora'], 0, 10);
    $txt = ($p['nome_a'] ?? 'A definir') . ' x ' . ($p['nome_b'] ?? 'A definir');
    if ($p['status'] === 'finalizada') $txt = ($p['nome_a']) . ' ' . (int)$p['placar_a'] . ' x ' . (int)$p['placar_b'] . ' ' . $p['nome_b'];
    $eventos[$dia][] = ['jogo', date('H\hi', strtotime($p['data_hora'])), $txt . ' — ' . $p['campeonato'], 'detalhes-competicao.php?id=' . $p['campeonato_id'] . '#chave'];
}
ksort($eventos);
$hoje = date('Y-m-d');

$titulo = 'Calendário esportivo';
$nav_ativa = 'competicoes.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Calendário esportivo</span><span class="line"></span></div>
    <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;align-items:flex-end;">
      <div>
        <h2>Calendário</h2>
        <p>Datas dos campeonatos e de todas as partidas agendadas na plataforma.</p>
      </div>
      <a href="competicoes.php" class="btn btn-outline"><?= icone('trophy') ?> Ver em lista</a>
    </div>

    <div class="panel">
      <div class="cal-head">
        <a class="btn btn-ghost btn-sm" href="?mes=<?= $anterior ?>&modalidade=<?= $modalidade ?>" aria-label="Mês anterior">‹ <?= NM_MESES[(int)date('n', strtotime($anterior . '-01')) - 1] ?></a>
        <h3><?= mb_convert_case(NM_MESES_LONGOS[(int)date('n', $inicio_mes) - 1], MB_CASE_TITLE) ?> de <?= date('Y', $inicio_mes) ?></h3>
        <a class="btn btn-ghost btn-sm" href="?mes=<?= $seguinte ?>&modalidade=<?= $modalidade ?>" aria-label="Próximo mês"><?= NM_MESES[(int)date('n', strtotime($seguinte . '-01')) - 1] ?> ›</a>
      </div>
      <form method="get" class="form-inline" style="margin-bottom:16px;">
        <input type="hidden" name="mes" value="<?= e($mes) ?>">
        <div class="field">
          <select name="modalidade" data-autosubmit aria-label="Filtrar por modalidade" style="padding:10px 12px;">
            <option value="0">Todas as modalidades</option>
            <?php foreach (modalidades($pdo) as $m): ?>
              <option value="<?= $m['id'] ?>" <?= $modalidade == $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($mes !== date('Y-m')): ?><a href="calendario.php" class="btn btn-ghost btn-sm">Hoje</a><?php endif; ?>
      </form>

      <!-- Grade (telas grandes) -->
      <div class="cal-grid" role="grid" aria-label="Calendário do mês">
        <?php foreach (NM_DIAS_SEMANA as $d): ?><div class="cal-dow" role="columnheader"><?= $d ?></div><?php endforeach; ?>
        <?php
        $primeiro_dow = (int)date('w', $inicio_mes);
        $dia = strtotime("-$primeiro_dow days", $inicio_mes);
        $ultimo = strtotime('+' . (6 - (int)date('w', $fim_mes)) . ' days', $fim_mes);
        while ($dia <= $ultimo):
            $chave = date('Y-m-d', $dia);
            $fora = date('Y-m', $dia) !== $mes;
        ?>
          <div class="cal-cell<?= $fora ? ' out' : '' ?><?= $chave === $hoje ? ' today' : '' ?>" role="gridcell">
            <span class="n"><?= date('j', $dia) ?></span>
            <?php if (!$fora) foreach (array_slice($eventos[$chave] ?? [], 0, 3) as [$tipo, $hora, $txt, $link]): ?>
              <a class="cal-ev <?= $tipo ?>" href="<?= e($link) ?>" title="<?= e(($hora ? $hora . ' · ' : '') . $txt) ?>"><?php if ($hora): ?><span class="t"><?= $hora ?></span><?php endif; ?><?= e($txt) ?></a>
            <?php endforeach; ?>
            <?php if (!$fora && count($eventos[$chave] ?? []) > 3): ?>
              <span class="faint" style="font-size:11px;padding:0 6px;">+<?= count($eventos[$chave]) - 3 ?> eventos</span>
            <?php endif; ?>
          </div>
        <?php $dia = strtotime('+1 day', $dia); endwhile; ?>
      </div>

      <!-- Lista (celular) -->
      <div class="cal-list">
        <?php if (!$eventos): ?>
          <div class="empty-state"><?= icone('calendar') ?><p>Nenhum evento neste mês.</p></div>
        <?php endif; ?>
        <?php foreach ($eventos as $data => $lista): $t = strtotime($data); ?>
          <div class="cal-day">
            <div class="cal-date<?= $data === $hoje ? ' today' : '' ?>"><b><?= date('d', $t) ?></b><span><?= NM_DIAS_SEMANA[(int)date('w', $t)] ?></span></div>
            <div class="cal-events">
              <?php foreach ($lista as [$tipo, $hora, $txt, $link]): ?>
                <a class="cal-ev <?= $tipo ?>" href="<?= e($link) ?>"><?php if ($hora): ?><span class="t"><?= $hora ?></span><?php endif; ?><?= e($txt) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cal-legend">
        <span><i style="background:var(--accent);"></i>Campeonatos</span>
        <span><i style="background:#64748B;"></i>Partidas</span>
      </div>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
