<?php
require 'include/funcoes.php';

/* Modalidades com algum resultado registrado (para as abas) */
$com_dados = $pdo->query("SELECT DISTINCT m.id FROM modalidades m
    WHERE EXISTS (SELECT 1 FROM vw_desempenho d WHERE d.modalidade_id = m.id)
       OR EXISTS (SELECT 1 FROM resultados r JOIN inscricoes i ON i.id = r.inscricao_id JOIN campeonatos c ON c.id = i.campeonato_id WHERE c.modalidade_id = m.id)")
    ->fetchAll(PDO::FETCH_COLUMN);
$com_dados = array_map('intval', $com_dados);
$abas = array_values(array_filter(modalidades($pdo), fn($m) => in_array((int)$m['id'], $com_dados, true)));

$mod_id = get_int('mod', $abas[0]['id'] ?? 1);
$modalidade = null;
foreach (modalidades($pdo) as $m) if ((int)$m['id'] === $mod_id) $modalidade = $m;
if (!$modalidade) redirecionar('ranking.php');

$ranking = calcular_ranking($pdo, $mod_id);
$ranking_provas = calcular_ranking_provas($pdo, $mod_id);
$rotulo = $modalidade['tipo_padrao'] === 'equipe' ? 'Equipe' : 'Atleta';

function link_participante(array $r): string {
    return $r['equipe_id'] ? '<a href="equipes.php?id=' . (int)$r['equipe_id'] . '">' . e($r['nome']) . '</a>' : e($r['nome']);
}
function dot_participante(array $r): string {
    return '<span class="dot">' . ($r['imagem'] ? '<img src="' . e($r['imagem']) . '" alt="">' : e(iniciais($r['nome']))) . '</span>';
}

$titulo = 'Ranking de ' . $modalidade['nome'];
$nav_ativa = 'ranking.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Classificação</span><span class="line"></span></div>
    <div class="section-head">
      <h2>Rankings</h2>
      <p>Classificação geral por modalidade, somando todas as competições da plataforma. Atualiza a cada resultado lançado.</p>
    </div>

    <div class="panel">
      <nav class="tabs" aria-label="Modalidades">
        <?php foreach ($abas as $m): ?>
          <a href="ranking.php?mod=<?= $m['id'] ?>" class="<?= (int)$m['id'] === $mod_id ? 'active' : '' ?>"><?= e($m['nome']) ?></a>
        <?php endforeach; ?>
        <?php if (!in_array($mod_id, $com_dados, true)): ?>
          <a href="ranking.php?mod=<?= $mod_id ?>" class="active"><?= e($modalidade['nome']) ?></a>
        <?php endif; ?>
      </nav>

      <?php if (!$ranking && !$ranking_provas): ?>
        <div class="empty-state"><?= icone('rank') ?><p>Ainda não há resultados de <?= e($modalidade['nome']) ?>.</p></div>
      <?php endif; ?>

      <?php if ($ranking): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Pos.</th><th><?= $rotulo ?></th><th title="Jogos">J</th><th title="Vitórias">V</th><th title="Empates">E</th><th title="Derrotas">D</th><th title="Saldo">Saldo</th><th>Pts</th></tr></thead>
            <tbody>
            <?php foreach ($ranking as $i => $r): $pos = $i + 1; ?>
              <tr>
                <td><span class="rank-pos <?= $pos <= 3 ? 'p' . $pos : '' ?>"><?= $pos ?>º</span></td>
                <td><div class="team-cell"><?= dot_participante($r) ?><?= link_participante($r) ?></div></td>
                <td><?= (int)$r['jogos'] ?></td><td><?= (int)$r['vitorias'] ?></td><td><?= (int)$r['empates'] ?></td><td><?= (int)$r['derrotas'] ?></td>
                <td><?= (int)$r['pro'] - (int)$r['contra'] ?></td>
                <td><b><?= (int)$r['pontos'] ?></b></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="field-hint" style="margin-top:12px;">Vitória = 3 pontos · Empate = 1 ponto. Desempate: vitórias e saldo. Em mata-mata decidido nos pênaltis, o jogo conta como vitória para quem avançou.</p>
      <?php endif; ?>

      <?php if ($ranking_provas): ?>
        <?php if ($ranking): ?><h4 class="mini-head" style="margin-top:26px;">Provas</h4><?php endif; ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Pos.</th><th><?= $rotulo ?></th><th>Provas</th><th>1º</th><th>2º</th><th>3º</th><th>Melhor</th><th>Pts</th></tr></thead>
            <tbody>
            <?php foreach ($ranking_provas as $i => $r): $pos = $i + 1; ?>
              <tr>
                <td><span class="rank-pos <?= $pos <= 3 ? 'p' . $pos : '' ?>"><?= $pos ?>º</span></td>
                <td><div class="team-cell"><?= dot_participante($r) ?><?= link_participante($r) ?></div></td>
                <td><?= (int)$r['provas'] ?></td><td><?= (int)$r['ouro'] ?></td><td><?= (int)$r['prata'] ?></td><td><?= (int)$r['bronze'] ?></td>
                <td><?= (int)$r['melhor'] ?>º</td>
                <td><b><?= (int)$r['pontos'] ?></b></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="field-hint" style="margin-top:12px;">Pontuação por colocação: 1º = 10, 2º = 8, 3º = 6, 4º = 5, 5º = 4, 6º = 3, 7º = 2, 8º = 1.</p>
      <?php endif; ?>
    </div>

    <?php $base = $ranking ?: $ranking_provas; if ($base): ?>
      <div class="panel">
        <div class="panel-head"><h3>Pontos — top <?= min(6, count($base)) ?></h3><span class="chip gray"><?= e($modalidade['nome']) ?></span></div>
        <?= grafico_barras(array_map(fn($r) => ['rotulo' => $r['nome'], 'valor' => (int)$r['pontos'], 'link' => $r['equipe_id'] ? 'equipes.php?id=' . $r['equipe_id'] : null], array_slice($base, 0, 6)), 'pts', 'Pontos no ranking de ' . $modalidade['nome']) ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require 'include/footer.php'; ?>
