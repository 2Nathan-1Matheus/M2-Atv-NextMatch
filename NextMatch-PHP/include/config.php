<?php
/* =====================================================
   NextMatch — Configurações
   Ajuste aqui os dados do banco de dados.
   No XAMPP o padrão é usuário "root" e senha vazia.
   ===================================================== */

define('DB_HOST', 'localhost');
define('DB_PORTA', 3306);
define('DB_NOME', 'nextmatch');
define('DB_USUARIO', 'root');
define('DB_SENHA', '');

/* true  = mostra a mensagem completa dos erros (use durante o desenvolvimento)
   false = mostra uma mensagem genérica (use quando publicar o site) */
define('MODO_DEBUG', true);

/* Tamanho máximo das imagens enviadas (em bytes) — 2 MB */
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);

date_default_timezone_set('America/Sao_Paulo');
