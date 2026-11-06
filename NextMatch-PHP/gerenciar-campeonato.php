<?php
require 'include/funcoes.php';
require 'include/chaveamento.php';
exigir_tipo('organizador');
$me = usuario_id();

$id = get_int('id');
$st = $pdo->prepare('SELECT c.*, m.nome AS modalidade, m.icone FROM campeonatos c JOIN modalidades m ON m.id = c.modalidade_id WHERE c.id = ? AND c.organizador_id = ?');
$st->execute([$id, $me]);
$c = $st->fetch();
if (!$c) {
    flash('err', 'Campeonato não encontrado ou você não é o organizador dele.');
    redirecionar('dashboard-gerenciador.php');
}
$link_publico = 'detalhes-competicao.php?id=' . $id;

function categorias_de(PDO $pdo, int $id): array {
    $st = $pdo->prepare('SELECT ct.*, (SELECT COUNT(*) FROM inscricoes i WHERE i.categoria_id = ct.id) AS inscricoes,
                                (SELECT COUNT(*) FROM partidas p WHERE p.categoria_id = ct.id) AS partidas
                           FROM categorias ct WHERE ct.campeonato_id = ? ORDER BY ct.id');
    $st->execute([$id]);
    return $st->fetchAll();
}

/** Se todas as partidas de todas as categorias tiverem resultado, encerra o campeonato */
function encerrar_se_concluido(PDO $pdo, array $c): bool {
    if ($c['formato'] === 'classificacao' || $c['status'] === 'encerrado') return false;
    $st = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'agendada') AS abertas FROM partidas WHERE campeonato_id = ?");
    $st->execute([$c['id']]);
    $r = $st->fetch();
    $cats_sem_jogos = $pdo->prepare('SELECT COUNT(*) FROM categorias ct WHERE ct.campeonato_id = ? AND NOT EXISTS (SELECT 1 FROM partidas p WHERE p.categoria_id = ct.id)');
    $cats_sem_jogos->execute([$c['id']]);
    if ((int)$r['total'] === 0 || (int)$r['abertas'] > 0 || (int)$cats_sem_jogos->fetchColumn() > 0) return false;
    $pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$c['id']]);
    notificar_participantes($pdo, (int)$c['id'], 'Campeonato encerrado', $c['nome'] . ' terminou. Confira o pódio e a classificação final!', 'detalhes-competicao.php?id=' . $c['id'] . '#premiacao');
    return true;
}

/* ---------------- Ações ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $acao = post('acao');
    $volta = 'gerenciar-campeonato.php?id=' . $id;
    $categorias = categorias_de($pdo, $id);
    $ids_cat = array_map('intval', array_column($categorias, 'id'));

    if ($acao === 'salvar_info') {
        $novo_status = post('status');
        $erros = [];
        if (mb_strlen(post('nome')) < 3) $erros[] = 'Informe o nome.';
        if (!isset(NM_STATUS[$novo_status])) $erros[] = 'Status inválido.';
        if (!strtotime(post('data_inicio'))) $erros[] = 'Informe a data de início.';
        if (post('data_fim') && strtotime(post('data_fim')) < strtotime(post('data_inicio'))) $erros[] = 'A data de término deve ser depois do início.';
        $vagas = (int)post('vagas');
        if ($vagas < 2 || $vagas > 1000) $erros[] = 'Vagas devem ficar entre 2 e 1000.';
        $tem_jogos = array_sum(array_column($categorias, 'partidas')) > 0;
        $tem_insc = array_sum(array_column($categorias, 'inscricoes')) > 0;
        $formato = $tem_jogos ? $c['formato'] : post('formato');
        $tipo = $tem_insc ? $c['tipo_participante'] : post('tipo_participante');
        if (!isset(NM_FORMATOS[$formato]) || !in_array($tipo, ['equipe', 'individual'], true)) $erros[] = 'Formato inválido.';
        $imagem = $erros ? null : salvar_upload('imagem', 'campeonatos', $erro);
        if (!empty($erro)) $erros[] = $erro;

        if ($erros) flash('err', implode(' ', $erros));
        else {
            if ($imagem) apagar_upload($c['imagem']);
            if (post('remover_imagem') && !$imagem) { apagar_upload($c['imagem']); $c['imagem'] = null; }
            $pdo->prepare('UPDATE campeonatos SET nome = ?, status = ?, formato = ?, tipo_participante = ?, data_inicio = ?, data_fim = ?, local = ?, cidade = ?, vagas = ?, premiacao = ?, regulamento = ?, imagem = ? WHERE id = ?')
                ->execute([post('nome'), $novo_status, $formato, $tipo, post('data_inicio'), post('data_fim') ?: null, post('local') ?: null, post('cidade') ?: null,
                           $vagas, post('premiacao') ?: null, post('regulamento') ?: null, $imagem ?: $c['imagem'], $id]);
            if ($novo_status !== $c['status']) {
                $msgs = [
                    'inscricoes_abertas' => 'As inscrições de ' . post('nome') . ' estão abertas.',
                    'em_andamento' => post('nome') . ' começou! Confira os jogos.',
                    'encerrado' => post('nome') . ' foi encerrado. Confira a classificação final.',
                    'em_breve' => post('nome') . ' voltou para "em breve".',
                ];
                notificar_participantes($pdo, $id, 'Status do campeonato atualizado', $msgs[$novo_status], $link_publico);
            }
            flash('ok', 'Informações salvas.');
        }
        $volta .= '#info';
    }

    if ($acao === 'salvar_imagem') {
        $imagem = salvar_upload('imagem', 'campeonatos', $erro);
        if ($erro) flash('err', $erro);
        elseif (!$imagem) flash('err', 'Escolha uma imagem para enviar.');
        else {
            apagar_upload($c['imagem']);
            $pdo->prepare('UPDATE campeonatos SET imagem = ? WHERE id = ?')->execute([$imagem, $id]);
            flash('ok', $c['imagem'] ? 'Imagem de capa atualizada.' : 'Imagem de capa adicionada.');
        }
        $volta .= '#info';
    }

    if ($acao === 'remover_imagem') {
        if ($c['imagem']) {
            apagar_upload($c['imagem']);
            $pdo->prepare('UPDATE campeonatos SET imagem = NULL WHERE id = ?')->execute([$id]);
            flash('ok', 'Imagem de capa removida.');
        }
        $volta .= '#info';
    }

    if ($acao === 'add_categoria') {
        $nome = mb_substr(post('nome'), 0, 80);
        if ($nome === '') flash('err', 'Informe o nome da categoria.');
        else { $pdo->prepare('INSERT INTO categorias (campeonato_id, nome) VALUES (?, ?)')->execute([$id, $nome]); flash('ok', 'Categoria adicionada.'); }
        $volta .= '#info';
    }

    if ($acao === 'remover_categoria') {
        $cat = (int)post('categoria_id');
        $info = array_values(array_filter($categorias, fn($x) => (int)$x['id'] === $cat))[0] ?? null;
        if (!$info) flash('err', 'Categoria não encontrada.');
        elseif ($info['inscricoes'] > 0) flash('err', 'Esta categoria já tem inscrições e não pode ser removida.');
        elseif (count($categorias) <= 1) flash('err', 'O campeonato precisa ter pelo menos uma categoria.');
        else { $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([$cat]); flash('ok', 'Categoria removida.'); }
        $volta .= '#info';
    }

    if ($acao === 'responder_inscricao') {
        $erro = responder_inscricao($pdo, (int)post('inscricao_id'), post('resposta'), $me);
        flash($erro ? 'err' : 'ok', $erro ?: (post('resposta') === 'aprovada' ? 'Inscrição aprovada.' : 'Inscrição recusada.'));
        $volta .= '#inscricoes';
    }

    if ($acao === 'remover_inscricao') {
        $st = $pdo->prepare('SELECT i.*, (SELECT COUNT(*) FROM partidas p WHERE p.categoria_id = i.categoria_id) AS jogos FROM inscricoes i WHERE i.id = ? AND i.campeonato_id = ?');
        $st->execute([(int)post('inscricao_id'), $id]);
        $insc = $st->fetch();
        if (!$insc) flash('err', 'Inscrição não encontrada.');
        elseif ($insc['jogos'] > 0) flash('err', 'A chave desta categoria já foi gerada. Gere novamente depois de remover, ou mantenha o participante.');
        else {
            notificar_inscricao($pdo, (int)$insc['id'], 'Inscrição cancelada', 'Sua inscrição em ' . $c['nome'] . ' foi cancelada pelo organizador.', $link_publico);
            $pdo->prepare('DELETE FROM inscricoes WHERE id = ?')->execute([$insc['id']]);
            flash('ok', 'Inscrição removida.');
        }
        $volta .= '#inscricoes';
    }

    if ($acao === 'gerar_chave') {
        $cat = (int)post('categoria_id');
        if (!in_array($cat, $ids_cat, true)) flash('err', 'Categoria inválida.');
        else {
            $erro = gerar_chaveamento($pdo, $c, $cat);
            if ($erro) flash('err', $erro);
            else {
                $nome_cat = array_values(array_filter($categorias, fn($x) => (int)$x['id'] === $cat))[0]['nome'];
                [$o_que, $gerado] = $c['formato'] === 'mata_mata' ? ['O chaveamento', 'gerado'] : ['A tabela de jogos', 'gerada'];
                notificar_participantes($pdo, $id, $c['formato'] === 'mata_mata' ? 'Chaveamento publicado' : 'Tabela de jogos publicada', $o_que . ' de ' . $c['nome'] . ' (' . $nome_cat . ') está disponível.', $link_publico . '#chave', $cat);
                $extra = '';
                if (in_array($c['status'], ['em_breve', 'inscricoes_abertas'], true)) {
                    $pdo->prepare("UPDATE campeonatos SET status = 'em_andamento' WHERE id = ?")->execute([$id]);
                    $extra = ' O campeonato passou para "Em andamento".';
                }
                flash('ok', $o_que . ' foi ' . $gerado . ' por sorteio.' . $extra);
            }
        }
        $volta .= '#jogos';
    }

    if ($acao === 'salvar_partida') {
        $st = $pdo->prepare('SELECT * FROM partidas WHERE id = ? AND campeonato_id = ?');
        $st->execute([(int)post('partida_id'), $id]);
        $p = $st->fetch();
        if (!$p) flash('err', 'Partida não encontrada.');
        else {
            $dh = post('data_hora');
            $pdo->prepare('UPDATE partidas SET data_hora = ?, local = ? WHERE id = ?')
                ->execute([$dh && strtotime($dh) ? date('Y-m-d H:i:s', strtotime($dh)) : null, post('local') ?: null, $p['id']]);
            $pa = post('placar_a'); $pb = post('placar_b');
            if ($pa !== '' && $pb !== '') {
                $erro = registrar_resultado($pdo, $p, $c['formato'], (int)$pa, (int)$pb, post('vencedor') !== '' ? (int)post('vencedor') : null);
                if ($erro) flash('err', $erro);
                else {
                    $part = participantes_do_campeonato($pdo, $id);
                    $na = $part[$p['inscricao_a_id']]['nome'] ?? '?'; $nb = $part[$p['inscricao_b_id']]['nome'] ?? '?';
                    notificar_participantes($pdo, $id, 'Resultado publicado', "$na $pa x $pb $nb — " . $c['nome'], $link_publico . '#chave', (int)$p['categoria_id']);
                    $fim = encerrar_se_concluido($pdo, $c);
                    flash('ok', 'Resultado salvo: ' . rtrim("$na $pa x $pb $nb", '.') . '.' . ($fim ? ' Todas as partidas terminaram e o campeonato foi encerrado.' : ''));
                }
            } else {
                flash('ok', 'Data e local da partida salvos.');
            }
        }
        $volta .= '#jogos';
    }

    if ($acao === 'salvar_resultados') {
        $cat = (int)post('categoria_id');
        $colocacoes = $_POST['colocacao'] ?? [];
        $marcas = $_POST['marca'] ?? [];
        $st = $pdo->prepare("SELECT id FROM inscricoes WHERE campeonato_id = ? AND categoria_id = ? AND status = 'aprovada'");
        $st->execute([$id, $cat]);
        $validas = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $salvos = 0;
        $upsert = $pdo->prepare('INSERT INTO resultados (inscricao_id, colocacao, marca) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE colocacao = VALUES(colocacao), marca = VALUES(marca)');
        $apagar = $pdo->prepare('DELETE FROM resultados WHERE inscricao_id = ?');
        foreach ($validas as $insc) {
            $col = (int)($colocacoes[$insc] ?? 0);
            if ($col > 0) { $upsert->execute([$insc, $col, mb_substr(trim((string)($marcas[$insc] ?? '')), 0, 40) ?: null]); $salvos++; }
            else $apagar->execute([$insc]);
        }
        if ($salvos) notificar_participantes($pdo, $id, 'Resultados publicados', 'A classificação de ' . $c['nome'] . ' foi publicada.', $link_publico . '#chave', $cat);
        flash('ok', $salvos . ' resultado(s) salvo(s).');
        $volta .= '#jogos';
    }

    if ($acao === 'excluir_campeonato') {
        if (post('confirmacao') !== $c['nome']) {
            flash('err', 'Digite o nome exato do campeonato para confirmar a exclusão.');
            $volta .= '#info';
        } else {
            apagar_upload($c['imagem']);
            $pdo->prepare('DELETE FROM campeonatos WHERE id = ?')->execute([$id]);
            flash('ok', 'Campeonato excluído.');
            redirecionar('dashboard-gerenciador.php');
        }
    }

    redirecionar($volta);
}

/* ---------------- Dados ---------------- */
$categorias = categorias_de($pdo, $id);
$participantes = participantes_do_campeonato($pdo, $id);
$st = $pdo->prepare('SELECT * FROM partidas WHERE campeonato_id = ? ORDER BY categoria_id, rodada, posicao');
$st->execute([$id]);
$partidas = $st->fetchAll();
$st = $pdo->prepare('SELECT r.* FROM resultados r JOIN inscricoes i ON i.id = r.inscricao_id WHERE i.campeonato_id = ?');
$st->execute([$id]);
$resultados = [];
foreach ($st->fetchAll() as $r) $resultados[$r['inscricao_id']] = $r;

$n_pend = count(array_filter($participantes, fn($p) => $p['status'] === 'pendente'));
$n_aprov = count(array_filter($participantes, fn($p) => $p['status'] === 'aprovada'));
$tem_jogos = (bool)$partidas;
$tem_insc = (bool)$participantes;
$aba_jogos = $c['formato'] === 'classificacao' ? 'Resultados' : ($c['formato'] === 'mata_mata' ? 'Chaveamento e resultados' : 'Tabela e resultados');

$titulo = 'Gerenciar · ' . $c['nome'];
$nav_ativa = '';
$sidebar_ativa = 'dashboard-gerenciador.php';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main">
    <a href="dashboard-gerenciador.php" class="faint" style="font-size:13px;">← Voltar ao painel</a>
    <div class="dash-head" style="margin-top:12px;">
      <div>
        <div class="btn-row" style="margin-bottom:6px;"><?= chip_status($c['status']) ?><span class="chip gray"><?= e($c['modalidade']) ?> · <?= e(NM_FORMATOS[$c['formato']]) ?></span></div>
        <h1><?= e($c['nome']) ?></h1>
        <p><?= e(periodo_br($c['data_inicio'], $c['data_fim'])) ?> · <?= $n_aprov ?>/<?= (int)$c['vagas'] ?> <?= $c['tipo_participante'] === 'equipe' ? 'equipes confirmadas' : 'atletas confirmados' ?></p>
      </div>
      <a href="<?= $link_publico ?>" class="btn btn-outline">Ver página pública</a>
    </div>

    <div class="panel">
      <div class="tabs" data-tabs role="tablist">
        <button type="button" class="active" data-tab="inscricoes" role="tab">Inscrições<?= $n_pend ? " ($n_pend)" : '' ?></button>
        <button type="button" data-tab="jogos" role="tab"><?= $aba_jogos ?></button>
        <button type="button" data-tab="info" role="tab">Informações e categorias</button>
      </div>

      <!-- INSCRIÇÕES -->
      <div class="tab-pane" data-pane="inscricoes">
        <?php if ($c['status'] === 'em_breve'): ?>
          <p class="note" style="margin-top:0;">As inscrições ainda não estão abertas. Mude o status na aba <b>Informações</b> para "Inscrições abertas".</p>
        <?php endif; ?>
        <?php foreach ($categorias as $ct):
          $lista = array_filter($participantes, fn($p) => (int)$p['categoria_id'] === (int)$ct['id']); ?>
          <div class="cat-block">
            <h4 class="mini-head"><?= e($ct['nome']) ?> · <?= count(array_filter($lista, fn($p) => $p['status'] === 'aprovada')) ?> confirmados</h4>
            <?php if (!$lista): ?><p class="faint" style="font-size:13px;margin:0;">Nenhuma inscrição nesta categoria.</p><?php endif; ?>
            <?php foreach ($lista as $p): ?>
              <div class="list-row">
                <div class="thumb<?= $p['equipe_id'] ? '' : ' round' ?>"><?= $p['imagem'] ? '<img src="' . e($p['imagem']) . '" alt="">' : icone($p['equipe_id'] ? 'users' : 'user') ?></div>
                <div class="info"><b><?= e($p['nome']) ?></b><span><?= e($p['cidade'] ?: '—') ?><?= $p['equipe_id'] ? ' · ' . (int)$p['atletas'] . ' atletas' : '' ?> · <?= e(tempo_relativo($p['criado_em'])) ?></span></div>
                <?= chip_inscricao($p['status']) ?>
                <form method="post" class="actions">
                  <?= csrf_campo() ?><input type="hidden" name="inscricao_id" value="<?= (int)$p['id'] ?>">
                  <?php if ($p['status'] !== 'aprovada'): ?>
                    <input type="hidden" name="acao" value="responder_inscricao">
                    <button name="resposta" value="aprovada" class="btn btn-primary btn-sm">Aprovar</button>
                    <?php if ($p['status'] === 'pendente'): ?><button name="resposta" value="recusada" class="btn btn-ghost btn-sm">Recusar</button><?php endif; ?>
                  <?php elseif (!$ct['partidas']): ?>
                    <input type="hidden" name="acao" value="remover_inscricao">
                    <button class="btn btn-ghost btn-sm" aria-label="Remover inscrição" onclick="return confirm('Remover esta inscrição?')"><?= icone('trash') ?></button>
                  <?php endif; ?>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- JOGOS / RESULTADOS -->
      <div class="tab-pane" data-pane="jogos" hidden>
        <?php foreach ($categorias as $ct):
          $cat = (int)$ct['id'];
          $aprovados = array_filter($participantes, fn($p) => (int)$p['categoria_id'] === $cat && $p['status'] === 'aprovada');
          $jogos = array_values(array_filter($partidas, fn($p) => (int)$p['categoria_id'] === $cat));
          $total_rodadas = $jogos ? max(array_column($jogos, 'rodada')) : 0; ?>
          <div class="cat-block">
            <div class="flex-between" style="flex-wrap:wrap;gap:10px;margin-bottom:12px;">
              <h4 class="mini-head" style="margin:0;"><?= e($ct['nome']) ?> · <?= count($aprovados) ?> participantes</h4>
              <?php if ($c['formato'] !== 'classificacao'): ?>
                <form method="post" <?= $jogos ? 'data-confirm="Isso apaga todos os jogos e resultados desta categoria e faz um novo sorteio. Continuar?"' : '' ?>>
                  <?= csrf_campo() ?><input type="hidden" name="acao" value="gerar_chave"><input type="hidden" name="categoria_id" value="<?= $cat ?>">
                  <button class="btn <?= $jogos ? 'btn-ghost' : 'btn-primary' ?> btn-sm" <?= count($aprovados) < 2 ? 'disabled title="São necessários 2 participantes aprovados"' : '' ?>>
                    <?= icone('bracket') ?> <?= $jogos ? 'Refazer sorteio' : ($c['formato'] === 'mata_mata' ? 'Gerar chaveamento' : 'Gerar tabela de jogos') ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>

            <?php if ($c['formato'] === 'classificacao'): ?>
              <?php if (!$aprovados): ?>
                <p class="faint" style="font-size:13px;margin:0;">Aprove inscrições para lançar os resultados.</p>
              <?php else: ?>
                <form method="post">
                  <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar_resultados"><input type="hidden" name="categoria_id" value="<?= $cat ?>">
                  <div class="table-wrap"><table>
                    <thead><tr><th>Atleta</th><th style="width:110px;">Colocação</th><th style="width:160px;">Marca (tempo/nota)</th></tr></thead>
                    <tbody>
                    <?php foreach ($aprovados as $p): $r = $resultados[$p['id']] ?? null; ?>
                      <tr><td><?= e($p['nome']) ?></td>
                          <td><input type="number" min="1" name="colocacao[<?= (int)$p['id'] ?>]" value="<?= $r ? (int)$r['colocacao'] : '' ?>" aria-label="Colocação de <?= e($p['nome']) ?>"></td>
                          <td><input type="text" maxlength="40" name="marca[<?= (int)$p['id'] ?>]" value="<?= e($r['marca'] ?? '') ?>" placeholder="Ex.: 00:28.91" aria-label="Marca de <?= e($p['nome']) ?>"></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                  <p class="field-hint">Deixe a colocação em branco para quem não completou a prova.</p>
                  <button class="btn btn-primary btn-sm" style="margin-top:10px;">Salvar resultados</button>
                </form>
              <?php endif; ?>

            <?php elseif (!$jogos): ?>
              <p class="faint" style="font-size:13px;margin:0;">
                <?= count($aprovados) < 2 ? 'Aprove pelo menos 2 inscrições nesta categoria para gerar os jogos.' : 'Quando as inscrições estiverem fechadas, clique em "' . ($c['formato'] === 'mata_mata' ? 'Gerar chaveamento' : 'Gerar tabela de jogos') . '". O sorteio é automático.' ?>
              </p>

            <?php else: ?>
              <?php if ($c['formato'] === 'mata_mata'): ?><?= html_chave($jogos, $participantes, false) ?><?php endif; ?>

              <?php foreach ($jogos as $j):
                if (!$j['inscricao_a_id'] && !$j['inscricao_b_id']) continue;
                if ($j['status'] === 'finalizada' && (!$j['inscricao_a_id'] || !$j['inscricao_b_id'])) continue; // avançou direto
                $na = $j['inscricao_a_id'] ? $participantes[$j['inscricao_a_id']]['nome'] : 'A definir';
                $nb = $j['inscricao_b_id'] ? $participantes[$j['inscricao_b_id']]['nome'] : 'A definir';
                $pronta = $j['inscricao_a_id'] && $j['inscricao_b_id'];
                $empate = $j['status'] === 'finalizada' && $j['placar_a'] == $j['placar_b']; ?>
                <form method="post" class="score-form" <?= $c['formato'] === 'mata_mata' ? 'data-mata-mata' : '' ?>>
                  <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar_partida"><input type="hidden" name="partida_id" value="<?= (int)$j['id'] ?>">
                  <div>
                    <div class="sf-meta" style="margin-bottom:6px;">
                      <span><?= e(nome_rodada((int)$j['rodada'], $total_rodadas, $c['formato'])) ?></span>
                      <?= $j['status'] === 'finalizada' ? '<span class="chip green">Encerrada</span>' : '<span class="chip gray">Agendada</span>' ?>
                    </div>
                    <div class="sf-teams">
                      <span class="right"><?= e($na) ?></span>
                      <input type="number" name="placar_a" min="0" value="<?= $j['status'] === 'finalizada' ? (int)$j['placar_a'] : '' ?>" <?= $pronta ? '' : 'disabled' ?> aria-label="Placar de <?= e($na) ?>">
                      <span class="faint" style="text-align:center;">×</span>
                      <input type="number" name="placar_b" min="0" value="<?= $j['status'] === 'finalizada' ? (int)$j['placar_b'] : '' ?>" <?= $pronta ? '' : 'disabled' ?> aria-label="Placar de <?= e($nb) ?>">
                      <span><?= e($nb) ?></span>
                    </div>
                    <?php if ($c['formato'] === 'mata_mata' && $pronta): ?>
                      <div class="desempate" style="margin-top:8px;" <?= $empate ? '' : 'hidden' ?>>
                        <label for="venc<?= (int)$j['id'] ?>">Empate: quem avançou no desempate (pênaltis, tie-break...)?</label>
                        <select id="venc<?= (int)$j['id'] ?>" name="vencedor" style="background:var(--bg-alt);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:8px;width:100%;">
                          <option value="">Escolha</option>
                          <option value="<?= (int)$j['inscricao_a_id'] ?>" <?= $empate && (int)$j['vencedor_id'] === (int)$j['inscricao_a_id'] ? 'selected' : '' ?>><?= e($na) ?></option>
                          <option value="<?= (int)$j['inscricao_b_id'] ?>" <?= $empate && (int)$j['vencedor_id'] === (int)$j['inscricao_b_id'] ? 'selected' : '' ?>><?= e($nb) ?></option>
                        </select>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="sf-extra">
                    <input type="datetime-local" name="data_hora" value="<?= $j['data_hora'] ? date('Y-m-d\TH:i', strtotime($j['data_hora'])) : '' ?>" aria-label="Data e hora">
                    <input type="text" name="local" value="<?= e($j['local']) ?>" placeholder="Local" aria-label="Local">
                    <button class="btn btn-primary btn-sm"><?= $pronta ? 'Salvar' : 'Salvar data' ?></button>
                  </div>
                </form>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- INFORMAÇÕES -->
      <div class="tab-pane" data-pane="info" hidden>
        <div class="cat-block" id="capa">
          <h4 class="mini-head">Imagem de capa</h4>
          <?php if ($c['imagem']): ?>
            <div class="detail-cover" style="margin:0 0 12px;max-width:520px;"><img src="<?= e($c['imagem']) ?>" alt="Capa atual de <?= e($c['nome']) ?>"></div>
          <?php else: ?>
            <p class="faint" style="font-size:13px;margin:0 0 12px;">Este campeonato ainda não tem imagem de capa.</p>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" class="form-inline">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar_imagem">
            <div class="field"><label for="i_img"><?= $c['imagem'] ? 'Trocar imagem' : 'Adicionar imagem' ?></label>
              <input id="i_img" name="imagem" type="file" accept="image/jpeg,image/png,image/webp" required>
              <p class="field-hint">JPG, PNG ou WEBP, até 2 MB. Aparece no card e na página do campeonato.</p></div>
            <button class="btn btn-primary"><?= $c['imagem'] ? 'Trocar imagem' : 'Enviar imagem' ?></button>
          </form>
          <?php if ($c['imagem']): ?>
            <form method="post" data-confirm="Remover a imagem de capa?" style="margin-top:8px;">
              <?= csrf_campo() ?><input type="hidden" name="acao" value="remover_imagem">
              <button class="btn btn-danger btn-sm"><?= icone('trash') ?> Remover imagem</button>
            </form>
          <?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar_info">
          <div class="field-row">
            <div class="field"><label for="i_nome">Nome</label><input id="i_nome" name="nome" value="<?= e($c['nome']) ?>" required></div>
            <div class="field"><label for="i_status">Status</label>
              <select id="i_status" name="status">
                <?php foreach (NM_STATUS as $k => [$rot]): ?><option value="<?= $k ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field-row">
            <div class="field"><label for="i_formato">Formato</label>
              <select id="i_formato" name="formato" <?= $tem_jogos ? 'disabled' : '' ?>>
                <?php foreach (NM_FORMATOS as $k => $rot): ?><option value="<?= $k ?>" <?= $c['formato'] === $k ? 'selected' : '' ?>><?= $rot ?></option><?php endforeach; ?>
              </select>
              <?php if ($tem_jogos): ?><p class="field-hint">Não pode ser alterado depois que os jogos foram gerados.</p><?php endif; ?>
            </div>
            <div class="field"><label for="i_tipo">Quem se inscreve</label>
              <select id="i_tipo" name="tipo_participante" <?= $tem_insc ? 'disabled' : '' ?>>
                <option value="equipe" <?= $c['tipo_participante'] === 'equipe' ? 'selected' : '' ?>>Equipes</option>
                <option value="individual" <?= $c['tipo_participante'] === 'individual' ? 'selected' : '' ?>>Atletas individualmente</option>
              </select>
              <?php if ($tem_insc): ?><p class="field-hint">Não pode ser alterado depois das primeiras inscrições.</p><?php endif; ?>
            </div>
          </div>
          <div class="field-row">
            <div class="field"><label for="i_ini">Início</label><input id="i_ini" name="data_inicio" type="date" value="<?= e($c['data_inicio']) ?>" required></div>
            <div class="field"><label for="i_fim">Término</label><input id="i_fim" name="data_fim" type="date" value="<?= e($c['data_fim']) ?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="i_local">Local</label><input id="i_local" name="local" value="<?= e($c['local']) ?>"></div>
            <div class="field"><label for="i_cidade">Cidade</label><input id="i_cidade" name="cidade" value="<?= e($c['cidade']) ?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="i_vagas">Vagas</label><input id="i_vagas" name="vagas" type="number" min="2" max="1000" value="<?= (int)$c['vagas'] ?>"></div>
            <div class="field"><label for="i_premio">Premiação</label><input id="i_premio" name="premiacao" value="<?= e($c['premiacao']) ?>"></div>
          </div>
          <div class="field"><label for="i_reg">Regulamento</label><textarea id="i_reg" name="regulamento"><?= e($c['regulamento']) ?></textarea></div>
          <button class="btn btn-primary">Salvar informações</button>
        </form>

        <div class="cat-block">
          <h4 class="mini-head">Categorias</h4>
          <?php foreach ($categorias as $ct): ?>
            <div class="list-row">
              <div class="thumb"><?= icone('layers') ?></div>
              <div class="info"><b><?= e($ct['nome']) ?></b><span><?= (int)$ct['inscricoes'] ?> inscrição(ões) · <?= (int)$ct['partidas'] ?> partida(s)</span></div>
              <?php if (!$ct['inscricoes'] && count($categorias) > 1): ?>
                <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="remover_categoria"><input type="hidden" name="categoria_id" value="<?= (int)$ct['id'] ?>">
                  <button class="btn btn-ghost btn-sm" aria-label="Remover categoria"><?= icone('trash') ?></button></form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <form method="post" class="form-inline" style="margin-top:12px;">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="add_categoria">
            <div class="field"><label for="nova_cat">Nova categoria</label><input id="nova_cat" name="nome" maxlength="80" placeholder="Ex.: Sub-15 Feminino" required></div>
            <button class="btn btn-outline">Adicionar</button>
          </form>
        </div>

        <div class="cat-block">
          <h4 class="mini-head" style="color:#FCA5A5;">Zona de perigo</h4>
          <form method="post" class="form-inline" data-confirm="Excluir definitivamente este campeonato, com inscrições e jogos?">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="excluir_campeonato">
            <div class="field"><label for="conf">Para excluir, digite o nome do campeonato</label><input id="conf" name="confirmacao" placeholder="<?= e($c['nome']) ?>" autocomplete="off"></div>
            <button class="btn btn-danger"><?= icone('trash') ?> Excluir campeonato</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
