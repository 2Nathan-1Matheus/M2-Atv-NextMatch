<?php
require 'include/funcoes.php';

$filtros = [
    'modalidade' => get_int('modalidade'),
    'status'     => isset(NM_STATUS[$_GET['status'] ?? '']) ? $_GET['status'] : '',
    'busca'      => trim((string)($_GET['busca'] ?? '')),
];
$campeonatos = buscar_campeonatos($pdo, $filtros);

$titulo = 'Competições';
$nav_ativa = 'competicoes.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Agenda esportiva</span><span class="line"></span></div>
    <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;align-items:flex-end;">
      <div>
        <h2>Competições</h2>
        <p>Campeonatos ativos, categorias e modalidades disponíveis na plataforma.</p>
      </div>
      <div class="btn-row">
        <a href="calendario.php" class="btn btn-outline"><?= icone('calendar') ?> Ver calendário</a>
        <?php if (tipo_usuario() === 'organizador'): ?>
          <a href="dashboard-gerenciador.php#novo" class="btn btn-primary"><?= icone('plus') ?> Criar campeonato</a>
        <?php endif; ?>
      </div>
    </div>

    <form class="filter-bar" method="get" role="search">
      <select name="modalidade" data-autosubmit aria-label="Modalidade">
        <option value="">Todas as modalidades</option>
        <?php foreach (modalidades($pdo) as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $filtros['modalidade'] == $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" data-autosubmit aria-label="Status">
        <option value="">Todos os status</option>
        <?php foreach (NM_STATUS as $valor => [$rotulo]): ?>
          <option value="<?= $valor ?>" <?= $filtros['status'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Buscar por nome ou cidade..." aria-label="Buscar">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </form>

    <?php if ($filtros['modalidade'] || $filtros['status'] || $filtros['busca']): ?>
      <p class="faint" style="font-size:13px;margin:-10px 0 18px;">
        <?= count($campeonatos) ?> resultado(s) · <a href="competicoes.php" style="color:var(--accent);">limpar filtros</a>
      </p>
    <?php endif; ?>

    <div class="grid grid-3">
      <?php foreach ($campeonatos as $c) echo card_campeonato($c); ?>
      <?php if (!$campeonatos): ?>
        <div class="empty-state" style="grid-column:1/-1;"><?= icone('trophy') ?><p>Nenhuma competição encontrada com esses filtros.</p></div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
