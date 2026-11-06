<?php
require 'include/funcoes.php';

$assuntos = ['Dúvida sobre cadastro', 'Suporte técnico', 'Esqueci minha senha', 'Parceria / organização de evento', 'Outro'];
$eu = usuario_logado();
$dados = ['nome' => $eu['nome'] ?? '', 'email' => $eu['email'] ?? '', 'assunto' => $assuntos[0], 'mensagem' => ''];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    foreach (array_keys($dados) as $k) $dados[$k] = post($k);
    if (mb_strlen($dados['nome']) < 2) $erros[] = 'Informe seu nome.';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
    if (!in_array($dados['assunto'], $assuntos, true)) $erros[] = 'Escolha um assunto.';
    if (mb_strlen($dados['mensagem']) < 10) $erros[] = 'Escreva uma mensagem com pelo menos 10 caracteres.';
    if (mb_strlen($dados['mensagem']) > 3000) $erros[] = 'A mensagem pode ter no máximo 3000 caracteres.';
    // armadilha anti-spam: campo invisível que só robôs preenchem
    if (post('site') !== '') $erros[] = 'Não foi possível enviar.';

    if (!$erros) {
        $st = $pdo->prepare('INSERT INTO contatos (nome, email, assunto, mensagem) VALUES (?, ?, ?, ?)');
        $st->execute([$dados['nome'], $dados['email'], $dados['assunto'], $dados['mensagem']]);
        flash('ok', 'Mensagem enviada! Nossa equipe responde em até 2 dias úteis.');
        redirecionar('contato.php');
    }
}

$titulo = 'Fale conosco';
$nav_ativa = 'contato.php';
require 'include/header.php';
?>

<main class="section" style="padding-top:34px;">
  <div class="container">
    <div class="bracket-divider"><span class="tag">Suporte</span><span class="line"></span></div>
    <div class="section-head">
      <h2>Fale conosco</h2>
      <p>Dúvidas, sugestões ou suporte técnico — nossa equipe responde em até 2 dias úteis.</p>
    </div>
    <div class="grid grid-2" style="align-items:start;">
      <div class="panel">
        <?php if ($erros): ?><div class="form-msg show err" role="alert"><?= implode('<br>', array_map('e', $erros)) ?></div><?php endif; ?>
        <form method="post" novalidate>
          <?= csrf_campo() ?>
          <div style="position:absolute;left:-9999px;" aria-hidden="true"><label>Não preencha <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
          <div class="field-row">
            <div class="field"><label for="nome">Nome</label><input id="nome" name="nome" type="text" value="<?= e($dados['nome']) ?>" placeholder="Seu nome" required></div>
            <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($dados['email']) ?>" placeholder="seuemail@exemplo.com" required></div>
          </div>
          <div class="field"><label for="assunto">Assunto</label>
            <select id="assunto" name="assunto">
              <?php foreach ($assuntos as $a): ?><option <?= $dados['assunto'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="mensagem">Mensagem</label><textarea id="mensagem" name="mensagem" placeholder="Como podemos ajudar?" required maxlength="3000"><?= e($dados['mensagem']) ?></textarea></div>
          <button type="submit" class="btn btn-primary">Enviar mensagem</button>
        </form>
      </div>
      <div class="panel">
        <h3 style="margin-top:0;">Outros canais</h3>
        <div class="list-row"><div class="thumb"><?= icone('phone') ?></div><div class="info"><b>Telefone / WhatsApp</b><span>(11) 4000-1234</span></div></div>
        <div class="list-row"><div class="thumb"><?= icone('mail') ?></div><div class="info"><b>E-mail</b><span>suporte@nextmatch.com.br</span></div></div>
        <div class="list-row"><div class="thumb"><?= icone('pin') ?></div><div class="info"><b>Sede</b><span>São Paulo/SP — Brasil</span></div></div>
        <h4 style="margin:20px 0 10px;font-size:13px;">Redes sociais</h4>
        <div class="social-row">
          <a href="#" aria-label="Instagram"><?= icone('instagram') ?></a>
          <a href="#" aria-label="WhatsApp"><?= icone('whatsapp') ?></a>
          <a href="#" aria-label="YouTube"><?= icone('youtube') ?></a>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require 'include/footer.php'; ?>
