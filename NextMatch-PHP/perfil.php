<?php
require 'include/funcoes.php';
exigir_login();
$meu_id = usuario_id();

$st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$st->execute([$meu_id]);
$u = $st->fetch();
if (!$u) { $_SESSION = []; redirecionar('login.php'); }

$erros = [];
$aba = 'dados';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $acao = post('acao');

    if ($acao === 'dados') {
        $nome = post('nome');
        $email = mb_strtolower(post('email'));
        if (mb_strlen($nome) < 3) $erros[] = 'Informe seu nome completo.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
        if (!$erros) {
            $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
            $st->execute([$email, $meu_id]);
            if ($st->fetch()) $erros[] = 'Esse e-mail já está em uso por outra conta.';
        }
        if (!$erros) {
            $st = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cidade = ?, posicao = ? WHERE id = ?');
            $st->execute([$nome, $email, post('telefone') ?: null, post('cidade') ?: null, $u['tipo'] === 'atleta' ? (post('posicao') ?: null) : null, $meu_id]);
            flash('ok', 'Dados atualizados com sucesso.');
        }
    }

    if ($acao === 'foto') {
        $caminho = salvar_upload('foto', 'perfis', $erro);
        if ($erro) $erros[] = $erro;
        elseif (!$caminho) $erros[] = 'Escolha uma imagem.';
        else {
            apagar_upload($u['foto']);
            $pdo->prepare('UPDATE usuarios SET foto = ? WHERE id = ?')->execute([$caminho, $meu_id]);
            flash('ok', 'Foto de perfil atualizada.');
        }
    }

    if ($acao === 'remover_foto') {
        apagar_upload($u['foto']);
        $pdo->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?')->execute([$meu_id]);
        flash('ok', 'Foto removida.');
    }

    if ($acao === 'senha') {
        $aba = 'senha';
        $nova = (string)($_POST['nova'] ?? '');
        if (!password_verify((string)($_POST['atual'] ?? ''), $u['senha'])) $erros[] = 'A senha atual está incorreta.';
        elseif (strlen($nova) < 8) $erros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
        elseif ($nova !== (string)($_POST['confirmar'] ?? '')) $erros[] = 'A confirmação não confere com a nova senha.';
        else {
            $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $meu_id]);
            session_regenerate_id(true);
            flash('ok', 'Senha alterada com sucesso.');
        }
    }

    if (!$erros) {
        $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$meu_id]);
        iniciar_sessao_usuario($st->fetch());
        redirecionar('perfil.php');
    }
}

$titulo = 'Meu perfil';
$nav_ativa = '';
$sidebar_ativa = 'perfil.php';
require 'include/header.php';
?>

<div class="dash-shell container">
  <?php require 'include/sidebar.php'; ?>
  <main class="dash-main" style="max-width:760px;">
    <div class="dash-head">
      <div><h1>Meu perfil</h1><p>Gerencie suas informações pessoais e de conta.</p></div>
    </div>

    <?php if ($erros): ?><div class="form-msg show err" role="alert"><?= implode('<br>', array_map('e', $erros)) ?></div><?php endif; ?>

    <div class="panel flex gap-16" style="flex-wrap:wrap;">
      <div class="avatar-lg" id="previewFoto"><?= $u['foto'] ? '<img src="' . e($u['foto']) . '" alt="Sua foto">' : e(iniciais($u['nome'])) ?></div>
      <div style="flex:1;min-width:200px;">
        <b style="font-family:var(--font-display);font-size:18px;display:block;"><?= e($u['nome']) ?></b>
        <span class="chip" style="margin:6px 0 12px;"><?= e(NM_TIPOS[$u['tipo']]) ?></span>
        <form method="post" enctype="multipart/form-data" class="btn-row">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="foto">
          <label for="foto" class="btn btn-outline btn-sm" style="cursor:pointer;">Escolher foto</label>
          <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="previewFoto" style="position:absolute;opacity:0;width:1px;height:1px;">
          <button type="submit" class="btn btn-primary btn-sm">Salvar foto</button>
        </form>
        <?php if ($u['foto']): ?>
          <form method="post" style="margin-top:8px;"><?= csrf_campo() ?><input type="hidden" name="acao" value="remover_foto"><button class="btn btn-ghost btn-sm">Remover foto</button></form>
        <?php endif; ?>
        <p class="field-hint">JPG, PNG ou WEBP de até 2 MB.</p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Dados pessoais</h3></div>
      <form method="post">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="dados">
        <div class="field-row">
          <div class="field"><label for="nome">Nome completo</label><input id="nome" name="nome" type="text" value="<?= e($u['nome']) ?>" required></div>
          <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($u['email']) ?>" required></div>
        </div>
        <div class="field-row">
          <div class="field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" type="tel" value="<?= e($u['telefone']) ?>" placeholder="(00) 00000-0000"></div>
          <div class="field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" type="text" value="<?= e($u['cidade']) ?>" placeholder="Ex.: Campinas/SP"></div>
        </div>
        <?php if ($u['tipo'] === 'atleta'): ?>
          <div class="field"><label for="posicao">Posição / função</label><input id="posicao" name="posicao" type="text" value="<?= e($u['posicao']) ?>" placeholder="Ex.: Atacante, Levantadora, Velocista"></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Salvar alterações</button>
      </form>
    </div>

    <div class="panel" id="senha">
      <div class="panel-head"><h3>Segurança</h3></div>
      <form method="post">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="senha">
        <div class="field"><label for="atual">Senha atual</label><input id="atual" name="atual" type="password" placeholder="••••••••" required autocomplete="current-password"></div>
        <div class="field-row">
          <div class="field"><label for="nova">Nova senha</label><input id="nova" name="nova" type="password" placeholder="Mínimo 8 caracteres" minlength="8" required autocomplete="new-password"></div>
          <div class="field"><label for="confirmar">Confirmar nova senha</label><input id="confirmar" name="confirmar" type="password" placeholder="••••••••" minlength="8" required autocomplete="new-password"></div>
        </div>
        <button type="submit" class="btn btn-outline">Atualizar senha</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>Sessão</h3></div>
      <p style="margin-bottom:14px;">Conta criada em <?= e(data_br($u['criado_em'])) ?>. Ao sair, você volta para a página inicial.</p>
      <a href="logout.php?token=<?= csrf_token() ?>" class="btn btn-danger">Sair da conta</a>
    </div>
  </main>
</div>

<?php require 'include/footer.php'; ?>
