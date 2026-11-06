<?php
/* =====================================================
   NextMatch — Cabeçalho (início do HTML, logo, menu, ações)
   Antes de incluir, a página pode definir:
     $titulo      → título da aba
     $descricao   → descrição para buscadores
     $nav_ativa   → link do menu destacado (ex.: 'competicoes.php')
     $classe_body → classes do <body> (padrão: 'has-bottom-nav')
   ===================================================== */

$nm_usuario = usuario_logado();
$nm_nao_lidas = $nm_usuario ? contar_nao_lidas($pdo, (int)$nm_usuario['id']) : 0;
$nm_titulo_aba = isset($titulo) ? $titulo . ' · NextMatch' : 'NextMatch — Sua plataforma multiesportiva';
$descricao = $descricao ?? 'NextMatch centraliza a organização de campeonatos, equipes, inscrições e rankings esportivos em um só lugar.';
$classe_body = $classe_body ?? 'has-bottom-nav';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($nm_titulo_aba) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<meta name="theme-color" content="#0F172A">
<link rel="icon" href="assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Roboto:wght@400;500;700&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($classe_body) ?>">

<header class="site-header">
  <div class="container">
    <a href="index.php" class="brand"><img src="assets/img/logo.png" alt="" width="32" height="32"> Next<span>Match</span></a>
    <?php require __DIR__ . '/navbar.php'; ?>
    <div class="header-actions">
      <?php if ($nm_usuario): ?>
        <a href="notificacoes.php" class="icon-btn" aria-label="Notificações<?= $nm_nao_lidas ? " ($nm_nao_lidas não lidas)" : '' ?>">
          <?= icone('bell') ?><?php if ($nm_nao_lidas): ?><span class="badge"><?= $nm_nao_lidas > 9 ? '9+' : $nm_nao_lidas ?></span><?php endif; ?>
        </a>
        <a href="<?= painel_url() ?>" class="user-chip"><?= avatar($nm_usuario['nome'], $nm_usuario['foto']) ?><span class="name"><?= e(explode(' ', $nm_usuario['nome'])[0]) ?></span></a>
      <?php else: ?>
        <a href="login.php" class="btn btn-outline hide-mobile">Entrar</a>
        <a href="login.php?modo=cadastro" class="btn btn-primary">Cadastrar</a>
      <?php endif; ?>
      <button class="nav-toggle" id="nmNavToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="nmMobileMenu"><?= icone('menu') ?></button>
    </div>
  </div>
</header>

<div class="mobile-menu" id="nmMobileMenu">
  <?php foreach ($NM_LINKS as $nm_href => $nm_rotulo): ?>
    <a href="<?= $nm_href ?>" class="<?= $nav_ativa === $nm_href ? 'active' : '' ?>"><?= $nm_rotulo ?> <span aria-hidden="true">›</span></a>
  <?php endforeach; ?>
  <a href="calendario.php" class="<?= $nav_ativa === 'calendario.php' ? 'active' : '' ?>">Calendário <span aria-hidden="true">›</span></a>
  <div class="mm-actions">
    <?php if ($nm_usuario): ?>
      <a href="<?= painel_url() ?>" class="btn btn-primary btn-block">Meu painel</a>
      <a href="perfil.php" class="btn btn-ghost btn-block">Meu perfil</a>
      <a href="logout.php?token=<?= csrf_token() ?>" class="btn btn-danger btn-block">Sair</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary btn-block">Entrar</a>
      <a href="login.php?modo=cadastro" class="btn btn-ghost btn-block">Criar conta</a>
    <?php endif; ?>
  </div>
</div>

<?php $nm_mensagens = pegar_flash(); if ($nm_mensagens): ?>
<div class="toast-stack" role="status" aria-live="polite">
  <?php foreach ($nm_mensagens as $nm_m): ?>
    <div class="toast <?= $nm_m['tipo'] === 'err' ? 'err' : 'ok' ?>">
      <?= icone($nm_m['tipo'] === 'err' ? 'close' : 'check') ?><span><?= e($nm_m['msg']) ?></span>
      <button type="button" class="toast-close" aria-label="Fechar">×</button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
