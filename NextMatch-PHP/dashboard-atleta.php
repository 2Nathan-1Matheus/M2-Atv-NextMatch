<?php
require 'include/funcoes.php';
exigir_tipo('atleta');
$eu = usuario_logado();
$me = usuario_id();

/* Minhas equipes (aprovado ou pendente) */
$st = $pdo->prepare("SELECT eq.*, m.nome AS modalidade, m.icone, ea.status AS vinculo, u.nome AS professor
                       FROM equipe_atletas ea JOIN equipes eq ON eq.id = ea.equipe_id JOIN modalidades m ON m.id = eq.modalidade_id
                       JOIN usuarios u ON u.id = eq.professor_id
                      WHERE ea.atleta_id = ? AND ea.status IN ('aprovado', 'pendente') ORDER BY ea.status, eq.nome");
$st->execute([$me]);
$minhas_equipes = $st->fetchAll();
$ids_equipes = array_map('intval', array_column(array_filter($minhas_equipes, fn($e) => $e['vinculo'] === 'aprovado'), 'id'));

/* Inscrições: as minhas (individuais) + as das minhas equipes */
$in_eq = $ids_equipes ? implode(',', $ids_equipes) : '0'; // só inteiros
$st = $pdo->prepare("SELECT i.*, c.nome AS campeonato, c.status AS status_camp, c.data_inicio, c.cidade, c.modalidade_id, m.icone,
                            ct.nome AS categoria, eq.nome AS equipe
                       FROM inscricoes i JOIN campeonatos c ON c.id = i.campeonato_id JOIN modalidades m ON m.id = c.modalidade_id
                       JOIN categorias ct ON ct.id = i.categoria_id LEFT JOIN equipes eq ON eq.id = i.equipe_id
                      WHERE (i.atleta_id = ? OR i.equipe_id IN ($in_eq)) AND i.status <> 'recusada'
                   ORDER BY FIELD(c.status, 'em_andamento', 'inscricoes_abertas', 'em_breve', 'encerrado'), c.data_inicio");
$st->execute([$me]);
$inscricoes = $st->fetchAll();
$ids_insc = array_map('intval', array_column(array_filter($inscricoes, fn($i) => $i['status'] === 'aprovada'), 'id'));
$in_insc = $ids_insc ? implode(',', $ids_insc) : '0';

/* Partidas */
$sql_partidas = "SELECT p.*, c.nome AS campeonato, c.formato,
        IF(p.inscricao_a_id IN ($in_insc), p.inscricao_a_id, p.inscricao_b_id) AS minha,
        IF(p.inscricao_a_id IN ($in_insc), p.inscricao_b_id, p.inscricao_a_id) AS adv,
        IF(p.inscricao_a_id IN ($in_insc), p.placar_a, p.placar_b) AS meu_placar,
        IF(p.inscricao_a_id IN ($in_insc), p.placar_b, p.placar_a) AS placar_adv,
        COALESCE(eq2.nome, u2.nome) AS adversario
      FROM partidas p JOIN campeonatos c ON c.id = p.campeonato_id
 LEFT JOIN inscricoes i2 ON i2.id = IF(p.inscricao_a_id IN ($in_insc), p.inscricao_b_id, p.inscricao_a_id)
 LEFT JOIN equipes eq2 ON eq2.id = i2.equipe_id LEFT JOIN usuarios u2 ON u2.id = i2.atleta_id
     WHERE (p.inscricao_a_id IN ($in_insc) OR p.inscricao_b_id IN ($in_insc))";
$proximas = $pdo->query($sql_partidas . " AND p.status = 'agendada' ORDER BY p.data_hora IS NULL, p.data_hora LIMIT 5")->fetchAll();
$resultados = $pdo->query($sql_partidas . " AND p.status = 'finalizada' AND p.inscricao_a_id IS NOT NULL AND p.inscricao_b_id IS NOT NULL ORDER BY p.data_hora DESC LIMIT 6")->fetchAll();

$vde = ['V' => 0, 'E' => 0, 'D' => 0];
foreach ($pdo->query("SELECT resultado, COUNT(*) AS n FROM vw_desempenho WHERE inscricao_id IN ($in_insc) GROUP BY resultado") as $r) $vde[$r['resultado']] = (int)$r['n'];

/* Melhor posição no ranking (minha ou de uma equipe minha) */
$melhor = null;
foreach (array_unique(array_column($inscricoes, 'modalidade_id')) as $mod) {
    foreach ([calcular_ranking($pdo, (int)$mod), calcular_ranking_provas($pdo, (int)$mod)] as $lista) {
        foreach ($lista as $pos => $r) {
            if ((int)$r['atleta_id'] === $me || in_array((int)$r['equipe_id'], $ids_equipes, true)) {
                if ($melhor === null || $pos + 1 < $melhor) $melhor = $pos + 1;
            }
        }
    }
}

$ativas = array_filter($inscricoes, fn($i) => $i['status_camp'] !== 'encerrado');
$proxima = $proximas[0] ?? null;

/* Equipes que posso pedir para entrar */
$st = $pdo->prepare("SELECT eq.id, eq.nome, m.nome AS modalidade FROM equipes eq JOIN modalidades m ON m.id = eq.modalidade_id
                      WHERE eq.id NOT IN (SELECT equipe_id FROM equipe_atletas WHERE atleta_id = ? AND status IN ('aprovado', 'pendente'))
                   ORDER BY m.id, eq.nome");
$st->execute([$me]);
$outras_equipes = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 4');
$st->execute([$me]);
$notificacoes = $st->fetchAll();

$titulo = 'Painel do atleta';
$nav_ativa = '';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-head">
      <div>
        <h1>Olá, <?= e(explode(' ', $eu['nome'])[0]) ?> 👋</h1>
        <p>Aqui está um resumo das suas competições e resultados.</p>
      </div>
      <a href="competicoes.php?status=inscricoes_abertas" class="btn btn-primary">Explorar competições</a>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="ico"><?= icone('trophy') ?></div><b><?= count($ativas) ?></b><span>Inscrições ativas</span></div>
      <div class="kpi"><div class="ico"><?= icone('rank') ?></div><b><?= $melhor ? $melhor . 'º' : '—' ?></b><span>Melhor posição no ranking</span></div>
      <div class="kpi"><div class="ico"><?= icone('chart') ?></div><b><?= $vde['V'] ?></b><span>Vitórias</span></div>
      <div class="kpi"><div class="ico"><?= icone('calendar') ?></div><b><?= $proxima && $proxima['data_hora'] ? e(data_br($proxima['data_hora'], false)) : '—' ?></b><span>Próxima partida</span></div>
    </div>

    <div class="split-2">
      <div class="panel">
        <div class="panel-head"><h3>Próximas partidas</h3><a href="calendario.php" class="btn btn-ghost btn-sm">Calendário</a></div>
        <?php if (!$proximas): ?><p class="faint" style="font-size:13px;margin:0;">Nenhuma partida agendada para você.</p><?php endif; ?>
        <?php foreach ($proximas as $p): ?>
          <a class="list-row" href="detalhes-competicao.php?id=<?= (int)$p['campeonato_id'] ?>#chave">
            <div class="thumb"><?= icone('calendar') ?></div>
            <div class="info"><b>x <?= e($p['adversario'] ?: 'Adversário a definir') ?></b><span><?= e($p['campeonato']) ?> · <?= e(data_hora_br($p['data_hora'])) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="panel">
        <div class="panel-head"><h3>Meu desempenho</h3><a href="ranking.php" class="btn btn-ghost btn-sm">Ver ranking</a></div>
        <?= grafico_vde($vde['V'], $vde['E'], $vde['D']) ?>
      </div>
    </div>

    <div class="panel" style="margin-top:20px;">
      <div class="panel-head"><h3>Minhas inscrições</h3><a href="competicoes.php" class="btn btn-ghost btn-sm">Ver competições</a></div>
      <?php if (!$inscricoes): ?>
        <div class="empty-state" style="padding:20px;"><?= icone('trophy') ?><p>Você ainda não tem inscrições. <a href="competicoes.php?status=inscricoes_abertas" style="color:var(--accent);">Veja as competições abertas</a>.</p></div>
      <?php endif; ?>
      <?php foreach ($inscricoes as $i): ?>
        <div class="list-row">
          <div class="thumb"><?= icone_modalidade($i['icone']) ?></div>
          <div class="info">
            <b><a href="detalhes-competicao.php?id=<?= (int)$i['campeonato_id'] ?>"><?= e($i['campeonato']) ?></a></b>
            <span><?= e($i['categoria']) ?> · <?= e(data_br($i['data_inicio'])) ?><?= $i['equipe'] ? ' · com ' . e($i['equipe']) : ' · individual' ?></span>
          </div>
          <?= $i['status'] === 'aprovada' ? chip_status($i['status_camp']) : chip_inscricao($i['status']) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Resultados recentes</h3></div>
      <?php if (!$resultados): ?><p class="faint" style="font-size:13px;margin:0;">Seus resultados aparecem aqui depois das partidas.</p><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Competição</th><th>Adversário</th><th>Resultado</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($resultados as $r):
          $res = (int)$r['vencedor_id'] === (int)$r['minha'] ? ['green', 'Vitória'] : ($r['vencedor_id'] === null ? ['yellow', 'Empate'] : ['red', 'Derrota']); ?>
          <tr>
            <td><a href="detalhes-competicao.php?id=<?= (int)$r['campeonato_id'] ?>"><?= e($r['campeonato']) ?></a></td>
            <td><?= e($r['adversario']) ?></td>
            <td><span class="chip <?= $res[0] ?>"><?= $res[1] ?> <?= (int)$r['meu_placar'] ?>x<?= (int)$r['placar_adv'] ?></span></td>
            <td><?= e(data_br($r['data_hora'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="split-2">
      <div class="panel">
        <div class="panel-head"><h3>Minhas equipes</h3></div>
        <?php if (!$minhas_equipes): ?><p class="faint" style="font-size:13px;margin:0 0 6px;">Você ainda não faz parte de nenhuma equipe.</p><?php endif; ?>
        <?php foreach ($minhas_equipes as $eq): ?>
          <div class="list-row">
            <div class="thumb"><?= $eq['logo'] ? '<img src="' . e($eq['logo']) . '" alt="">' : icone_modalidade($eq['icone']) ?></div>
            <div class="info"><b><a href="equipes.php?id=<?= (int)$eq['id'] ?>"><?= e($eq['nome']) ?></a></b><span><?= e($eq['modalidade']) ?> · <?= e($eq['professor']) ?></span></div>
            <?= chip_inscricao($eq['vinculo']) ?>
          </div>
        <?php endforeach; ?>

        <?php if ($outras_equipes): ?>
          <form method="post" action="equipes.php" class="form-inline" style="margin-top:16px;">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="solicitar"><input type="hidden" name="voltar" value="dashboard-atleta.php">
            <div class="field"><label for="equipe_id">Pedir para entrar em uma equipe</label>
              <select id="equipe_id" name="equipe_id" required>
                <option value="">Escolha a equipe</option>
                <?php $grupo = null; foreach ($outras_equipes as $o):
                  if ($grupo !== $o['modalidade']) { if ($grupo !== null) echo '</optgroup>'; $grupo = $o['modalidade']; echo '<optgroup label="' . e($grupo) . '">'; } ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['nome']) ?></option>
                <?php endforeach; if ($grupo !== null) echo '</optgroup>'; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Enviar pedido</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Notificações</h3><a href="notificacoes.php" class="btn btn-ghost btn-sm">Ver todas</a></div>
        <?php if (!$notificacoes): ?><p class="faint" style="font-size:13px;margin:0;">Nada por aqui ainda.</p><?php endif; ?>
        <?php foreach ($notificacoes as $n): ?>
          <a class="notif-row<?= $n['lida'] ? '' : ' unread' ?>" href="notificacoes.php?ler=<?= (int)$n['id'] ?>">
            <span class="dot" aria-hidden="true"></span>
            <span class="n-body"><b><?= e($n['titulo']) ?></b><p><?= e($n['mensagem']) ?></p></span>
            <span class="n-time"><?= e(tempo_relativo($n['criado_em'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
