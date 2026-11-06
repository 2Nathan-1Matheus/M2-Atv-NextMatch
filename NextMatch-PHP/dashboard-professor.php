<?php
require 'include/funcoes.php';
exigir_tipo('professor');
$eu = usuario_logado();
$me = usuario_id();

/** Busca uma equipe garantindo que pertence ao professor logado */
function minha_equipe(PDO $pdo, int $equipe_id, int $me): ?array {
    $st = $pdo->prepare('SELECT * FROM equipes WHERE id = ? AND professor_id = ?');
    $st->execute([$equipe_id, $me]);
    return $st->fetch() ?: null;
}

/* ---------------- Ações ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $acao = post('acao');
    $volta = 'dashboard-professor.php';

    if ($acao === 'criar_equipe') {
        $nome = post('nome');
        $mod = (int)post('modalidade_id');
        $mods_validas = array_map('intval', array_column(modalidades($pdo), 'id'));
        if (mb_strlen($nome) < 2) flash('err', 'Informe o nome da equipe.');
        elseif (!in_array($mod, $mods_validas, true)) flash('err', 'Escolha a modalidade.');
        else {
            $logo = salvar_upload('logo', 'equipes', $erro);
            if ($erro) flash('err', $erro);
            else {
                $pdo->prepare('INSERT INTO equipes (nome, modalidade_id, professor_id, cidade, logo) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$nome, $mod, $me, post('cidade') ?: null, $logo]);
                $novo = (int)$pdo->lastInsertId();
                flash('ok', 'Equipe ' . $nome . ' criada! Agora adicione os atletas.');
                $volta .= '?equipe=' . $novo . '#elenco';
            }
        }
    }

    if ($acao === 'editar_equipe' && ($eq = minha_equipe($pdo, (int)post('equipe_id'), $me))) {
        $logo = salvar_upload('logo', 'equipes', $erro);
        if ($erro) flash('err', $erro);
        elseif (mb_strlen(post('nome')) < 2) flash('err', 'Informe o nome da equipe.');
        else {
            if ($logo) apagar_upload($eq['logo']);
            $pdo->prepare('UPDATE equipes SET nome = ?, cidade = ?, logo = ? WHERE id = ?')
                ->execute([post('nome'), post('cidade') ?: null, $logo ?: $eq['logo'], $eq['id']]);
            flash('ok', 'Equipe atualizada.');
        }
        $volta .= '?equipe=' . $eq['id'] . '#elenco';
    }

    if ($acao === 'excluir_equipe' && ($eq = minha_equipe($pdo, (int)post('equipe_id'), $me))) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM inscricoes WHERE equipe_id = ? AND status <> 'recusada'");
        $st->execute([$eq['id']]);
        if ((int)$st->fetchColumn() > 0) flash('err', 'Esta equipe tem inscrições em campeonatos e não pode ser excluída.');
        else {
            apagar_upload($eq['logo']);
            $pdo->prepare('DELETE FROM equipes WHERE id = ?')->execute([$eq['id']]);
            flash('ok', 'Equipe excluída.');
        }
    }

    if ($acao === 'adicionar_atleta' && ($eq = minha_equipe($pdo, (int)post('equipe_id'), $me))) {
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND tipo = 'atleta'");
        $st->execute([mb_strtolower(post('email'))]);
        $atleta = $st->fetch();
        if (!$atleta) flash('err', 'Nenhum atleta cadastrado com esse e-mail. Peça para ele criar uma conta de atleta.');
        else {
            $pdo->prepare("INSERT INTO equipe_atletas (equipe_id, atleta_id, status) VALUES (?, ?, 'aprovado')
                           ON DUPLICATE KEY UPDATE status = 'aprovado'")->execute([$eq['id'], $atleta['id']]);
            notificar($pdo, (int)$atleta['id'], 'Você entrou em uma equipe', $eu['nome'] . ' adicionou você à equipe ' . $eq['nome'] . '.', 'equipes.php?id=' . $eq['id']);
            flash('ok', $atleta['nome'] . ' agora faz parte de ' . $eq['nome'] . '.');
        }
        $volta .= '?equipe=' . $eq['id'] . '#elenco';
    }

    if ($acao === 'remover_atleta' && ($eq = minha_equipe($pdo, (int)post('equipe_id'), $me))) {
        $st = $pdo->prepare('DELETE FROM equipe_atletas WHERE equipe_id = ? AND atleta_id = ?');
        $st->execute([$eq['id'], (int)post('atleta_id')]);
        if ($st->rowCount()) {
            notificar($pdo, (int)post('atleta_id'), 'Saída de equipe', 'Você não faz mais parte da equipe ' . $eq['nome'] . '.', 'equipes.php');
            flash('ok', 'Atleta removido da equipe.');
        }
        $volta .= '?equipe=' . $eq['id'] . '#elenco';
    }

    if ($acao === 'responder_solicitacao') {
        $st = $pdo->prepare("SELECT ea.*, eq.nome AS equipe, u.nome AS atleta FROM equipe_atletas ea JOIN equipes eq ON eq.id = ea.equipe_id
                               JOIN usuarios u ON u.id = ea.atleta_id WHERE ea.id = ? AND eq.professor_id = ? AND ea.status = 'pendente'");
        $st->execute([(int)post('solicitacao_id'), $me]);
        $sol = $st->fetch();
        if ($sol) {
            $aceitar = post('resposta') === 'aceitar';
            $pdo->prepare('UPDATE equipe_atletas SET status = ? WHERE id = ?')->execute([$aceitar ? 'aprovado' : 'recusado', $sol['id']]);
            notificar($pdo, (int)$sol['atleta_id'], $aceitar ? 'Solicitação aceita' : 'Solicitação recusada',
                $aceitar ? 'Bem-vindo(a) à equipe ' . $sol['equipe'] . '!' : 'Seu pedido para entrar em ' . $sol['equipe'] . ' foi recusado.', 'equipes.php?id=' . $sol['equipe_id']);
            flash('ok', $aceitar ? $sol['atleta'] . ' entrou na equipe ' . $sol['equipe'] . '.' : 'Solicitação recusada.');
        }
        $volta .= '#solicitacoes';
    }

    if ($acao === 'inscrever_equipe' && ($eq = minha_equipe($pdo, (int)post('equipe_id'), $me))) {
        [$camp_id, $cat_id] = array_map('intval', explode(':', post('destino') . ':0'));
        $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM inscricoes i WHERE i.campeonato_id = c.id AND i.status = 'aprovada') AS aprovadas
                               FROM campeonatos c JOIN categorias ct ON ct.campeonato_id = c.id AND ct.id = ? WHERE c.id = ?");
        $st->execute([$cat_id, $camp_id]);
        $c = $st->fetch();
        if (!$c) flash('err', 'Escolha um campeonato e uma categoria.');
        elseif ($c['status'] !== 'inscricoes_abertas' || $c['tipo_participante'] !== 'equipe') flash('err', 'Este campeonato não está recebendo inscrições de equipes.');
        elseif ((int)$c['modalidade_id'] !== (int)$eq['modalidade_id']) flash('err', 'A modalidade da equipe é diferente da do campeonato.');
        elseif ((int)$c['aprovadas'] >= (int)$c['vagas']) flash('err', 'As vagas deste campeonato já foram preenchidas.');
        else {
            try {
                $pdo->prepare('INSERT INTO inscricoes (campeonato_id, categoria_id, equipe_id, inscrito_por) VALUES (?, ?, ?, ?)')->execute([$camp_id, $cat_id, $eq['id'], $me]);
                notificar($pdo, (int)$c['organizador_id'], 'Nova inscrição', $eq['nome'] . ' pediu inscrição em ' . $c['nome'] . '.', 'gerenciar-campeonato.php?id=' . $camp_id . '#inscricoes');
                flash('ok', $eq['nome'] . ' foi inscrita em ' . $c['nome'] . '. Aguarde a aprovação do organizador.');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;
                flash('err', 'Essa equipe já está inscrita nesta categoria.');
            }
        }
        $volta .= '#inscricoes';
    }

    redirecionar($volta);
}

/* ---------------- Dados ---------------- */
$st = $pdo->prepare("SELECT eq.*, m.nome AS modalidade, m.icone,
           (SELECT COUNT(*) FROM equipe_atletas ea WHERE ea.equipe_id = eq.id AND ea.status = 'aprovado') AS atletas,
           (SELECT COUNT(*) FROM equipe_atletas ea WHERE ea.equipe_id = eq.id AND ea.status = 'pendente') AS pendentes
      FROM equipes eq JOIN modalidades m ON m.id = eq.modalidade_id WHERE eq.professor_id = ? ORDER BY eq.nome");
$st->execute([$me]);
$equipes = $st->fetchAll();
$ids_equipes = array_map('intval', array_column($equipes, 'id'));
$in_eq = $ids_equipes ? implode(',', $ids_equipes) : '0';

$sel_id = get_int('equipe', $ids_equipes[0] ?? 0);
$sel = null;
foreach ($equipes as $eq) if ((int)$eq['id'] === $sel_id) $sel = $eq;
if (!$sel && $equipes) $sel = $equipes[0];

$elenco = [];
if ($sel) {
    $st = $pdo->prepare("SELECT u.id, u.nome, u.email, u.foto, u.posicao FROM equipe_atletas ea JOIN usuarios u ON u.id = ea.atleta_id
                          WHERE ea.equipe_id = ? AND ea.status = 'aprovado' ORDER BY u.nome");
    $st->execute([$sel['id']]);
    $elenco = $st->fetchAll();
}

$st = $pdo->prepare("SELECT ea.id, ea.criado_em, u.nome, u.posicao, u.cidade, eq.nome AS equipe FROM equipe_atletas ea
                       JOIN equipes eq ON eq.id = ea.equipe_id JOIN usuarios u ON u.id = ea.atleta_id
                      WHERE eq.professor_id = ? AND ea.status = 'pendente' ORDER BY ea.criado_em");
$st->execute([$me]);
$solicitacoes = $st->fetchAll();

$inscricoes = $pdo->query("SELECT i.*, c.nome AS campeonato, c.status AS status_camp, c.data_inicio, ct.nome AS categoria, eq.nome AS equipe
      FROM inscricoes i JOIN campeonatos c ON c.id = i.campeonato_id JOIN categorias ct ON ct.id = i.categoria_id JOIN equipes eq ON eq.id = i.equipe_id
     WHERE i.equipe_id IN ($in_eq)
  ORDER BY FIELD(c.status, 'em_andamento', 'inscricoes_abertas', 'em_breve', 'encerrado'), c.data_inicio")->fetchAll();

$proximos = $pdo->query("SELECT p.*, c.nome AS campeonato, COALESCE(ea.nome, ua.nome) AS nome_a, COALESCE(eb.nome, ub.nome) AS nome_b
      FROM partidas p JOIN campeonatos c ON c.id = p.campeonato_id
      LEFT JOIN inscricoes ia ON ia.id = p.inscricao_a_id LEFT JOIN equipes ea ON ea.id = ia.equipe_id LEFT JOIN usuarios ua ON ua.id = ia.atleta_id
      LEFT JOIN inscricoes ib ON ib.id = p.inscricao_b_id LEFT JOIN equipes eb ON eb.id = ib.equipe_id LEFT JOIN usuarios ub ON ub.id = ib.atleta_id
     WHERE p.status = 'agendada' AND (ia.equipe_id IN ($in_eq) OR ib.equipe_id IN ($in_eq))
  ORDER BY p.data_hora LIMIT 4")->fetchAll();

/* Campeonatos abertos para equipes, com categorias */
$abertos = $pdo->query("SELECT c.id, c.nome, c.modalidade_id, m.nome AS modalidade, ct.id AS categoria_id, ct.nome AS categoria
      FROM campeonatos c JOIN modalidades m ON m.id = c.modalidade_id JOIN categorias ct ON ct.campeonato_id = c.id
     WHERE c.status = 'inscricoes_abertas' AND c.tipo_participante = 'equipe' ORDER BY c.data_inicio, ct.id")->fetchAll();

$competicoes_ativas = count(array_filter($inscricoes, fn($i) => $i['status'] === 'aprovada' && $i['status_camp'] !== 'encerrado'));
$total_atletas = array_sum(array_column($equipes, 'atletas'));

$titulo = 'Painel do professor';
$nav_ativa = '';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-head">
      <div>
        <h1>Olá, <?= e(explode(' ', $eu['nome'])[0]) ?> 👋</h1>
        <p>Gerencie suas equipes, atletas e inscrições em um só lugar.</p>
      </div>
      <a href="#nova-equipe" class="btn btn-primary"><?= icone('plus') ?> Nova equipe</a>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="ico"><?= icone('users') ?></div><b><?= count($equipes) ?></b><span>Equipes gerenciadas</span></div>
      <div class="kpi"><div class="ico"><?= icone('user') ?></div><b><?= $total_atletas ?></b><span>Atletas vinculados</span></div>
      <div class="kpi"><div class="ico"><?= icone('bell') ?></div><b><?= count($solicitacoes) ?></b><span>Solicitações pendentes</span></div>
      <div class="kpi"><div class="ico"><?= icone('trophy') ?></div><b><?= $competicoes_ativas ?></b><span>Competições ativas</span></div>
    </div>

    <?php if ($solicitacoes): ?>
    <div class="panel" id="solicitacoes">
      <div class="panel-head"><h3>Solicitações para entrar nas equipes</h3><span class="chip"><?= count($solicitacoes) ?> pendente(s)</span></div>
      <?php foreach ($solicitacoes as $s): ?>
        <div class="list-row">
          <div class="thumb round txt"><?= e(iniciais($s['nome'])) ?></div>
          <div class="info"><b><?= e($s['nome']) ?></b><span>Quer entrar em <?= e($s['equipe']) ?><?= $s['posicao'] ? ' · ' . e($s['posicao']) : '' ?> · <?= e(tempo_relativo($s['criado_em'])) ?></span></div>
          <form method="post" class="actions">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="responder_solicitacao"><input type="hidden" name="solicitacao_id" value="<?= (int)$s['id'] ?>">
            <button name="resposta" value="aceitar" class="btn btn-primary btn-sm">Aceitar</button>
            <button name="resposta" value="recusar" class="btn btn-ghost btn-sm">Recusar</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="split-2">
      <div class="panel">
        <div class="panel-head"><h3>Minhas equipes</h3><a href="equipes.php" class="btn btn-ghost btn-sm">Todas as equipes</a></div>
        <?php if (!$equipes): ?><p class="faint" style="font-size:13px;margin:0;">Você ainda não cadastrou equipes. <a href="#nova-equipe" style="color:var(--accent);">Cadastre a primeira</a>.</p><?php endif; ?>
        <?php foreach ($equipes as $eq): ?>
          <div class="list-row">
            <div class="thumb"><?= $eq['logo'] ? '<img src="' . e($eq['logo']) . '" alt="">' : icone_modalidade($eq['icone']) ?></div>
            <div class="info"><b><?= e($eq['nome']) ?></b><span><?= e($eq['modalidade']) ?> · <?= (int)$eq['atletas'] ?> atletas<?= $eq['pendentes'] ? ' · ' . (int)$eq['pendentes'] . ' pedido(s)' : '' ?></span></div>
            <a href="?equipe=<?= (int)$eq['id'] ?>#elenco" class="btn btn-ghost btn-sm">Gerenciar</a>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="panel">
        <div class="panel-head"><h3>Atletas por equipe</h3></div>
        <?= grafico_barras(array_map(fn($eq) => ['rotulo' => $eq['nome'], 'valor' => (int)$eq['atletas'], 'link' => 'equipes.php?id=' . $eq['id']], $equipes), '', 'Atletas por equipe') ?>
      </div>
    </div>

    <?php if ($sel): ?>
    <div class="panel" id="elenco" style="margin-top:20px;">
      <div class="panel-head">
        <h3>Elenco — <?= e($sel['nome']) ?></h3>
        <?php if (count($equipes) > 1): ?>
          <form method="get" class="field" style="margin:0;"><select name="equipe" data-autosubmit aria-label="Trocar equipe" style="padding:8px 10px;">
            <?php foreach ($equipes as $eq): ?><option value="<?= (int)$eq['id'] ?>" <?= (int)$eq['id'] === (int)$sel['id'] ? 'selected' : '' ?>><?= e($eq['nome']) ?></option><?php endforeach; ?>
          </select></form>
        <?php endif; ?>
      </div>

      <?php if (!$elenco): ?><p class="faint" style="font-size:13px;">Nenhum atleta nesta equipe ainda.</p><?php endif; ?>
      <?php foreach ($elenco as $a): ?>
        <div class="list-row">
          <div class="thumb round txt"><?= $a['foto'] ? '<img src="' . e($a['foto']) . '" alt="">' : e(iniciais($a['nome'])) ?></div>
          <div class="info"><b><?= e($a['nome']) ?></b><span><?= e($a['posicao'] ?: 'Atleta') ?> · <?= e($a['email']) ?></span></div>
          <form method="post" data-confirm="Remover <?= e($a['nome']) ?> da equipe?">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="remover_atleta"><input type="hidden" name="equipe_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="atleta_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-ghost btn-sm" aria-label="Remover <?= e($a['nome']) ?>"><?= icone('trash') ?></button>
          </form>
        </div>
      <?php endforeach; ?>

      <form method="post" class="form-inline" style="margin-top:16px;">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="adicionar_atleta"><input type="hidden" name="equipe_id" value="<?= (int)$sel['id'] ?>">
        <div class="field"><label for="email_atleta">Adicionar atleta pelo e-mail da conta</label><input id="email_atleta" name="email" type="email" placeholder="atleta@exemplo.com" required></div>
        <button type="submit" class="btn btn-primary">Adicionar</button>
      </form>
      <p class="field-hint">O atleta precisa ter uma conta do tipo Atleta. Ele também pode pedir para entrar pela página da equipe.</p>

      <details style="margin-top:16px;">
        <summary class="btn btn-ghost btn-sm" style="list-style:none;display:inline-flex;"><?= icone('edit') ?> Editar dados da equipe</summary>
        <form method="post" enctype="multipart/form-data" style="margin-top:14px;">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="editar_equipe"><input type="hidden" name="equipe_id" value="<?= (int)$sel['id'] ?>">
          <div class="field-row">
            <div class="field"><label for="ed_nome">Nome</label><input id="ed_nome" name="nome" value="<?= e($sel['nome']) ?>" required></div>
            <div class="field"><label for="ed_cidade">Cidade</label><input id="ed_cidade" name="cidade" value="<?= e($sel['cidade']) ?>"></div>
          </div>
          <div class="field"><label for="ed_logo">Escudo / logo (opcional)</label><input id="ed_logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"></div>
          <div class="btn-row"><button class="btn btn-primary btn-sm">Salvar</button></div>
        </form>
        <form method="post" data-confirm="Excluir a equipe <?= e($sel['nome']) ?>? Essa ação não pode ser desfeita." style="margin-top:10px;">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir_equipe"><input type="hidden" name="equipe_id" value="<?= (int)$sel['id'] ?>">
          <button class="btn btn-danger btn-sm"><?= icone('trash') ?> Excluir equipe</button>
        </form>
      </details>
    </div>
    <?php endif; ?>

    <div class="panel" id="inscricoes">
      <div class="panel-head"><h3>Inscrever equipe em campeonato</h3><a href="competicoes.php?status=inscricoes_abertas" class="btn btn-ghost btn-sm">Ver abertos</a></div>
      <?php if (!$equipes): ?>
        <p class="faint" style="font-size:13px;margin:0;">Cadastre uma equipe para poder inscrevê-la.</p>
      <?php elseif (!$abertos): ?>
        <p class="faint" style="font-size:13px;margin:0;">Nenhum campeonato de equipes com inscrições abertas no momento.</p>
      <?php else: ?>
        <form method="post" class="form-inline">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="inscrever_equipe">
          <div class="field"><label for="insc_equipe">Equipe</label>
            <select id="insc_equipe" name="equipe_id" data-filtra="insc_destino" data-aviso="insc_aviso" required>
              <?php foreach ($equipes as $eq): ?><option value="<?= (int)$eq['id'] ?>" data-modalidade="<?= (int)$eq['modalidade_id'] ?>"><?= e($eq['nome']) ?> (<?= e($eq['modalidade']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="insc_destino">Campeonato / categoria</label>
            <select id="insc_destino" name="destino" required>
              <option value="">Escolha</option>
              <?php $grupo = null; foreach ($abertos as $a):
                if ($grupo !== $a['id']) { if ($grupo !== null) echo '</optgroup>'; $grupo = $a['id']; echo '<optgroup label="' . e($a['nome'] . ' · ' . $a['modalidade']) . '">'; } ?>
                <option value="<?= (int)$a['id'] ?>:<?= (int)$a['categoria_id'] ?>" data-modalidade="<?= (int)$a['modalidade_id'] ?>"><?= e($a['categoria']) ?></option>
              <?php endforeach; if ($grupo !== null) echo '</optgroup>'; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Inscrever</button>
        </form>
        <p class="field-hint" id="insc_aviso" hidden>Não há campeonatos abertos da modalidade desta equipe.</p>
      <?php endif; ?>

      <?php if ($inscricoes): ?>
        <h4 class="mini-head" style="margin-top:22px;">Inscrições das minhas equipes</h4>
        <?php foreach ($inscricoes as $i): ?>
          <div class="list-row">
            <div class="thumb"><?= icone('trophy') ?></div>
            <div class="info"><b><a href="detalhes-competicao.php?id=<?= (int)$i['campeonato_id'] ?>"><?= e($i['campeonato']) ?></a></b><span><?= e($i['equipe']) ?> · <?= e($i['categoria']) ?> · <?= e(data_br($i['data_inicio'])) ?></span></div>
            <?= $i['status'] === 'aprovada' ? chip_status($i['status_camp']) : chip_inscricao($i['status']) ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($proximos): ?>
    <div class="panel">
      <div class="panel-head"><h3>Próximos jogos das minhas equipes</h3><a href="calendario.php" class="btn btn-ghost btn-sm">Calendário</a></div>
      <?php foreach ($proximos as $p): ?>
        <a class="list-row" href="detalhes-competicao.php?id=<?= (int)$p['campeonato_id'] ?>#chave">
          <div class="thumb"><?= icone('calendar') ?></div>
          <div class="info"><b><?= e($p['nome_a'] ?? 'A definir') ?> x <?= e($p['nome_b'] ?? 'A definir') ?></b><span><?= e($p['campeonato']) ?> · <?= e(data_hora_br($p['data_hora'])) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="panel" id="nova-equipe">
      <div class="panel-head"><h3>Cadastrar nova equipe</h3></div>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="criar_equipe">
        <div class="field-row">
          <div class="field"><label for="nova_nome">Nome da equipe</label><input id="nova_nome" name="nome" placeholder="Ex.: Falcões FC" required></div>
          <div class="field"><label for="nova_mod">Modalidade</label>
            <select id="nova_mod" name="modalidade_id" required>
              <?php foreach (modalidades($pdo) as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label for="nova_cidade">Cidade</label><input id="nova_cidade" name="cidade" placeholder="Ex.: Campinas/SP"></div>
          <div class="field"><label for="nova_logo">Escudo / logo (opcional)</label><input id="nova_logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar equipe</button>
      </form>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
