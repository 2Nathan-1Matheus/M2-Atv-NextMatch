<?php
require 'include/funcoes.php';

$id = get_int('id');
$meu_id = usuario_id();

/* ---------------- Ações do atleta: pedir para entrar / sair ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    exigir_tipo('atleta');
    $st = $pdo->prepare('SELECT * FROM equipes WHERE id = ?');
    $st->execute([(int)post('equipe_id')]);
    $equipe = $st->fetch();
    if (!$equipe) { flash('err', 'Equipe não encontrada.'); redirecionar('equipes.php'); }

    if (post('acao') === 'solicitar') {
        $st = $pdo->prepare('SELECT status FROM equipe_atletas WHERE equipe_id = ? AND atleta_id = ?');
        $st->execute([$equipe['id'], $meu_id]);
        $atual = $st->fetchColumn();
        if ($atual === 'aprovado' || $atual === 'pendente') {
            flash('err', $atual === 'aprovado' ? 'Você já faz parte desta equipe.' : 'Sua solicitação já foi enviada.');
        } else {
            $pdo->prepare("INSERT INTO equipe_atletas (equipe_id, atleta_id, status) VALUES (?, ?, 'pendente')
                           ON DUPLICATE KEY UPDATE status = 'pendente', criado_em = NOW()")->execute([$equipe['id'], $meu_id]);
            notificar($pdo, (int)$equipe['professor_id'], 'Nova solicitação', usuario_logado()['nome'] . ' quer entrar na equipe ' . $equipe['nome'] . '.', 'dashboard-professor.php#solicitacoes');
            flash('ok', 'Solicitação enviada ao professor da equipe.');
        }
    } elseif (post('acao') === 'sair') {
        $pdo->prepare('DELETE FROM equipe_atletas WHERE equipe_id = ? AND atleta_id = ?')->execute([$equipe['id'], $meu_id]);
        flash('ok', 'Você saiu da equipe ' . $equipe['nome'] . '.');
    }
    redirecionar(url_interna(post('voltar'), 'equipes.php?id=' . $equipe['id']));
}

/* ---------------- Página de uma equipe ---------------- */
if ($id) {
    $st = $pdo->prepare('SELECT eq.*, m.nome AS modalidade, m.icone, u.nome AS professor, u.email AS professor_email
                           FROM equipes eq JOIN modalidades m ON m.id = eq.modalidade_id JOIN usuarios u ON u.id = eq.professor_id
                          WHERE eq.id = ?');
    $st->execute([$id]);
    $equipe = $st->fetch();
    if (!$equipe) { flash('err', 'Equipe não encontrada.'); redirecionar('equipes.php'); }

    $st = $pdo->prepare("SELECT u.id, u.nome, u.foto, u.posicao FROM equipe_atletas ea JOIN usuarios u ON u.id = ea.atleta_id
                          WHERE ea.equipe_id = ? AND ea.status = 'aprovado' ORDER BY u.nome");
    $st->execute([$id]);
    $elenco = $st->fetchAll();

    $st = $pdo->prepare("SELECT i.*, c.nome AS campeonato, c.status AS status_camp, c.data_inicio, ct.nome AS categoria
                           FROM inscricoes i JOIN campeonatos c ON c.id = i.campeonato_id JOIN categorias ct ON ct.id = i.categoria_id
                          WHERE i.equipe_id = ? AND i.status <> 'recusada' ORDER BY c.data_inicio DESC");
    $st->execute([$id]);
    $competicoes = $st->fetchAll();

    $st = $pdo->prepare("SELECT d.resultado, COUNT(*) AS n FROM vw_desempenho d JOIN inscricoes i ON i.id = d.inscricao_id WHERE i.equipe_id = ? GROUP BY d.resultado");
    $st->execute([$id]);
    $vde = ['V' => 0, 'E' => 0, 'D' => 0];
    foreach ($st->fetchAll() as $r) $vde[$r['resultado']] = (int)$r['n'];

    $st = $pdo->prepare("SELECT p.*, c.nome AS campeonato, IF(p.inscricao_a_id = i.id, p.inscricao_b_id, p.inscricao_a_id) AS adversario_insc,
                                IF(p.inscricao_a_id = i.id, p.placar_a, p.placar_b) AS meu, IF(p.inscricao_a_id = i.id, p.placar_b, p.placar_a) AS deles,
                                COALESCE(eq2.nome, u2.nome) AS adversario, i.id AS minha_insc
                           FROM inscricoes i
                           JOIN partidas p ON (p.inscricao_a_id = i.id OR p.inscricao_b_id = i.id)
                           JOIN campeonatos c ON c.id = p.campeonato_id
                      LEFT JOIN inscricoes i2 ON i2.id = IF(p.inscricao_a_id = i.id, p.inscricao_b_id, p.inscricao_a_id)
                      LEFT JOIN equipes eq2 ON eq2.id = i2.equipe_id LEFT JOIN usuarios u2 ON u2.id = i2.atleta_id
                          WHERE i.equipe_id = ? AND p.status = 'finalizada' AND p.inscricao_a_id IS NOT NULL AND p.inscricao_b_id IS NOT NULL
                       ORDER BY p.data_hora DESC LIMIT 8");
    $st->execute([$id]);
    $jogos = $st->fetchAll();

    $minha_relacao = null;
    if (tipo_usuario() === 'atleta') {
        $st = $pdo->prepare('SELECT status FROM equipe_atletas WHERE equipe_id = ? AND atleta_id = ?');
        $st->execute([$id, $meu_id]);
        $minha_relacao = $st->fetchColumn() ?: null;
    }

    $titulo = $equipe['nome'];
    $nav_ativa = 'equipes.php';
    require 'include/header.php';
    ?>
    <main class="section" style="padding-top:34px;">
      <div class="container">
        <a href="equipes.php" class="faint" style="font-size:13px;">← Voltar para equipes</a>

        <div class="card detail-head" style="margin-top:18px;">
          <div class="logo-box"><?= $equipe['logo'] ? '<img src="' . e($equipe['logo']) . '" alt="">' : icone_modalidade($equipe['icone']) ?></div>
          <div class="grow">
            <span class="chip gray"><?= e($equipe['modalidade']) ?></span>
            <h1><?= e($equipe['nome']) ?></h1>
            <p style="margin:0;"><?= e($equipe['cidade'] ?: '—') ?> · Treinador(a): <?= e($equipe['professor']) ?></p>
          </div>
          <div class="btn-row">
            <?php if ((int)$equipe['professor_id'] === $meu_id): ?>
              <a href="dashboard-professor.php?equipe=<?= $id ?>#elenco" class="btn btn-primary"><?= icone('edit') ?> Gerenciar equipe</a>
            <?php elseif (tipo_usuario() === 'atleta'): ?>
              <?php if ($minha_relacao === 'aprovado'): ?>
                <span class="chip green">Você faz parte</span>
                <form method="post" data-confirm="Sair da equipe <?= e($equipe['nome']) ?>?">
                  <?= csrf_campo() ?><input type="hidden" name="acao" value="sair"><input type="hidden" name="equipe_id" value="<?= $id ?>">
                  <button class="btn btn-ghost btn-sm">Sair da equipe</button>
                </form>
              <?php elseif ($minha_relacao === 'pendente'): ?>
                <span class="chip yellow">Solicitação enviada</span>
              <?php else: ?>
                <form method="post">
                  <?= csrf_campo() ?><input type="hidden" name="acao" value="solicitar"><input type="hidden" name="equipe_id" value="<?= $id ?>">
                  <button class="btn btn-primary"><?= icone('plus') ?> Pedir para entrar</button>
                </form>
              <?php endif; ?>
            <?php elseif (!usuario_logado()): ?>
              <a href="login.php?voltar=<?= urlencode('equipes.php?id=' . $id) ?>" class="btn btn-outline">Entrar para participar</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="grid grid-2" style="margin-top:22px;align-items:start;">
          <div class="panel" style="margin:0;">
            <div class="panel-head"><h3>Elenco</h3><span class="chip gray"><?= count($elenco) ?> atletas</span></div>
            <?php if (!$elenco): ?><p class="faint" style="font-size:13px;margin:0;">Nenhum atleta vinculado ainda.</p><?php endif; ?>
            <?php foreach ($elenco as $a): ?>
              <div class="list-row">
                <div class="thumb round txt"><?= $a['foto'] ? '<img src="' . e($a['foto']) . '" alt="">' : e(iniciais($a['nome'])) ?></div>
                <div class="info"><b><?= e($a['nome']) ?></b><span><?= e($a['posicao'] ?: 'Atleta') ?></span></div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="stack-gap">
            <div class="panel" style="margin:0;">
              <div class="panel-head"><h3>Desempenho</h3></div>
              <?= grafico_vde($vde['V'], $vde['E'], $vde['D']) ?>
            </div>
            <div class="panel" style="margin:0;">
              <div class="panel-head"><h3>Competições</h3></div>
              <?php if (!$competicoes): ?><p class="faint" style="font-size:13px;margin:0;">A equipe ainda não está inscrita em competições.</p><?php endif; ?>
              <?php foreach ($competicoes as $ci): ?>
                <div class="list-row">
                  <div class="thumb"><?= icone('trophy') ?></div>
                  <div class="info"><b><a href="detalhes-competicao.php?id=<?= (int)$ci['campeonato_id'] ?>"><?= e($ci['campeonato']) ?></a></b><span><?= e($ci['categoria']) ?> · <?= e(data_br($ci['data_inicio'])) ?></span></div>
                  <?= $ci['status'] === 'aprovada' ? chip_status($ci['status_camp']) : chip_inscricao($ci['status']) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="panel" style="margin:0;">
              <div class="panel-head"><h3>Últimos resultados</h3></div>
              <?php if (!$jogos): ?><p class="faint" style="font-size:13px;margin:0;">Nenhuma partida disputada ainda.</p><?php endif; ?>
              <?php foreach ($jogos as $j):
                $res = (int)$j['vencedor_id'] === (int)$j['minha_insc'] ? ['V', 'green', 'Vitória'] : ($j['vencedor_id'] === null ? ['E', 'yellow', 'Empate'] : ['D', 'red', 'Derrota']); ?>
                <div class="list-row">
                  <div class="info"><b>x <?= e($j['adversario']) ?></b><span><?= e($j['campeonato']) ?> · <?= e(data_br($j['data_hora'])) ?></span></div>
                  <span class="chip <?= $res[1] ?>"><?= $res[2] ?> <?= (int)$j['meu'] ?>x<?= (int)$j['deles'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
    <?php
    require 'include/footer.php';
    exit;
}

/* ---------------- Lista de equipes ---------------- */
$modalidade = get_int('modalidade');
$busca = trim((string)($_GET['busca'] ?? ''));
$sql = "SELECT eq.*, m.nome AS modalidade, m.icone, u.nome AS professor,
               (SELECT COUNT(*) FROM equipe_atletas ea WHERE ea.equipe_id = eq.id AND ea.status = 'aprovado') AS atletas
          FROM equipes eq JOIN modalidades m ON m.id = eq.modalidade_id JOIN usuarios u ON u.id = eq.professor_id WHERE 1 = 1";
$p = [];
if ($modalidade) { $sql .= ' AND eq.modalidade_id = ?'; $p[] = $modalidade; }
if ($busca !== '') { $sql .= ' AND (eq.nome LIKE ? OR eq.cidade LIKE ?)'; $p[] = "%$busca%"; $p[] = "%$busca%"; }
$sql .= ' ORDER BY m.id, eq.nome';
$st = $pdo->prepare($sql);
$st->execute($p);
$equipes = $st->fetchAll();

$titulo = 'Equipes';
$nav_ativa = 'equipes.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Comunidade</span><span class="line"></span></div>
    <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;align-items:flex-end;">
      <div>
        <h2>Equipes cadastradas</h2>
        <p>Conheça as equipes que competem na plataforma NextMatch.</p>
      </div>
      <?php if (tipo_usuario() === 'professor'): ?>
        <a href="dashboard-professor.php#nova-equipe" class="btn btn-primary"><?= icone('plus') ?> Cadastrar equipe</a>
      <?php endif; ?>
    </div>

    <form class="filter-bar" method="get" role="search">
      <select name="modalidade" data-autosubmit aria-label="Modalidade">
        <option value="">Todas as modalidades</option>
        <?php foreach (modalidades($pdo) as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $modalidade == $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar equipe ou cidade..." aria-label="Buscar">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </form>

    <div class="grid grid-3">
      <?php foreach ($equipes as $t): ?>
        <div class="card">
          <div class="flex gap-12" style="margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--accent);overflow:hidden;">
              <?= $t['logo'] ? '<img src="' . e($t['logo']) . '" alt="" style="width:100%;height:100%;object-fit:cover;">' : '<span style="width:22px;height:22px;display:block;">' . icone_modalidade($t['icone']) . '</span>' ?>
            </div>
            <div>
              <h4 style="margin:0;font-size:15px;"><?= e($t['nome']) ?></h4>
              <span class="faint" style="font-size:12px;"><?= e($t['cidade'] ?: '—') ?></span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;">
            <span class="muted">Modalidade: <b style="color:var(--text)"><?= e($t['modalidade']) ?></b></span>
            <span class="muted">Treinador(a): <b style="color:var(--text)"><?= e($t['professor']) ?></b></span>
            <span class="muted">Atletas: <b style="color:var(--text)"><?= (int)$t['atletas'] ?></b></span>
          </div>
          <div style="margin-top:14px;"><a href="equipes.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline btn-sm btn-block">Ver equipe</a></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$equipes): ?>
        <div class="empty-state" style="grid-column:1/-1;"><?= icone('users') ?><p>Nenhuma equipe encontrada.</p></div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
