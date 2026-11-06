<?php
require 'include/funcoes.php';

// O link de sair leva um token (?token=...) para que outro site não consiga deslogar o usuário
if (!hash_equals(csrf_token(), (string)($_GET['token'] ?? ''))) {
    redirecionar(usuario_logado() ? painel_url() : 'index.php');
}

$_SESSION = [];
session_regenerate_id(true);
flash('ok', 'Você saiu da sua conta.');
redirecionar('index.php');
