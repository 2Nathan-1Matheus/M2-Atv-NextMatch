<?php
/* =====================================================
   NextMatch — Rodapé, barra inferior (celular) e scripts
   ===================================================== */

$nm_usuario = usuario_logado();
$nav_ativa = $nav_ativa ?? pagina_atual();
$nm_itens = [
    ['index.php', 'Início', 'home'],
    ['competicoes.php', 'Competições', 'trophy'],
    ['ranking.php', 'Ranking', 'rank'],
    ['noticias.php', 'Notícias', 'news'],
    $nm_usuario ? [painel_url(), 'Painel', 'user'] : ['login.php', 'Entrar', 'user'],
];
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.php" class="brand"><img src="assets/img/logo.png" alt="" width="32" height="32"> Next<span>Match</span></a>
        <p>Plataforma multiesportiva para organização e gerenciamento de competições, equipes e atletas.</p>
        <div class="social-row">
          <a href="#" aria-label="Instagram"><?= icone('instagram') ?></a>
          <a href="#" aria-label="WhatsApp"><?= icone('whatsapp') ?></a>
          <a href="#" aria-label="YouTube"><?= icone('youtube') ?></a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Plataforma</h4>
        <a href="competicoes.php">Competições</a>
        <a href="calendario.php">Calendário</a>
        <a href="equipes.php">Equipes</a>
        <a href="ranking.php">Ranking</a>
        <a href="noticias.php">Notícias</a>
      </div>
      <div class="footer-col">
        <h4>Conta</h4>
        <?php if ($nm_usuario): ?>
          <a href="<?= painel_url() ?>">Meu painel</a>
          <a href="perfil.php">Meu perfil</a>
          <a href="notificacoes.php">Notificações</a>
        <?php else: ?>
          <a href="login.php">Entrar</a>
          <a href="login.php?modo=cadastro">Criar conta</a>
        <?php endif; ?>
      </div>
      <div class="footer-col">
        <h4>Suporte</h4>
        <p>suporte@nextmatch.com.br</p>
        <p>(11) 4000-1234</p>
        <a href="contato.php">Fale conosco</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> NextMatch. Todos os direitos reservados.</span>
      <span>Feito para atletas, professores e organizadores.</span>
    </div>
  </div>
</footer>

<nav class="bottom-nav" aria-label="Navegação inferior">
  <?php foreach ($nm_itens as [$nm_href, $nm_rotulo, $nm_ico]): ?>
    <a href="<?= $nm_href ?>" class="<?= $nav_ativa === $nm_href ? 'active' : '' ?>"><?= icone($nm_ico) ?><?= $nm_rotulo ?></a>
  <?php endforeach; ?>
</nav>

<script src="assets/js/main.js"></script>
</body>
</html>
