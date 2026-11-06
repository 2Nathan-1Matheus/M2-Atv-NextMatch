<?php
require 'include/funcoes.php';

$id = get_int('id');

if ($id) {
    $st = $pdo->prepare('SELECT n.*, u.nome AS autor FROM noticias n LEFT JOIN usuarios u ON u.id = n.autor_id WHERE n.id = ?');
    $st->execute([$id]);
    $n = $st->fetch();
    if (!$n) { flash('err', 'Notícia não encontrada.'); redirecionar('noticias.php'); }
    $st = $pdo->prepare('SELECT * FROM noticias WHERE id <> ? ORDER BY publicado_em DESC LIMIT 3');
    $st->execute([$id]);
    $outras = $st->fetchAll();

    $titulo = $n['titulo'];
    $descricao = $n['resumo'];
    $nav_ativa = 'noticias.php';
    require 'include/header.php';
    ?>
    <main class="section" style="padding-top:34px;">
      <div class="container">
        <a href="noticias.php" class="faint" style="font-size:13px;">← Todas as notícias</a>
        <article style="margin-top:18px;">
          <span class="chip"><?= e($n['categoria']) ?></span>
          <h1 style="font-size:clamp(26px,5vw,38px);margin:14px 0 10px;max-width:24ch;"><?= e($n['titulo']) ?></h1>
          <p class="faint" style="font-size:13px;margin:0;"><?= e(data_br($n['publicado_em'])) ?><?= $n['autor'] ? ' · por ' . e($n['autor']) : '' ?></p>
          <div class="article-cover"><?= $n['imagem'] ? '<img src="' . e($n['imagem']) . '" alt="">' : icone('news') ?></div>
          <div class="prose">
            <p style="font-size:18px;color:var(--text);"><?= e($n['resumo']) ?></p>
            <?php foreach (preg_split('/\R{2,}/', trim($n['conteudo'])) as $paragrafo): ?>
              <p><?= nl2br(e($paragrafo)) ?></p>
            <?php endforeach; ?>
          </div>
        </article>

        <?php if ($outras): ?>
          <div class="bracket-divider" style="margin-top:46px;"><span class="tag">Leia também</span><span class="line"></span></div>
          <div class="grid grid-3"><?php foreach ($outras as $o) echo card_noticia($o); ?></div>
        <?php endif; ?>
      </div>
    </main>
    <?php
    require 'include/footer.php';
    exit;
}

$categoria = trim((string)($_GET['categoria'] ?? ''));
$categorias = $pdo->query('SELECT DISTINCT categoria FROM noticias ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);
if ($categoria !== '' && in_array($categoria, $categorias, true)) {
    $st = $pdo->prepare('SELECT * FROM noticias WHERE categoria = ? ORDER BY publicado_em DESC');
    $st->execute([$categoria]);
} else {
    $categoria = '';
    $st = $pdo->query('SELECT * FROM noticias ORDER BY publicado_em DESC');
}
$noticias = $st->fetchAll();

$titulo = 'Notícias';
$nav_ativa = 'noticias.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Fique por dentro</span><span class="line"></span></div>
    <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;align-items:flex-end;">
      <div>
        <h2>Notícias</h2>
        <p>Atualizações sobre campeonatos, equipes e novidades da plataforma.</p>
      </div>
      <?php if (tipo_usuario() === 'organizador'): ?>
        <a href="dashboard-gerenciador.php#noticia" class="btn btn-primary"><?= icone('plus') ?> Publicar notícia</a>
      <?php endif; ?>
    </div>

    <nav class="tabs" aria-label="Categorias">
      <a href="noticias.php" class="<?= $categoria === '' ? 'active' : '' ?>">Todas</a>
      <?php foreach ($categorias as $cat): ?>
        <a href="noticias.php?categoria=<?= urlencode($cat) ?>" class="<?= $categoria === $cat ? 'active' : '' ?>"><?= e($cat) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="grid grid-3">
      <?php foreach ($noticias as $n) echo card_noticia($n); ?>
      <?php if (!$noticias): ?><div class="empty-state" style="grid-column:1/-1;"><?= icone('news') ?><p>Nenhuma notícia publicada.</p></div><?php endif; ?>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
