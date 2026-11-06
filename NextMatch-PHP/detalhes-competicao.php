<?php
require 'include/funcoes.php';
require 'include/chaveamento.php';

$id = get_int('id');
$st = $pdo->prepare('SELECT c.*, m.nome AS modalidade, m.icone, u.nome AS organizador
                       FROM campeonatos c JOIN modalidades m ON m.id = c.modalidade_id JOIN usuarios u ON u.id = c.organizador_id
                      WHERE c.id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    flash('err', 'Competição não encontrada.');
    redirecionar('competicoes.php');
}

$eu = usuario_logado();
$meu_id = usuario_id();
$e_equipe = $c['tipo_participante'] === 'equipe';

$st = $pdo->prepare('SELECT * FROM categorias WHERE campeonato_id = ? ORDER BY id');
$st->execute([$id]);
$categorias = $st->fetchAll();
$ids_categorias = array_column($categorias, 'id');

/* ---------------- Ações (inscrever / cancelar) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    exigir_login();
    $acao = post('acao');

    if ($acao === 'inscrever') {
        $categoria_id = (int)post('categoria_id');
        $aprovadas = (int)$pdo->query("SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = $id AND status = 'aprovada'")->fetchColumn();
        $erro = null;
        if ($c['status'] !== 'inscricoes_abertas') $erro = 'As inscrições deste campeonato não estão abertas.';
        elseif (!in_array($categoria_id, array_map('intval', $ids_categorias), true)) $erro = 'Escolha uma categoria válida.';
        elseif ($aprovadas >= (int)$c['vagas']) $erro = 'As vagas deste campeonato já foram preenchidas.';

        if (!$erro && !$e_equipe) {
            if (tipo_usuario() !== 'atleta') $erro = 'A inscrição neste campeonato é individual, feita pelo próprio atleta.';
            else { $equipe_id = null; $atleta_id = $meu_id; $nome_inscrito = $eu['nome']; }
        } elseif (!$erro) {
            if (tipo_usuario() !== 'professor') $erro = 'A inscrição de equipes é feita pelo professor/treinador responsável.';
            else {
                $st = $pdo->prepare('SELECT * FROM equipes WHERE id = ? AND professor_id = ?');
                $st->execute([(int)post('equipe_id'), $meu_id]);
                $equipe = $st->fetch();
                if (!$equipe) $erro = 'Escolha uma das suas equipes.';
                elseif ((int)$equipe['modalidade_id'] !== (int)$c['modalidade_id']) $erro = 'A equipe precisa ser da modalidade ' . $c['modalidade'] . '.';
                else { $equipe_id = (int)$equipe['id']; $atleta_id = null; $nome_inscrito = $equipe['nome']; }
            }
        }

        if (!$erro) {
            try {
                $st = $pdo->prepare('INSERT INTO inscricoes (campeonato_id, categoria_id, equipe_id, atleta_id, inscrito_por) VALUES (?, ?, ?, ?, ?)');
                $st->execute([$id, $categoria_id, $equipe_id, $atleta_id, $meu_id]);
                notificar($pdo, (int)$c['organizador_id'], 'Nova inscrição', $nome_inscrito . ' pediu inscrição em ' . $c['nome'] . '.', 'gerenciar-campeonato.php?id=' . $id . '#inscricoes');
                flash('ok', 'Inscrição enviada! Você será avisado quando o organizador responder.');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;
                flash('err', 'Essa inscrição já existe nesta categoria.');
            }
        } else {
            flash('err', $erro);
        }
        redirecionar('detalhes-competicao.php?id=' . $id);
    }

    if ($acao === 'cancelar') {
        $st = $pdo->prepare("DELETE i FROM inscricoes i LEFT JOIN equipes eq ON eq.id = i.equipe_id
                              WHERE i.id = ? AND i.campeonato_id = ? AND i.status = 'pendente' AND (i.atleta_id = ? OR eq.professor_id = ?)");
        $st->execute([(int)post('inscricao_id'), $id, $meu_id, $meu_id]);
        flash($st->rowCount() ? 'ok' : 'err', $st->rowCount() ? 'Inscrição cancelada.' : 'Não foi possível cancelar essa inscrição.');
        redirecionar('detalhes-competicao.php?id=' . $id);
    }
}

/* ---------------- Dados para exibição ---------------- */
$participantes = participantes_do_campeonato($pdo, $id);
$aprovados = array_filter($participantes, fn($p) => $p['status'] === 'aprovada');

$st = $pdo->prepare('SELECT * FROM partidas WHERE campeonato_id = ? ORDER BY categoria_id, rodada, posicao');
$st->execute([$id]);
$partidas = $st->fetchAll();

$st = $pdo->prepare('SELECT r.*, i.categoria_id FROM resultados r JOIN inscricoes i ON i.id = r.inscricao_id WHERE i.campeonato_id = ? ORDER BY r.colocacao');
$st->execute([$id]);
$resultados = $st->fetchAll();

// Minhas inscrições neste campeonato (diretas ou das minhas equipes)
$minhas = [];
$minhas_equipes = [];
if ($eu) {
    foreach ($participantes as $p) {
        if ((int)$p['atleta_id'] === $meu_id || (int)$p['inscrito_por'] === $meu_id) $minhas[] = $p;
    }
    if (tipo_usuario() === 'atleta' && $e_equipe) {
        $st = $pdo->prepare("SELECT i.*, eq.nome FROM inscricoes i JOIN equipes eq ON eq.id = i.equipe_id
                               JOIN equipe_atletas ea ON ea.equipe_id = eq.id AND ea.atleta_id = ? AND ea.status = 'aprovado'
                              WHERE i.campeonato_id = ?");
        $st->execute([$meu_id, $id]);
        $minhas = array_merge($minhas, $st->fetchAll());
    }
    if (tipo_usuario() === 'professor') {
        $st = $pdo->prepare('SELECT * FROM equipes WHERE professor_id = ? AND modalidade_id = ? ORDER BY nome');
        $st->execute([$meu_id, $c['modalidade_id']]);
        $minhas_equipes = $st->fetchAll();
    }
}

// Pódio / campeão
function podio(array $c, array $categoria, array $partidas, array $participantes, array $resultados): array {
    $cat = (int)$categoria['id'];
    if ($c['formato'] === 'classificacao') {
        $r = array_values(array_filter($resultados, fn($x) => (int)$x['categoria_id'] === $cat));
        return array_map(fn($x) => $participantes[$x['inscricao_id']]['nome'] ?? '—', array_slice($r, 0, 3));
    }
    $jogos = array_filter($partidas, fn($p) => (int)$p['categoria_id'] === $cat);
    if (!$jogos) return [];
    if ($c['formato'] === 'mata_mata') {
        $final = array_reduce($jogos, fn($a, $p) => (!$a || $p['rodada'] > $a['rodada']) ? $p : $a);
        if ($final['status'] !== 'finalizada' || !$final['vencedor_id']) return [];
        $vice = (int)$final['vencedor_id'] === (int)$final['inscricao_a_id'] ? $final['inscricao_b_id'] : $final['inscricao_a_id'];
        return [$participantes[$final['vencedor_id']]['nome'] ?? '—', $participantes[$vice]['nome'] ?? '—'];
    }
    foreach ($jogos as $j) if ($j['status'] !== 'finalizada') return [];
    $tab = classificacao_pontos_corridos($partidas, $participantes, $cat);
    return array_slice(array_column($tab, 'nome'), 0, 3);
}

$aba_chave = ['mata_mata' => 'Chaveamento', 'pontos_corridos' => 'Tabela e jogos', 'classificacao' => 'Resultados'][$c['formato']];
$sou_dono = $meu_id && (int)$c['organizador_id'] === $meu_id;

$titulo = $c['nome'];
$descricao = $c['modalidade'] . ' · ' . data_br($c['data_inicio']) . ' · ' . $c['cidade'];
$nav_ativa = 'competicoes.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <a href="competicoes.php" class="faint" style="font-size:13px;">← Voltar para competições</a>

    <div class="card detail-head" style="margin-top:18px;">
      <div class="logo-box"><?= icone_modalidade($c['icone']) ?></div>
      <div class="grow">
        <div class="btn-row"><?= chip_status($c['status']) ?><span class="chip gray"><?= e(NM_FORMATOS[$c['formato']]) ?></span></div>
        <h1><?= e($c['nome']) ?></h1>
        <p style="margin:0;"><?= e($c['modalidade']) ?> · <?= e(implode(' · ', array_column($categorias, 'nome'))) ?> · Organização: <?= e($c['organizador']) ?></p>
      </div>
      <?php if ($sou_dono): ?>
        <a href="gerenciar-campeonato.php?id=<?= $id ?>" class="btn btn-primary"><?= icone('edit') ?> Gerenciar campeonato</a>
      <?php endif; ?>
    </div>

    <?php if ($c['imagem']): ?>
      <div class="detail-cover"><img src="<?= e($c['imagem']) ?>" alt=""></div>
    <?php endif; ?>

    <div class="grid grid-4" style="margin-top:18px;">
      <div class="card"><span class="faint" style="font-size:12px;">Data</span><h4 style="margin:6px 0 0;font-size:15px;"><?= e(periodo_br($c['data_inicio'], $c['data_fim'])) ?></h4></div>
      <div class="card"><span class="faint" style="font-size:12px;">Local</span><h4 style="margin:6px 0 0;font-size:15px;"><?= e($c['local']) ?><?= $c['cidade'] ? ', ' . e($c['cidade']) : '' ?></h4></div>
      <div class="card"><span class="faint" style="font-size:12px;"><?= $e_equipe ? 'Equipes inscritas' : 'Atletas inscritos' ?></span><h4 style="margin:6px 0 0;font-size:15px;"><?= count($aprovados) ?> de <?= (int)$c['vagas'] ?> vagas</h4></div>
      <div class="card"><span class="faint" style="font-size:12px;">Premiação</span><h4 style="margin:6px 0 0;font-size:15px;"><?= e($c['premiacao'] ?: 'A definir') ?></h4></div>
    </div>

    <!-- Inscrição -->
    <div class="panel" style="margin-top:22px;" id="inscricao">
      <div class="panel-head"><h3>Inscrição</h3><?= $e_equipe ? '<span class="chip gray">Por equipe</span>' : '<span class="chip gray">Individual</span>' ?></div>

      <?php foreach ($minhas as $m): ?>
        <div class="list-row">
          <div class="thumb"><?= icone($e_equipe ? 'users' : 'user') ?></div>
          <div class="info"><b><?= e($m['nome']) ?></b><span><?= e($m['categoria'] ?? '') ?> · inscrição de <?= e(data_br($m['criado_em'])) ?></span></div>
          <?= chip_inscricao($m['status']) ?>
          <?php if ($m['status'] === 'pendente' && ((int)($m['atleta_id'] ?? 0) === $meu_id || (int)$m['inscrito_por'] === $meu_id)): ?>
            <form method="post" data-confirm="Cancelar esta inscrição?">
              <?= csrf_campo() ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="inscricao_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-ghost btn-sm">Cancelar</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($c['status'] !== 'inscricoes_abertas'): ?>
        <p class="note" style="margin:<?= $minhas ? '12px' : '0' ?> 0 0;">
          <?= $c['status'] === 'em_breve' ? 'As inscrições ainda não foram abertas. Acompanhe pelo calendário.' : 'As inscrições estão encerradas.' ?>
        </p>
      <?php elseif (count($aprovados) >= (int)$c['vagas']): ?>
        <p class="note" style="margin:0;">Todas as vagas foram preenchidas.</p>
      <?php elseif (!$eu): ?>
        <p style="margin:0 0 12px;"><?= $e_equipe ? 'Professores inscrevem suas equipes por aqui.' : 'Atletas fazem a própria inscrição por aqui.' ?></p>
        <a href="login.php?voltar=<?= urlencode('detalhes-competicao.php?id=' . $id) ?>" class="btn btn-primary">Entrar para se inscrever</a>
      <?php elseif ($e_equipe && tipo_usuario() === 'professor'): ?>
        <?php if (!$minhas_equipes): ?>
          <p class="note" style="margin:0;">Você ainda não tem equipe de <?= e($c['modalidade']) ?>. <a href="dashboard-professor.php#nova-equipe" style="color:var(--accent);">Cadastre uma no seu painel</a>.</p>
        <?php else: ?>
          <form method="post" class="form-inline" style="margin-top:<?= $minhas ? '14px' : '0' ?>;">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="inscrever">
            <div class="field"><label for="equipe_id">Equipe</label>
              <select id="equipe_id" name="equipe_id" required>
                <?php foreach ($minhas_equipes as $eq): ?><option value="<?= $eq['id'] ?>"><?= e($eq['nome']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label for="categoria_id">Categoria</label>
              <select id="categoria_id" name="categoria_id" required>
                <?php foreach ($categorias as $ct): ?><option value="<?= $ct['id'] ?>"><?= e($ct['nome']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Inscrever equipe</button>
          </form>
        <?php endif; ?>
      <?php elseif (!$e_equipe && tipo_usuario() === 'atleta'): ?>
        <form method="post" class="form-inline" style="margin-top:<?= $minhas ? '14px' : '0' ?>;">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="inscrever">
          <div class="field"><label for="categoria_id">Categoria</label>
            <select id="categoria_id" name="categoria_id" required>
              <?php foreach ($categorias as $ct): ?><option value="<?= $ct['id'] ?>"><?= e($ct['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Quero me inscrever</button>
        </form>
      <?php elseif (!$minhas): ?>
        <p class="note" style="margin:0;">
          <?= $e_equipe ? 'A inscrição de equipes é feita pelo professor/treinador responsável.' : 'Este campeonato é individual: cada atleta faz a própria inscrição.' ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="tabs" data-tabs role="tablist">
        <button type="button" class="active" data-tab="regulamento" role="tab">Regulamento</button>
        <button type="button" data-tab="participantes" role="tab">Participantes (<?= count($aprovados) ?>)</button>
        <button type="button" data-tab="chave" role="tab"><?= $aba_chave ?></button>
        <button type="button" data-tab="premiacao" role="tab">Premiação</button>
      </div>

      <div class="tab-pane" data-pane="regulamento">
        <div class="prose"><p style="font-size:15px;"><?= nl2br(e($c['regulamento'] ?: 'Regulamento ainda não publicado.')) ?></p></div>
      </div>

      <div class="tab-pane" data-pane="participantes" hidden>
        <?php foreach ($categorias as $ct):
          $lista = array_filter($aprovados, fn($p) => (int)$p['categoria_id'] === (int)$ct['id']); ?>
          <div class="cat-block">
            <?php if (count($categorias) > 1): ?><h4 class="mini-head"><?= e($ct['nome']) ?> · <?= count($lista) ?></h4><?php endif; ?>
            <?php if (!$lista): ?><p class="faint" style="font-size:13px;margin:0;">Nenhum participante confirmado ainda.</p><?php endif; ?>
            <?php foreach ($lista as $p): ?>
              <div class="list-row">
                <div class="thumb<?= $p['equipe_id'] ? '' : ' round' ?>"><?= $p['imagem'] ? '<img src="' . e($p['imagem']) . '" alt="">' : icone($p['equipe_id'] ? 'users' : 'user') ?></div>
                <div class="info">
                  <b><?= $p['equipe_id'] ? '<a href="equipes.php?id=' . (int)$p['equipe_id'] . '">' . e($p['nome']) . '</a>' : e($p['nome']) ?></b>
                  <span><?= e($p['cidade'] ?: '—') ?><?= $p['equipe_id'] ? ' · ' . (int)$p['atletas'] . ' atletas' : '' ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="tab-pane" data-pane="chave" hidden>
        <?php foreach ($categorias as $ct):
          $jogos = array_values(array_filter($partidas, fn($p) => (int)$p['categoria_id'] === (int)$ct['id']));
          $total_rodadas = $jogos ? max(array_column($jogos, 'rodada')) : 0; ?>
          <div class="cat-block">
            <?php if (count($categorias) > 1): ?><h4 class="mini-head"><?= e($ct['nome']) ?></h4><?php endif; ?>

            <?php if ($c['formato'] === 'classificacao'):
              $res = array_values(array_filter($resultados, fn($r) => (int)$r['categoria_id'] === (int)$ct['id'])); ?>
              <?php if (!$res): ?>
                <p class="faint" style="font-size:13px;margin:0;">Os resultados serão publicados após a prova.</p>
              <?php else: ?>
                <div class="table-wrap"><table>
                  <thead><tr><th>Pos.</th><th>Atleta</th><th>Marca</th></tr></thead>
                  <tbody>
                  <?php foreach ($res as $r): ?>
                    <tr><td><span class="rank-pos <?= $r['colocacao'] <= 3 ? 'p' . $r['colocacao'] : '' ?>"><?= (int)$r['colocacao'] ?>º</span></td>
                        <td><?= e($participantes[$r['inscricao_id']]['nome'] ?? '—') ?></td><td><?= e($r['marca'] ?: '—') ?></td></tr>
                  <?php endforeach; ?>
                  </tbody>
                </table></div>
              <?php endif; ?>

            <?php elseif (!$jogos): ?>
              <p class="faint" style="font-size:13px;margin:0;">
                <?= $c['formato'] === 'mata_mata' ? 'O chaveamento será sorteado quando as inscrições forem encerradas.' : 'A tabela de jogos será gerada quando as inscrições forem encerradas.' ?>
              </p>

            <?php elseif ($c['formato'] === 'mata_mata'): ?>
              <?= html_chave($jogos, $participantes) ?>

            <?php else:
              $tabela = classificacao_pontos_corridos($partidas, $participantes, (int)$ct['id']); ?>
              <div class="table-wrap" style="margin-bottom:18px;"><table>
                <thead><tr><th>Pos.</th><th>Equipe</th><th>J</th><th>V</th><th>E</th><th>D</th><th>Saldo</th><th>Pts</th></tr></thead>
                <tbody>
                <?php $pos = 0; foreach ($tabela as $linha): $pos++; ?>
                  <tr><td><span class="rank-pos <?= $pos <= 3 ? 'p' . $pos : '' ?>"><?= $pos ?>º</span></td>
                      <td><div class="team-cell"><span class="dot"><?= e(iniciais($linha['nome'])) ?></span><?= e($linha['nome']) ?></div></td>
                      <td><?= $linha['jogos'] ?></td><td><?= $linha['vitorias'] ?></td><td><?= $linha['empates'] ?></td><td><?= $linha['derrotas'] ?></td>
                      <td><?= $linha['pro'] - $linha['contra'] ?></td><td><b><?= $linha['pontos'] ?></b></td></tr>
                <?php endforeach; ?>
                </tbody>
              </table></div>
              <?= html_lista_jogos($jogos, $participantes, $total_rodadas, 'pontos_corridos') ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="tab-pane" data-pane="premiacao" hidden>
        <div class="list-row"><div class="thumb"><?= icone('trophy') ?></div><div class="info"><b>Premiação</b><span><?= e($c['premiacao'] ?: 'A definir') ?></span></div></div>
        <?php foreach ($categorias as $ct):
          $pod = podio($c, $ct, $partidas, $participantes, $resultados);
          if (!$pod) continue; ?>
          <div class="list-row">
            <div class="thumb"><?= icone('medal') ?></div>
            <div class="info"><b>Pódio<?= count($categorias) > 1 ? ' · ' . e($ct['nome']) : '' ?></b>
              <span>
                <?php foreach ($pod as $i => $nome): ?>
                  <span class="podium-<?= $i + 1 ?>"><?= $i + 1 ?>º</span> <?= e($nome) ?><?= $i < count($pod) - 1 ? ' &nbsp;·&nbsp; ' : '' ?>
                <?php endforeach; ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
