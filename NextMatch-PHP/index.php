<?php
require 'include/funcoes.php';

$destaques = buscar_campeonatos($pdo, ['ativos' => true], 3);
$noticias = $pdo->query('SELECT * FROM noticias ORDER BY publicado_em DESC LIMIT 3')->fetchAll();

$numeros = $pdo->query("SELECT
    (SELECT COUNT(*) FROM equipes) AS equipes,
    (SELECT COUNT(*) FROM campeonatos) AS campeonatos,
    (SELECT COUNT(*) FROM partidas WHERE status = 'finalizada' AND inscricao_a_id IS NOT NULL AND inscricao_b_id IS NOT NULL) AS partidas,
    (SELECT COUNT(*) FROM usuarios WHERE tipo = 'atleta') AS atletas")->fetch();

// Próximas partidas com os dois lados definidos
$proximas = $pdo->query("SELECT p.*, c.nome AS campeonato, m.nome AS modalidade,
        COALESCE(ea.nome, ua.nome) AS nome_a, COALESCE(eb.nome, ub.nome) AS nome_b
      FROM partidas p
      JOIN campeonatos c ON c.id = p.campeonato_id
      JOIN modalidades m ON m.id = c.modalidade_id
      JOIN inscricoes ia ON ia.id = p.inscricao_a_id
      JOIN inscricoes ib ON ib.id = p.inscricao_b_id
 LEFT JOIN equipes ea ON ea.id = ia.equipe_id LEFT JOIN usuarios ua ON ua.id = ia.atleta_id
 LEFT JOIN equipes eb ON eb.id = ib.equipe_id LEFT JOIN usuarios ub ON ub.id = ib.atleta_id
     WHERE p.status = 'agendada'
  ORDER BY p.data_hora IS NULL, p.data_hora
     LIMIT 4")->fetchAll();

$titulo = null;
$nav_ativa = 'index.php';
require 'include/header.php';
?>

<main>

  <!-- Banner (carrossel) -->
  <section class="banner" aria-label="Banners em destaque">
    <button type="button" class="anterior" aria-label="Banner anterior">&#10094;</button>
    <button type="button" class="proximo" aria-label="Próximo banner">&#10095;</button>
  </section>

  <!-- HERO -->
  <section class="hero">
    <div class="container">
      <div class="hero-copy">
        <span class="eyebrow">Plataforma multiesportiva</span>
        <h1>Mais que um jogo, <em>é superação.</em></h1>
        <p>O NextMatch centraliza campeonatos, equipes, inscrições, chaveamentos e rankings em um único lugar — chega de planilha e grupo de WhatsApp para organizar competição.</p>
        <div class="hero-actions">
          <?php if (usuario_logado()): ?>
            <a href="<?= painel_url() ?>" class="btn btn-primary">Ir para o meu painel</a>
          <?php else: ?>
            <a href="login.php?modo=cadastro" class="btn btn-primary">Criar minha conta</a>
          <?php endif; ?>
          <a href="competicoes.php" class="btn btn-outline">Ver competições</a>
        </div>
        <div class="stat-strip">
          <div class="stat"><span class="ico"><?= icone('users') ?></span><div><b><?= (int)$numeros['equipes'] ?></b><span>Equipes cadastradas</span></div></div>
          <div class="stat"><span class="ico"><?= icone('trophy') ?></span><div><b><?= (int)$numeros['campeonatos'] ?></b><span>Competições organizadas</span></div></div>
          <div class="stat"><span class="ico"><?= icone('chart') ?></span><div><b><?= (int)$numeros['partidas'] ?></b><span>Partidas com resultado</span></div></div>
          <div class="stat"><span class="ico"><?= icone('user') ?></span><div><b><?= (int)$numeros['atletas'] ?></b><span>Atletas na plataforma</span></div></div>
        </div>
      </div>

      <div class="next-games">
        <div class="flex-between" style="margin-bottom:6px;">
          <h3>Próximas partidas</h3>
          <a href="calendario.php" class="btn btn-ghost btn-sm">Calendário</a>
        </div>
        <?php if (!$proximas): ?>
          <p class="faint" style="font-size:13px;margin:10px 0 0;">Nenhuma partida agendada no momento.</p>
        <?php endif; ?>
        <?php foreach ($proximas as $p): ?>
          <a class="next-game" href="detalhes-competicao.php?id=<?= (int)$p['campeonato_id'] ?>#chave">
            <div class="ng-meta"><span><?= e($p['modalidade']) ?> · <?= e($p['campeonato']) ?></span></div>
            <div class="ng-teams"><span><?= e($p['nome_a']) ?></span><span class="vs">VS</span><span><?= e($p['nome_b']) ?></span></div>
            <div class="ng-meta"><span><?= e(data_hora_br($p['data_hora'])) ?></span><?php if ($p['local']): ?><span>· <?= e($p['local']) ?></span><?php endif; ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PERFIS -->
  <section class="section">
    <div class="container">
      <div class="bracket-divider"><span class="tag">Feito para cada função</span><span class="line"></span></div>
      <div class="section-head">
        <h2>Um painel para cada perfil</h2>
        <p>Atletas, professores e organizadores têm experiências e ferramentas específicas para suas necessidades dentro da plataforma.</p>
      </div>
      <div class="grid grid-3">
        <div class="card profile-card">
          <div class="ico"><?= icone('user') ?></div>
          <h3>Atleta</h3>
          <p>Acompanhe tudo em um lugar só.</p>
          <ul>
            <li><?= icone('check') ?>Inscreva-se em campeonatos</li>
            <li><?= icone('check') ?>Acompanhe resultados e rankings</li>
            <li><?= icone('check') ?>Veja sua próxima partida</li>
          </ul>
        </div>
        <div class="card profile-card">
          <div class="ico"><?= icone('users') ?></div>
          <h3>Professor / Treinador</h3>
          <p>Gerencie sua equipe com facilidade.</p>
          <ul>
            <li><?= icone('check') ?>Gerencie atletas e equipes</li>
            <li><?= icone('check') ?>Aprove solicitações de entrada</li>
            <li><?= icone('check') ?>Inscreva o time em competições</li>
          </ul>
        </div>
        <div class="card profile-card">
          <div class="ico"><?= icone('trophy') ?></div>
          <h3>Organizador</h3>
          <p>Organize eventos do início ao fim.</p>
          <ul>
            <li><?= icone('check') ?>Crie campeonatos e categorias</li>
            <li><?= icone('check') ?>Gere chaveamentos automáticos</li>
            <li><?= icone('check') ?>Controle inscrições e resultados</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- DESTAQUES -->
  <section class="section" style="background:var(--bg-alt);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;">
        <div>
          <div class="bracket-divider" style="margin-bottom:10px;"><span class="tag">Agenda esportiva</span><span class="line"></span></div>
          <h2 class="mt-0">Competições em destaque</h2>
        </div>
        <a href="competicoes.php" class="btn btn-outline">Ver todas</a>
      </div>
      <div class="grid grid-3">
        <?php foreach ($destaques as $c) echo card_campeonato($c); ?>
      </div>
    </div>
  </section>

  <!-- COMO FUNCIONA -->
  <section class="section">
    <div class="container">
      <div class="bracket-divider"><span class="tag">Como funciona</span><span class="line"></span></div>
      <div class="section-head"><h2>Da inscrição ao pódio, em 4 passos</h2></div>
      <div class="grid grid-4">
        <div class="card">
          <div class="chip" style="margin-bottom:12px;">Passo 1</div>
          <h4>Crie sua conta</h4>
          <p style="font-size:13px;">Escolha seu perfil — atleta, professor ou organizador — e comece em minutos.</p>
        </div>
        <div class="card">
          <div class="chip" style="margin-bottom:12px;">Passo 2</div>
          <h4>Encontre ou crie um campeonato</h4>
          <p style="font-size:13px;">Explore competições abertas ou monte a sua com categorias e regulamento.</p>
        </div>
        <div class="card">
          <div class="chip" style="margin-bottom:12px;">Passo 3</div>
          <h4>Gerencie inscrições</h4>
          <p style="font-size:13px;">Equipes e atletas se inscrevem direto pela plataforma, sem planilhas.</p>
        </div>
        <div class="card">
          <div class="chip" style="margin-bottom:12px;">Passo 4</div>
          <h4>Acompanhe em tempo real</h4>
          <p style="font-size:13px;">Chaveamentos, resultados e rankings atualizados a cada rodada.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- NOTÍCIAS -->
  <section class="section" style="background:var(--bg-alt);border-top:1px solid var(--border);">
    <div class="container">
      <div class="section-head flex-between" style="flex-wrap:wrap;gap:14px;">
        <div>
          <div class="bracket-divider" style="margin-bottom:10px;"><span class="tag">Fique por dentro</span><span class="line"></span></div>
          <h2 class="mt-0">Últimas notícias</h2>
        </div>
        <a href="noticias.php" class="btn btn-outline">Ver todas</a>
      </div>
      <div class="grid grid-3">
        <?php foreach ($noticias as $n) echo card_noticia($n); ?>
      </div>
    </div>
  </section>

  <!-- CTA FINAL -->
  <section class="section">
    <div class="container">
      <div class="card" style="padding:38px 26px;text-align:center;background:linear-gradient(135deg,var(--surface),var(--bg-alt));">
        <h2 style="margin-bottom:8px;">Seu time te espera.</h2>
        <p style="max-width:44ch;margin:0 auto 20px;">Cadastre sua equipe ou crie seu próximo campeonato no NextMatch — competa, treine, evolua, vença.</p>
        <div class="hero-actions" style="justify-content:center;">
          <a href="<?= usuario_logado() ? painel_url() : 'login.php?modo=cadastro' ?>" class="btn btn-primary">Começar agora</a>
          <a href="contato.php" class="btn btn-outline">Falar com a equipe</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require 'include/footer.php'; ?>
