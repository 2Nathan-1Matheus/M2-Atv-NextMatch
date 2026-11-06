<?php
require 'include/funcoes.php';

if (usuario_logado()) redirecionar(painel_url());

$modo = ($_GET['modo'] ?? $_POST['modo'] ?? '') === 'cadastro' ? 'cadastro' : 'entrar';
$voltar = url_interna($_GET['voltar'] ?? $_POST['voltar'] ?? '', '');
$erros = [];
$dados = ['nome' => '', 'email' => '', 'tipo' => 'atleta'];

/* Limite simples de tentativas: 5 erros seguidos = esperar 1 minuto */
$_SESSION['tentativas'] = $_SESSION['tentativas'] ?? ['n' => 0, 'ate' => 0];
$bloqueado = $_SESSION['tentativas']['ate'] > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $dados['email'] = mb_strtolower(post('email'));
    $senha = (string)($_POST['senha'] ?? '');

    if ($modo === 'entrar') {
        if ($bloqueado) {
            $erros[] = 'Muitas tentativas. Aguarde um minuto e tente de novo.';
        } else {
            $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
            $st->execute([$dados['email']]);
            $u = $st->fetch();
            if ($u && password_verify($senha, $u['senha'])) {
                session_regenerate_id(true);
                $_SESSION['tentativas'] = ['n' => 0, 'ate' => 0];
                if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
                }
                iniciar_sessao_usuario($u);
                flash('ok', 'Bem-vindo(a) de volta, ' . explode(' ', $u['nome'])[0] . '!');
                redirecionar($voltar ?: painel_url($u['tipo']));
            }
            $_SESSION['tentativas']['n']++;
            if ($_SESSION['tentativas']['n'] >= 5) $_SESSION['tentativas'] = ['n' => 0, 'ate' => time() + 60];
            $erros[] = 'E-mail ou senha incorretos.';
        }
    } else {
        $dados['nome'] = post('nome');
        $dados['tipo'] = post('tipo');
        $confirmar = (string)($_POST['confirmar'] ?? '');

        if (mb_strlen($dados['nome']) < 3) $erros[] = 'Informe seu nome completo.';
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
        if (strlen($senha) < 8) $erros[] = 'A senha precisa ter pelo menos 8 caracteres.';
        if ($senha !== $confirmar) $erros[] = 'As senhas não conferem.';
        if (!isset(NM_TIPOS[$dados['tipo']])) $erros[] = 'Escolha o tipo de conta.';

        if (!$erros) {
            $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
            $st->execute([$dados['email']]);
            if ($st->fetch()) $erros[] = 'Já existe uma conta com esse e-mail.';
        }

        if (!$erros) {
            $st = $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)');
            $st->execute([$dados['nome'], $dados['email'], password_hash($senha, PASSWORD_DEFAULT), $dados['tipo']]);
            $id = (int)$pdo->lastInsertId();
            $dicas = [
                'atleta' => 'Procure uma competição aberta ou peça para entrar em uma equipe pelo seu painel.',
                'professor' => 'Cadastre sua primeira equipe no painel e adicione seus atletas.',
                'organizador' => 'Crie seu primeiro campeonato pelo painel do organizador.',
            ];
            notificar($pdo, $id, 'Bem-vindo(a) ao NextMatch!', $dicas[$dados['tipo']], painel_url($dados['tipo']));
            session_regenerate_id(true);
            iniciar_sessao_usuario(['id' => $id, 'nome' => $dados['nome'], 'email' => $dados['email'], 'tipo' => $dados['tipo'], 'foto' => null]);
            flash('ok', 'Conta criada! Este é o seu painel.');
            redirecionar($voltar ?: painel_url($dados['tipo']));
        }
    }
}

$titulo = $modo === 'cadastro' ? 'Criar conta' : 'Entrar';
$nav_ativa = '';
$classe_body = '';
require 'include/header.php';
?>

<main class="auth-wrap">
  <div class="container">
    <div class="auth-card">
      <div class="text-center" style="margin-bottom:24px;">
        <img src="assets/img/logo.png" alt="NextMatch" style="height:56px;margin:0 auto 14px;">
        <h1 style="font-size:22px;margin-bottom:4px;"><?= $modo === 'cadastro' ? 'Criar conta' : 'Entrar na plataforma' ?></h1>
        <p style="margin:0;"><?= $modo === 'cadastro' ? 'Escolha seu perfil para começar' : 'Use o e-mail e a senha da sua conta' ?></p>
      </div>

      <form method="post" action="login.php<?= $voltar ? '?voltar=' . urlencode($voltar) : '' ?>" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="modo" value="<?= $modo ?>">

        <?php if ($modo === 'cadastro'): ?>
          <div class="role-tabs" role="radiogroup" aria-label="Tipo de conta">
            <?php foreach (['atleta' => ['user', 'Atleta'], 'professor' => ['users', 'Professor'], 'organizador' => ['trophy', 'Organizador']] as $valor => [$ico, $rotulo]): ?>
              <input type="radio" name="tipo" id="tipo-<?= $valor ?>" value="<?= $valor ?>" <?= $dados['tipo'] === $valor ? 'checked' : '' ?>>
              <label for="tipo-<?= $valor ?>"><?= icone($ico) ?><span><?= $rotulo ?></span></label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <?php if ($erros): ?>
            <div class="form-msg show err" role="alert"><?= implode('<br>', array_map('e', $erros)) ?></div>
          <?php endif; ?>

          <?php if ($modo === 'cadastro'): ?>
            <div class="field">
              <label for="nome">Nome completo</label>
              <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" placeholder="Seu nome" required autocomplete="name">
            </div>
          <?php endif; ?>
          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" placeholder="seuemail@exemplo.com" required autocomplete="email">
          </div>
          <div class="field">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" placeholder="••••••••" required
                   <?= $modo === 'cadastro' ? 'minlength="8" autocomplete="new-password"' : 'autocomplete="current-password"' ?>>
            <?php if ($modo === 'cadastro'): ?><p class="field-hint">Mínimo de 8 caracteres. Sua senha é guardada criptografada.</p><?php endif; ?>
          </div>
          <?php if ($modo === 'cadastro'): ?>
            <div class="field">
              <label for="confirmar">Confirmar senha</label>
              <input type="password" id="confirmar" name="confirmar" placeholder="••••••••" required minlength="8" autocomplete="new-password">
            </div>
          <?php else: ?>
            <div style="margin:-4px 0 18px;text-align:right;">
              <a href="contato.php" style="font-size:13px;color:var(--accent);">Esqueceu a senha? Fale com o suporte</a>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary btn-block"><?= $modo === 'cadastro' ? 'Criar conta' : 'Entrar' ?></button>

          <?php if ($modo === 'entrar'): ?>
            <details class="demo">
              <summary>Contas de demonstração</summary>
              <table>
                <tr><td>Atleta</td><td><code>atleta@nextmatch.com</code></td></tr>
                <tr><td>Professor</td><td><code>professor@nextmatch.com</code></td></tr>
                <tr><td>Organizador</td><td><code>organizador@nextmatch.com</code></td></tr>
                <tr><td>Senha</td><td><code>nextmatch123</code></td></tr>
              </table>
            </details>
          <?php endif; ?>
        </div>
      </form>

      <p class="auth-switch">
        <?php $q = $voltar ? '&voltar=' . urlencode($voltar) : ''; ?>
        <?php if ($modo === 'cadastro'): ?>
          Já tem conta? <a href="login.php?modo=entrar<?= $q ?>">Entrar</a>
        <?php else: ?>
          Ainda não tem conta? <a href="login.php?modo=cadastro<?= $q ?>">Cadastre-se</a>
        <?php endif; ?>
      </p>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
