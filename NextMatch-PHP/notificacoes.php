<?php
require 'include/funcoes.php';
exigir_login();
$meu_id = usuario_id();

/* Abrir uma notificação: marca como lida e vai para o link */
if (isset($_GET['ler'])) {
    $st = $pdo->prepare('SELECT link FROM notificacoes WHERE id = ? AND usuario_id = ?');
    $st->execute([get_int('ler'), $meu_id]);
    $link = $st->fetchColumn();
    $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?')->execute([get_int('ler'), $meu_id]);
    redirecionar($link ? url_interna($link, 'notificacoes.php') : 'notificacoes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    if (post('acao') === 'ler_todas') {
        $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?')->execute([$meu_id]);
        flash('ok', 'Todas as notificações foram marcadas como lidas.');
    } elseif (post('acao') === 'limpar') {
        $pdo->prepare('DELETE FROM notificacoes WHERE usuario_id = ? AND lida = 1')->execute([$meu_id]);
        flash('ok', 'Notificações lidas removidas.');
    }
    redirecionar('notificacoes.php');
}

$st = $pdo->prepare('SELECT * FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC, id DESC LIMIT 60');
$st->execute([$meu_id]);
$notificacoes = $st->fetchAll();
$nao_lidas_total = count(array_filter($notificacoes, fn($n) => !$n['lida']));

$titulo = 'Notificações';
$nav_ativa = '';
$sidebar_ativa = 'notificacoes.php';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-head">
      <div>
        <h1>Notificações</h1>
        <p><?= $nao_lidas_total ? $nao_lidas_total . ' não lida(s)' : 'Você está em dia.' ?></p>
      </div>
      <div class="btn-row">
        <?php if ($nao_lidas_total): ?>
          <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="ler_todas"><button class="btn btn-outline btn-sm"><?= icone('check') ?> Marcar todas como lidas</button></form>
        <?php endif; ?>
        <?php if (count($notificacoes) > $nao_lidas_total): ?>
          <form method="post" data-confirm="Remover as notificações já lidas?"><?= csrf_campo() ?><input type="hidden" name="acao" value="limpar"><button class="btn btn-ghost btn-sm">Limpar lidas</button></form>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <?php if (!$notificacoes): ?>
        <div class="empty-state"><?= icone('bell') ?><p>Nenhuma notificação por aqui.</p></div>
      <?php endif; ?>
      <?php foreach ($notificacoes as $n): ?>
        <a class="notif-row<?= $n['lida'] ? '' : ' unread' ?>" href="notificacoes.php?ler=<?= (int)$n['id'] ?>">
          <span class="dot" aria-hidden="true"></span>
          <span class="n-body"><b><?= e($n['titulo']) ?><?= $n['lida'] ? '' : ' <span class="sr-only">(não lida)</span>' ?></b><p><?= e($n['mensagem']) ?></p></span>
          <span class="n-time"><?= e(tempo_relativo($n['criado_em'])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
