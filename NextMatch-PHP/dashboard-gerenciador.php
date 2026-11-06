<?php
require 'include/funcoes.php';
require 'include/chaveamento.php';
exigir_tipo('organizador');
$eu = usuario_logado();
$me = usuario_id();

/** Separa "Sub-17 Feminino, Sub-17 Masculino" (vírgula ou linha) em uma lista */
function separar_categorias(string $texto): array {
    $lista = array_filter(array_map('trim', preg_split('/[,\n;]+/', $texto)), fn($c) => $c !== '');
    return array_values(array_unique(array_map(fn($c) => mb_substr($c, 0, 80), $lista)));
}

/* ---------------- Ações ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $acao = post('acao');
    $volta = 'dashboard-gerenciador.php';

    if ($acao === 'excluir_campeonato') {
        $st = $pdo->prepare('SELECT id, nome, imagem FROM campeonatos WHERE id = ? AND organizador_id = ?');
        $st->execute([(int)post('campeonato_id'), $me]);
        $alvo = $st->fetch();
        if (!$alvo) flash('err', 'Campeonato não encontrado.');
        else {
            apagar_upload($alvo['imagem']);
            $pdo->prepare('DELETE FROM campeonatos WHERE id = ?')->execute([$alvo['id']]);
            flash('ok', 'Campeonato "' . $alvo['nome'] . '" excluído.');
        }
    }

    if ($acao === 'criar_campeonato') {
        $d = [
            'nome' => post('nome'), 'modalidade_id' => (int)post('modalidade_id'), 'tipo_participante' => post('tipo_participante'),
            'formato' => post('formato'), 'status' => post('status'), 'local' => post('local'), 'cidade' => post('cidade'),
            'data_inicio' => post('data_inicio'), 'data_fim' => post('data_fim'), 'vagas' => (int)post('vagas'),
            'premiacao' => post('premiacao'), 'regulamento' => post('regulamento'),
        ];
        $categorias = separar_categorias(post('categorias'));
        $erros = [];
        if (mb_strlen($d['nome']) < 3) $erros[] = 'Informe o nome do campeonato.';
        if (!in_array($d['modalidade_id'], array_map('intval', array_column(modalidades($pdo), 'id')), true)) $erros[] = 'Escolha a modalidade.';
        if (!in_array($d['tipo_participante'], ['equipe', 'individual'], true)) $erros[] = 'Escolha quem participa.';
        if (!isset(NM_FORMATOS[$d['formato']])) $erros[] = 'Escolha o formato de disputa.';
        if (!in_array($d['status'], ['em_breve', 'inscricoes_abertas'], true)) $d['status'] = 'em_breve';
        if (!strtotime($d['data_inicio'])) $erros[] = 'Informe a data de início.';
        if ($d['data_fim'] && strtotime($d['data_fim']) < strtotime($d['data_inicio'])) $erros[] = 'A data de término deve ser depois do início.';
        if ($d['vagas'] < 2 || $d['vagas'] > 1000) $erros[] = 'O número de vagas deve ficar entre 2 e 1000.';
        if (!$categorias) $erros[] = 'Informe pelo menos uma categoria.';

        $imagem = null;
        if (!$erros) { $imagem = salvar_upload('imagem', 'campeonatos', $erro); if ($erro) $erros[] = $erro; }

        if ($erros) {
            $_SESSION['form_campeonato'] = $_POST;
            flash('err', implode(' ', $erros));
            redirecionar($volta . '#novo');
        }
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO campeonatos (nome, modalidade_id, organizador_id, tipo_participante, formato, status, local, cidade, data_inicio, data_fim, vagas, premiacao, regulamento, imagem)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$d['nome'], $d['modalidade_id'], $me, $d['tipo_participante'], $d['formato'], $d['status'], $d['local'] ?: null, $d['cidade'] ?: null,
                       $d['data_inicio'], $d['data_fim'] ?: null, $d['vagas'], $d['premiacao'] ?: null, $d['regulamento'] ?: null, $imagem]);
        $novo = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO categorias (campeonato_id, nome) VALUES (?, ?)');
        foreach ($categorias as $cat) $ins->execute([$novo, $cat]);
        $pdo->commit();
        unset($_SESSION['form_campeonato']);
        flash('ok', 'Campeonato criado! ' . ($d['status'] === 'inscricoes_abertas' ? 'As inscrições já estão abertas.' : 'Abra as inscrições quando estiver pronto.'));
        redirecionar('gerenciar-campeonato.php?id=' . $novo);
    }

    if ($acao === 'responder_inscricao') {
        $erro = responder_inscricao($pdo, (int)post('inscricao_id'), post('resposta'), $me);
        flash($erro ? 'err' : 'ok', $erro ?: (post('resposta') === 'aprovada' ? 'Inscrição aprovada.' : 'Inscrição recusada.'));
        $volta .= '#pendentes';
    }

    if ($acao === 'publicar_noticia') {
        $titulo_n = post('titulo'); $resumo = post('resumo'); $conteudo = post('conteudo'); $categoria = post('categoria') ?: 'Plataforma';
        if (mb_strlen($titulo_n) < 5 || mb_strlen($resumo) < 10 || mb_strlen($conteudo) < 20) {
            flash('err', 'Preencha título, resumo e texto da notícia.');
            $_SESSION['form_noticia'] = $_POST;
        } else {
            $imagem = salvar_upload('imagem', 'noticias', $erro);
            if ($erro) flash('err', $erro);
            else {
                $pdo->prepare('INSERT INTO noticias (titulo, resumo, conteudo, categoria, imagem, autor_id) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([mb_substr($titulo_n, 0, 180), mb_substr($resumo, 0, 300), $conteudo, mb_substr($categoria, 0, 60), $imagem, $me]);
                unset($_SESSION['form_noticia']);
                flash('ok', 'Notícia publicada.');
                redirecionar('noticias.php?id=' . $pdo->lastInsertId());
            }
        }
        $volta .= '#noticia';
    }

    redirecionar($volta);
}

/* ---------------- Dados ---------------- */
$campeonatos = buscar_campeonatos($pdo, ['organizador' => $me]);
$ids = array_map('intval', array_column($campeonatos, 'id'));

$st = $pdo->prepare("SELECT i.*, c.nome AS campeonato, ct.nome AS categoria, COALESCE(eq.nome, u.nome) AS nome, c.tipo_participante
      FROM inscricoes i JOIN campeonatos c ON c.id = i.campeonato_id JOIN categorias ct ON ct.id = i.categoria_id
 LEFT JOIN equipes eq ON eq.id = i.equipe_id LEFT JOIN usuarios u ON u.id = i.atleta_id
     WHERE c.organizador_id = ? AND i.status = 'pendente' ORDER BY i.criado_em");
$st->execute([$me]);
$pendentes = $st->fetchAll();

$kpi = [
    'criados' => count($campeonatos),
    'abertos' => count(array_filter($campeonatos, fn($c) => $c['status'] === 'inscricoes_abertas')),
    'participantes' => array_sum(array_column($campeonatos, 'inscritos')),
    'andamento' => count(array_filter($campeonatos, fn($c) => $c['status'] === 'em_andamento')),
];

// Chave em destaque: primeiro mata-mata em andamento
$destaque = null;
foreach ($campeonatos as $c) if ($c['status'] === 'em_andamento' && $c['formato'] === 'mata_mata') { $destaque = $c; break; }
if ($destaque) {
    $part_destaque = participantes_do_campeonato($pdo, (int)$destaque['id']);
    $st = $pdo->prepare('SELECT * FROM partidas WHERE campeonato_id = ? AND categoria_id = (SELECT MIN(id) FROM categorias WHERE campeonato_id = ?) ORDER BY rodada, posicao');
    $st->execute([$destaque['id'], $destaque['id']]);
    $jogos_destaque = $st->fetchAll();
}

$ativos = array_filter($campeonatos, fn($c) => $c['status'] !== 'encerrado');
$form = $_SESSION['form_campeonato'] ?? [];
$form_n = $_SESSION['form_noticia'] ?? [];
unset($_SESSION['form_campeonato'], $_SESSION['form_noticia']);
$v = fn($k, $padrao = '') => e($form[$k] ?? $padrao);

$titulo = 'Painel do organizador';
$nav_ativa = '';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-head">
      <div>
        <h1>Olá, <?= e(explode(' ', $eu['nome'])[0]) ?> 👋</h1>
        <p>Crie campeonatos, defina categorias e acompanhe os chaveamentos.</p>
      </div>
      <a href="#novo" class="btn btn-primary"><?= icone('plus') ?> Novo campeonato</a>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="ico"><?= icone('trophy') ?></div><b><?= $kpi['criados'] ?></b><span>Campeonatos criados</span></div>
      <div class="kpi"><div class="ico"><?= icone('bell') ?></div><b><?= $kpi['abertos'] ?></b><span>Com inscrições abertas</span></div>
      <div class="kpi"><div class="ico"><?= icone('users') ?></div><b><?= $kpi['participantes'] ?></b><span>Participantes confirmados</span></div>
      <div class="kpi"><div class="ico"><?= icone('bracket') ?></div><b><?= $kpi['andamento'] ?></b><span>Em andamento</span></div>
    </div>

    <?php if ($pendentes): ?>
    <div class="panel" id="pendentes">
      <div class="panel-head"><h3>Inscrições aguardando aprovação</h3><span class="chip"><?= count($pendentes) ?> pendente(s)</span></div>
      <?php foreach ($pendentes as $p): ?>
        <div class="list-row">
          <div class="thumb"><?= icone($p['equipe_id'] ? 'users' : 'user') ?></div>
          <div class="info"><b><?= e($p['nome']) ?></b><span><?= e($p['campeonato']) ?> · <?= e($p['categoria']) ?> · <?= e(tempo_relativo($p['criado_em'])) ?></span></div>
          <form method="post" class="actions">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="responder_inscricao"><input type="hidden" name="inscricao_id" value="<?= (int)$p['id'] ?>">
            <button name="resposta" value="aprovada" class="btn btn-primary btn-sm">Aprovar</button>
            <button name="resposta" value="recusada" class="btn btn-ghost btn-sm">Recusar</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head"><h3>Meus campeonatos</h3></div>
      <?php if (!$campeonatos): ?><p class="faint" style="font-size:13px;margin:0;">Você ainda não criou campeonatos. <a href="#novo" style="color:var(--accent);">Crie o primeiro</a>.</p><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Campeonato</th><th>Modalidade</th><th>Status</th><th>Inscritos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($campeonatos as $c): ?>
          <tr>
            <td><b><?= e($c['nome']) ?></b><?= $c['imagem'] ? '' : ' <a href="gerenciar-campeonato.php?id=' . (int)$c['id'] . '#info" class="chip yellow" title="Adicionar imagem de capa">Sem imagem</a>' ?><br><span class="faint" style="font-size:12px;"><?= e(data_br($c['data_inicio'])) ?> · <?= e(NM_FORMATOS[$c['formato']]) ?></span></td>
            <td><?= e($c['modalidade']) ?></td>
            <td><?= chip_status($c['status']) ?></td>
            <td><?= (int)$c['inscritos'] ?>/<?= (int)$c['vagas'] ?><?= $c['pendentes'] ? ' <span class="chip yellow" style="margin-left:4px;">+' . (int)$c['pendentes'] . '</span>' : '' ?></td>
            <td style="text-align:right;white-space:nowrap;">
              <a href="gerenciar-campeonato.php?id=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">Gerenciar</a>
              <form method="post" style="display:inline;" data-confirm="Excluir definitivamente &quot;<?= e($c['nome']) ?>&quot;, com inscrições e jogos? Não dá para desfazer.">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir_campeonato"><input type="hidden" name="campeonato_id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-danger btn-sm" aria-label="Excluir <?= e($c['nome']) ?>"><?= icone('trash') ?> Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <?php if ($ativos): ?>
    <div class="panel">
      <div class="panel-head"><h3>Participantes confirmados</h3><span class="chip gray">Campeonatos ativos</span></div>
      <?= grafico_barras(array_map(fn($c) => ['rotulo' => $c['nome'], 'valor' => (int)$c['inscritos'], 'link' => 'gerenciar-campeonato.php?id=' . $c['id']], array_values($ativos)), '', 'Inscritos confirmados por campeonato') ?>
      <p class="field-hint" style="margin-top:12px;">Equipes ou atletas aprovados em cada campeonato ativo.</p>
    </div>
    <?php endif; ?>

    <?php if ($destaque && $jogos_destaque): ?>
    <div class="panel">
      <div class="panel-head">
        <h3>Chaveamento — <?= e($destaque['nome']) ?></h3>
        <a href="gerenciar-campeonato.php?id=<?= (int)$destaque['id'] ?>#jogos" class="btn btn-ghost btn-sm">Lançar resultados</a>
      </div>
      <?= html_chave($jogos_destaque, $part_destaque) ?>
    </div>
    <?php endif; ?>

    <div class="panel" id="novo">
      <div class="panel-head"><h3>Criar novo campeonato</h3></div>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="criar_campeonato">
        <div class="field-row">
          <div class="field"><label for="c_nome">Nome do campeonato</label><input id="c_nome" name="nome" value="<?= $v('nome') ?>" placeholder="Ex.: Copa NextMatch de Basquete" required></div>
          <div class="field"><label for="c_mod">Modalidade</label>
            <select id="c_mod" name="modalidade_id">
              <?php foreach (modalidades($pdo) as $m): ?><option value="<?= $m['id'] ?>" <?= ($form['modalidade_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label for="c_tipo">Quem se inscreve</label>
            <select id="c_tipo" name="tipo_participante">
              <option value="equipe" <?= ($form['tipo_participante'] ?? '') === 'equipe' ? 'selected' : '' ?>>Equipes (inscritas pelo professor)</option>
              <option value="individual" <?= ($form['tipo_participante'] ?? '') === 'individual' ? 'selected' : '' ?>>Atletas individualmente</option>
            </select>
          </div>
          <div class="field"><label for="c_formato">Formato de disputa</label>
            <select id="c_formato" name="formato">
              <?php foreach (NM_FORMATOS as $k => $rot): ?><option value="<?= $k ?>" <?= ($form['formato'] ?? '') === $k ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field"><label for="c_cats">Categorias</label><input id="c_cats" name="categorias" value="<?= $v('categorias') ?>" placeholder="Ex.: Sub-17 Feminino, Sub-17 Masculino" required>
          <p class="field-hint">Separe as categorias por vírgula. Cada categoria tem seu próprio chaveamento.</p></div>
        <div class="field-row">
          <div class="field"><label for="c_ini">Data de início</label><input id="c_ini" name="data_inicio" type="date" value="<?= $v('data_inicio') ?>" required></div>
          <div class="field"><label for="c_fim">Data de término (opcional)</label><input id="c_fim" name="data_fim" type="date" value="<?= $v('data_fim') ?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label for="c_local">Local</label><input id="c_local" name="local" value="<?= $v('local') ?>" placeholder="Ex.: Ginásio Municipal"></div>
          <div class="field"><label for="c_cidade">Cidade</label><input id="c_cidade" name="cidade" value="<?= $v('cidade') ?>" placeholder="Ex.: Jundiaí/SP"></div>
        </div>
        <div class="field-row">
          <div class="field"><label for="c_vagas">Vagas</label><input id="c_vagas" name="vagas" type="number" min="2" max="1000" value="<?= $v('vagas', '16') ?>"></div>
          <div class="field"><label for="c_premio">Premiação</label><input id="c_premio" name="premiacao" value="<?= $v('premiacao') ?>" placeholder="Ex.: Troféu + medalhas"></div>
        </div>
        <div class="field"><label for="c_reg">Regulamento</label><textarea id="c_reg" name="regulamento" placeholder="Formato de disputa, número de atletas, critérios de desempate..."><?= $v('regulamento') ?></textarea></div>
        <div class="field-row">
          <div class="field"><label for="c_status">Inscrições</label>
            <select id="c_status" name="status">
              <option value="inscricoes_abertas" <?= ($form['status'] ?? '') === 'inscricoes_abertas' ? 'selected' : '' ?>>Abrir inscrições agora</option>
              <option value="em_breve" <?= ($form['status'] ?? '') === 'em_breve' ? 'selected' : '' ?>>Publicar como "em breve"</option>
            </select>
          </div>
          <div class="field"><label for="c_img">Imagem de capa (opcional)</label><input id="c_img" name="imagem" type="file" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        <button type="submit" class="btn btn-primary">Criar campeonato</button>
      </form>
    </div>

    <div class="panel" id="noticia">
      <div class="panel-head"><h3>Publicar notícia</h3><a href="noticias.php" class="btn btn-ghost btn-sm">Ver notícias</a></div>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="publicar_noticia">
        <div class="field-row">
          <div class="field"><label for="n_tit">Título</label><input id="n_tit" name="titulo" maxlength="180" value="<?= e($form_n['titulo'] ?? '') ?>" required></div>
          <div class="field"><label for="n_cat">Categoria</label><input id="n_cat" name="categoria" maxlength="60" value="<?= e($form_n['categoria'] ?? '') ?>" placeholder="Ex.: Futebol, Plataforma" list="cats-noticia">
            <datalist id="cats-noticia"><?php foreach (modalidades($pdo) as $m): ?><option value="<?= e($m['nome']) ?>"><?php endforeach; ?><option value="Plataforma"></datalist></div>
        </div>
        <div class="field"><label for="n_res">Resumo</label><input id="n_res" name="resumo" maxlength="300" value="<?= e($form_n['resumo'] ?? '') ?>" placeholder="Uma ou duas frases que aparecem no card" required></div>
        <div class="field"><label for="n_txt">Texto</label><textarea id="n_txt" name="conteudo" style="min-height:160px;" placeholder="Deixe uma linha em branco entre os parágrafos." required><?= e($form_n['conteudo'] ?? '') ?></textarea></div>
        <div class="field"><label for="n_img">Imagem (opcional)</label><input id="n_img" name="imagem" type="file" accept="image/jpeg,image/png,image/webp"></div>
        <button type="submit" class="btn btn-primary">Publicar</button>
      </form>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
