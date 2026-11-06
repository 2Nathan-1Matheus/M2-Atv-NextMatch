<?php
/* =====================================================
   NextMatch — Conexão com o banco de dados (PDO)
   Todas as consultas usam "prepared statements"
   ($pdo->prepare + execute), o que protege contra SQL injection.
   ===================================================== */

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4',
        DB_USUARIO,
        DB_SENHA,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $erro) {
    http_response_code(500);
    $detalhe = MODO_DEBUG ? '<p style="opacity:.7;font-size:13px">' . htmlspecialchars($erro->getMessage()) . '</p>' : '';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>NextMatch — banco de dados indisponível</title></head>'
       . '<body style="background:#0F172A;color:#fff;font-family:system-ui,sans-serif;padding:40px 20px;max-width:640px;margin:0 auto;line-height:1.6">'
       . '<h1 style="color:#F97316">Não foi possível conectar ao banco de dados</h1>'
       . '<p>Verifique se:</p><ol>'
       . '<li>o <b>MySQL</b> está ligado no painel do XAMPP;</li>'
       . '<li>o arquivo <code>database/nextmatch.sql</code> foi importado no phpMyAdmin;</li>'
       . '<li>os dados em <code>include/config.php</code> estão corretos.</li></ol>'
       . $detalhe . '</body></html>';
    exit;
}
