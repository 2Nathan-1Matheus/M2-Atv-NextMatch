<?php
/* =====================================================
   NextMatch — Menu lateral dos painéis
   Usa o tipo do usuário logado. $sidebar_ativa = página atual.
   ===================================================== */

$nm_tipo = tipo_usuario();
$sidebar_ativa = $sidebar_ativa ?? pagina_atual();
$nm_menus = [
    'atleta' => [
        ['dashboard-atleta.php', 'Meu painel', 'home'],
        ['competicoes.php', 'Competições', 'trophy'],
        ['calendario.php', 'Calendário', 'calendar'],
        ['equipes.php', 'Equipes', 'users'],
        ['ranking.php', 'Rankings', 'rank'],
    ],
    'professor' => [
        ['dashboard-professor.php', 'Painel', 'home'],
        ['equipes.php', 'Equipes', 'users'],
        ['competicoes.php', 'Competições', 'trophy'],
        ['calendario.php', 'Calendário', 'calendar'],
        ['ranking.php', 'Rankings', 'rank'],
    ],
    'organizador' => [
        ['dashboard-gerenciador.php', 'Painel', 'home'],
        ['competicoes.php', 'Campeonatos', 'trophy'],
        ['calendario.php', 'Calendário', 'calendar'],
        ['equipes.php', 'Equipes', 'users'],
        ['ranking.php', 'Rankings', 'rank'],
    ],
];
$nm_itens = $nm_menus[$nm_tipo] ?? $nm_menus['atleta'];
$nm_nao_lidas = contar_nao_lidas($pdo, (int)usuario_id());
?>
<aside class="dash-sidebar" aria-label="Menu do painel">
  <div class="side-title"><?= e(NM_TIPOS[$nm_tipo] ?? '') ?></div>
  <?php foreach ($nm_itens as [$nm_href, $nm_rotulo, $nm_ico]): ?>
    <a href="<?= $nm_href ?>" class="<?= $sidebar_ativa === $nm_href ? 'active' : '' ?>"><?= icone($nm_ico) ?><?= $nm_rotulo ?></a>
  <?php endforeach; ?>
  <div class="side-title" style="margin-top:22px;">Conta</div>
  <a href="notificacoes.php" class="<?= $sidebar_ativa === 'notificacoes.php' ? 'active' : '' ?>"><?= icone('bell') ?>Notificações<?php if ($nm_nao_lidas): ?><span class="side-badge"><?= $nm_nao_lidas ?></span><?php endif; ?></a>
  <a href="perfil.php" class="<?= $sidebar_ativa === 'perfil.php' ? 'active' : '' ?>"><?= icone('user') ?>Meu perfil</a>
  <a href="logout.php?token=<?= csrf_token() ?>"><?= icone('logout') ?>Sair</a>
</aside>
