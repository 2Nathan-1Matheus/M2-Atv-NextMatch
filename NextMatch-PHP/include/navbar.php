<?php
/* =====================================================
   NextMatch — Menu de navegação principal
   Incluído pelo header.php. A variável $nav_ativa indica
   qual link fica destacado.
   ===================================================== */

$NM_LINKS = [
    'index.php'       => 'Início',
    'competicoes.php' => 'Competições',
    'equipes.php'     => 'Equipes',
    'ranking.php'     => 'Ranking',
    'noticias.php'    => 'Notícias',
    'contato.php'     => 'Contato',
];
$nav_ativa = $nav_ativa ?? pagina_atual();
?>
<nav class="navbar" aria-label="Navegação principal">
  <?php foreach ($NM_LINKS as $nm_href => $nm_rotulo): ?>
    <a href="<?= $nm_href ?>" class="<?= $nav_ativa === $nm_href ? 'active' : '' ?>"<?= $nav_ativa === $nm_href ? ' aria-current="page"' : '' ?>><?= $nm_rotulo ?></a>
  <?php endforeach; ?>
</nav>
