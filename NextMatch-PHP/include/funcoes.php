<?php
/* =====================================================
   NextMatch — Funções gerais
   Toda página começa com: require 'include/funcoes.php';
   Isso abre a sessão, conecta ao banco ($pdo) e carrega
   os ícones e as funções abaixo.
   ===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/icones.php';

/* Erros inesperados: mensagem amigável em vez de tela branca */
set_exception_handler(function (Throwable $erro) {
    error_log('[NextMatch] ' . $erro);
    http_response_code(500);
    $detalhe = MODO_DEBUG
        ? '<pre style="white-space:pre-wrap;font-size:12px;opacity:.75">' . htmlspecialchars($erro->getMessage() . "\n" . $erro->getFile() . ':' . $erro->getLine()) . '</pre>'
        : '<p>Ative <code>MODO_DEBUG</code> em <code>include/config.php</code> para ver os detalhes.</p>';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Erro · NextMatch</title></head>'
       . '<body style="background:#0F172A;color:#fff;font-family:system-ui,sans-serif;padding:40px 20px;max-width:680px;margin:0 auto;line-height:1.6">'
       . '<h1 style="color:#F97316">Algo deu errado</h1><p>Não foi possível concluir esta ação. Tente novamente.</p>'
       . $detalhe . '<p><a style="color:#F97316" href="index.php">Voltar para o início</a></p></body></html>';
});

/* ---------------- Utilidades ---------------- */

/** Escapa texto para HTML (proteção contra XSS). Use em TODO dado vindo do banco ou do usuário. */
function e($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

/** Aceita só links internos do tipo "pagina.php?x=1" (evita redirecionar para outro site). */
function url_interna(?string $url, string $padrao = 'index.php'): string {
    $url = (string)$url;
    $partes = parse_url($url);
    if ($partes === false || isset($partes['scheme']) || isset($partes['host'])) return $padrao;
    $arquivo = basename($partes['path'] ?? '');
    if (!preg_match('/^[a-z0-9\-]+\.php$/i', $arquivo)) return $padrao;
    return $arquivo . (isset($partes['query']) ? '?' . $partes['query'] : '');
}

function post(string $campo, string $padrao = ''): string {
    return trim((string)($_POST[$campo] ?? $padrao));
}

function get_int(string $campo, int $padrao = 0): int {
    return isset($_GET[$campo]) ? (int)$_GET[$campo] : $padrao;
}

function pagina_atual(): string {
    return basename($_SERVER['PHP_SELF']);
}

/* ---------------- Mensagens (flash) ---------------- */

function flash(string $tipo, string $mensagem): void {
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function pegar_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------- CSRF (proteção de formulários) ---------------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_campo(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verificar_csrf(): void {
    $enviado = (string)($_POST['csrf'] ?? $_GET['token'] ?? '');
    if (!hash_equals(csrf_token(), $enviado)) {
        flash('err', 'Sua sessão expirou. Tente de novo.');
        redirecionar(pagina_atual() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    }
}

/* ---------------- Autenticação ---------------- */

const NM_TIPOS = ['atleta' => 'Atleta', 'professor' => 'Professor(a)', 'organizador' => 'Organizador(a)'];
const NM_PAINEIS = ['atleta' => 'dashboard-atleta.php', 'professor' => 'dashboard-professor.php', 'organizador' => 'dashboard-gerenciador.php'];

function usuario_logado(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function usuario_id(): ?int {
    return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
}

function tipo_usuario(): ?string {
    return $_SESSION['usuario']['tipo'] ?? null;
}

function painel_url(?string $tipo = null): string {
    $tipo = $tipo ?? tipo_usuario();
    return NM_PAINEIS[$tipo] ?? 'login.php';
}

/** Guarda na sessão os dados básicos do usuário (chamado no login e ao editar o perfil). */
function iniciar_sessao_usuario(array $u): void {
    $_SESSION['usuario'] = [
        'id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email'],
        'tipo' => $u['tipo'], 'foto' => $u['foto'] ?? null,
    ];
}

function exigir_login(): void {
    if (!usuario_logado()) {
        flash('err', 'Entre na sua conta para continuar.');
        $voltar = pagina_atual() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
        redirecionar('login.php?voltar=' . urlencode($voltar));
    }
}

function exigir_tipo(string ...$tipos): void {
    exigir_login();
    if (!in_array(tipo_usuario(), $tipos, true)) {
        flash('err', 'Essa área é exclusiva para ' . implode(' / ', array_map(fn($t) => strtolower(NM_TIPOS[$t]), $tipos)) . '.');
        redirecionar(painel_url());
    }
}

/* ---------------- Datas ---------------- */

const NM_MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
const NM_MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const NM_DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

/** "2026-09-14" → "14 Set 2026" */
function data_br(?string $data, bool $com_ano = true): string {
    if (!$data) return 'A definir';
    $t = strtotime($data);
    return date('d', $t) . ' ' . NM_MESES[(int)date('n', $t) - 1] . ($com_ano ? ' ' . date('Y', $t) : '');
}

/** "2026-11-07 15:00:00" → "Sáb, 07 Nov · 15h00" */
function data_hora_br(?string $dt): string {
    if (!$dt) return 'Data a definir';
    $t = strtotime($dt);
    return NM_DIAS_SEMANA[(int)date('w', $t)] . ', ' . date('d', $t) . ' ' . NM_MESES[(int)date('n', $t) - 1] . ' · ' . date('H\hi', $t);
}

function periodo_br(?string $inicio, ?string $fim): string {
    if (!$fim || $fim === $inicio) return data_br($inicio);
    if (substr($inicio, 0, 7) === substr($fim, 0, 7)) {
        return date('d', strtotime($inicio)) . ' a ' . data_br($fim);
    }
    return data_br($inicio, false) . ' a ' . data_br($fim);
}

/** "há 5 min", "há 2 h", "ontem", "12 Out" */
function tempo_relativo(string $dt): string {
    $seg = time() - strtotime($dt);
    if ($seg < 60) return 'agora';
    if ($seg < 3600) return 'há ' . floor($seg / 60) . ' min';
    if ($seg < 86400) return 'há ' . floor($seg / 3600) . ' h';
    if ($seg < 172800) return 'ontem';
    if ($seg < 604800) return 'há ' . floor($seg / 86400) . ' dias';
    return data_br($dt, date('Y', strtotime($dt)) !== date('Y'));
}

/* ---------------- Exibição ---------------- */

function iniciais(string $nome): string {
    $partes = preg_split('/\s+/', trim($nome)) ?: [''];
    $ini =mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
    return mb_strtoupper($ini);
}

/** Foto (se existir) ou iniciais do nome */
function avatar(string $nome, ?string $foto, string $classe = 'avatar'): string {
    if ($foto) return '<span class="' . $classe . '"><img src="' . e($foto) . '" alt=""></span>';
    return '<span class="' . $classe . '">' . e(iniciais($nome)) . '</span>';
}

const NM_STATUS = [
    'em_breve'           => ['Em breve', 'gray'],
    'inscricoes_abertas' => ['Inscrições abertas', 'green'],
    'em_andamento'       => ['Em andamento', 'yellow'],
    'encerrado'          => ['Encerrado', 'red'],
];
const NM_FORMATOS = [
    'mata_mata'       => 'Mata-mata',
    'pontos_corridos' => 'Pontos corridos',
    'classificacao'   => 'Prova (tempo/nota)',
];

function chip_status(string $status): string {
    [$rotulo, $cor] = NM_STATUS[$status] ?? [$status, 'gray'];
    return '<span class="chip ' . $cor . '">' . e($rotulo) . '</span>';
}

function chip_inscricao(string $status): string {
    $mapa = ['pendente' => ['Aguardando aprovação', 'yellow'], 'aprovada' => ['Aprovada', 'green'], 'recusada' => ['Recusada', 'red'],
             'aprovado' => ['Na equipe', 'green'], 'recusado' => ['Recusado', 'red']];
    [$rotulo, $cor] = $mapa[$status] ?? [$status, 'gray'];
    return '<span class="chip ' . $cor . '">' . e($rotulo) . '</span>';
}

/** Nome da rodada no mata-mata: Final, Semifinal, Quartas... */
function nome_rodada(int $rodada, int $total, string $formato = 'mata_mata'): string {
    if ($formato !== 'mata_mata') return $rodada . 'ª rodada';
    $faltam = $total - $rodada;
    return match ($faltam) {
        0 => 'Final',
        1 => 'Semifinal',
        2 => 'Quartas de final',
        3 => 'Oitavas de final',
        default => $rodada . 'ª fase',
    };
}

/* ---------------- Dados comuns ---------------- */

function modalidades(PDO $pdo): array {
    static $cache = null;
    if ($cache === null) $cache = $pdo->query('SELECT * FROM modalidades ORDER BY id')->fetchAll();
    return $cache;
}

/**
 * Lista campeonatos com modalidade, nº de inscritos e categorias.
 * Filtros aceitos: modalidade (id), status, busca, ativos (bool), organizador (id)
 */
function buscar_campeonatos(PDO $pdo, array $filtros = [], ?int $limite = null): array {
    $sql = "SELECT c.*, m.nome AS modalidade, m.icone,
              (SELECT COUNT(*) FROM inscricoes i WHERE i.campeonato_id = c.id AND i.status = 'aprovada') AS inscritos,
              (SELECT COUNT(*) FROM inscricoes i WHERE i.campeonato_id = c.id AND i.status = 'pendente') AS pendentes,
              (SELECT GROUP_CONCAT(ct.nome ORDER BY ct.id SEPARATOR '|') FROM categorias ct WHERE ct.campeonato_id = c.id) AS categorias
            FROM campeonatos c JOIN modalidades m ON m.id = c.modalidade_id WHERE 1 = 1";
    $p = [];
    if (!empty($filtros['modalidade']))  { $sql .= ' AND c.modalidade_id = ?'; $p[] = (int)$filtros['modalidade']; }
    if (!empty($filtros['status']))      { $sql .= ' AND c.status = ?'; $p[] = $filtros['status']; }
    if (!empty($filtros['busca']))       { $sql .= ' AND (c.nome LIKE ? OR c.cidade LIKE ?)'; $p[] = '%' . $filtros['busca'] . '%'; $p[] = '%' . $filtros['busca'] . '%'; }
    if (!empty($filtros['ativos']))      { $sql .= " AND c.status <> 'encerrado'"; }
    if (!empty($filtros['organizador'])) { $sql .= ' AND c.organizador_id = ?'; $p[] = (int)$filtros['organizador']; }
    $sql .= " ORDER BY FIELD(c.status, 'em_andamento', 'inscricoes_abertas', 'em_breve', 'encerrado'),
              CASE WHEN c.status = 'encerrado' THEN DATEDIFF('2100-01-01', c.data_inicio) ELSE DATEDIFF(c.data_inicio, '2000-01-01') END";
    if ($limite) $sql .= ' LIMIT ' . (int)$limite;
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function card_campeonato(array $c): string {
    $cats = array_filter(explode('|', (string)$c['categorias']));
    $cat_txt = $cats ? $cats[0] . (count($cats) > 1 ? ' +' . (count($cats) - 1) : '') : 'Sem categoria';
    $thumb = $c['imagem']
        ? '<img src="' . e($c['imagem']) . '" alt="" loading="lazy">'
        : icone_modalidade($c['icone']);
    $vagas = $c['tipo_participante'] === 'equipe' ? 'equipes' : 'atletas';
    return '
    <a href="detalhes-competicao.php?id=' . (int)$c['id'] . '" class="comp-card">
      <div class="thumb' . ($c['imagem'] ? ' has-img' : '') . '">' . $thumb . chip_status($c['status']) . '</div>
      <div class="body">
        <span class="chip gray">' . e($c['modalidade'] . ' · ' . $cat_txt) . '</span>
        <h4>' . e($c['nome']) . '</h4>
        <div class="meta">
          <span>' . icone('calendar') . e(data_br($c['data_inicio'])) . '</span>
          <span>' . icone('pin') . e($c['cidade'] ?: $c['local']) . '</span>
        </div>
        <div class="foot">
          <span class="faint" style="font-size:12px;">' . (int)$c['inscritos'] . '/' . (int)$c['vagas'] . ' ' . $vagas . '</span>
          <span class="btn btn-ghost btn-sm">Ver detalhes</span>
        </div>
      </div>
    </a>';
}

function card_noticia(array $n): string {
    $thumb = $n['imagem'] ? '<img src="' . e($n['imagem']) . '" alt="" loading="lazy">' : icone('news');
    return '
    <a href="noticias.php?id=' . (int)$n['id'] . '" class="news-card">
      <div class="thumb' . ($n['imagem'] ? ' has-img' : '') . '">' . $thumb . '</div>
      <div class="body">
        <span class="date">' . e($n['categoria']) . ' · ' . e(data_br($n['publicado_em'])) . '</span>
        <h4>' . e($n['titulo']) . '</h4>
        <p>' . e($n['resumo']) . '</p>
      </div>
    </a>';
}

/** Todas as inscrições de um campeonato, indexadas pelo id, com o nome do participante */
function participantes_do_campeonato(PDO $pdo, int $campeonato_id): array {
    $st = $pdo->prepare("SELECT i.*, COALESCE(eq.nome, u.nome) AS nome, COALESCE(eq.logo, u.foto) AS imagem,
                                COALESCE(eq.cidade, u.cidade) AS cidade, ct.nome AS categoria,
                                (SELECT COUNT(*) FROM equipe_atletas ea WHERE ea.equipe_id = i.equipe_id AND ea.status = 'aprovado') AS atletas
                           FROM inscricoes i
                           JOIN categorias ct ON ct.id = i.categoria_id
                      LEFT JOIN equipes eq ON eq.id = i.equipe_id
                      LEFT JOIN usuarios u ON u.id = i.atleta_id
                          WHERE i.campeonato_id = ?
                       ORDER BY ct.id, FIELD(i.status, 'aprovada', 'pendente', 'recusada'), nome");
    $st->execute([$campeonato_id]);
    $lista = [];
    foreach ($st->fetchAll() as $linha) $lista[$linha['id']] = $linha;
    return $lista;
}

/** IDs dos usuários ligados a uma inscrição (o atleta, ou o professor + atletas da equipe) */
function usuarios_da_inscricao(PDO $pdo, int $inscricao_id): array {
    $st = $pdo->prepare('SELECT i.atleta_id, i.equipe_id, eq.professor_id FROM inscricoes i LEFT JOIN equipes eq ON eq.id = i.equipe_id WHERE i.id = ?');
    $st->execute([$inscricao_id]);
    $i = $st->fetch();
    if (!$i) return [];
    if ($i['atleta_id']) return [(int)$i['atleta_id']];
    $ids = [(int)$i['professor_id']];
    $st = $pdo->prepare("SELECT atleta_id FROM equipe_atletas WHERE equipe_id = ? AND status = 'aprovado'");
    $st->execute([$i['equipe_id']]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[] = (int)$id;
    return array_values(array_unique($ids));
}

/* ---------------- Notificações ---------------- */

function notificar(PDO $pdo, int $usuario_id, string $titulo, string $mensagem, ?string $link = null): void {
    $st = $pdo->prepare('INSERT INTO notificacoes (usuario_id, titulo, mensagem, link) VALUES (?, ?, ?, ?)');
    $st->execute([$usuario_id, mb_substr($titulo, 0, 150), mb_substr($mensagem, 0, 300), $link]);
}

function notificar_inscricao(PDO $pdo, int $inscricao_id, string $titulo, string $mensagem, ?string $link = null): void {
    foreach (usuarios_da_inscricao($pdo, $inscricao_id) as $uid) notificar($pdo, $uid, $titulo, $mensagem, $link);
}

function contar_nao_lidas(PDO $pdo, int $usuario_id): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0');
    $st->execute([$usuario_id]);
    return (int)$st->fetchColumn();
}

/* ---------------- Upload de imagens ---------------- */

/**
 * Salva a imagem enviada em uploads/<pasta>/ e devolve o caminho relativo.
 * Retorna null se nenhum arquivo foi enviado. Em caso de erro, preenche $erro.
 * Só aceita JPG, PNG e WEBP de até 2 MB (verifica o conteúdo real, não só a extensão).
 */
function salvar_upload(string $campo, string $pasta, ?string &$erro = null): ?string {
    $erro = null;
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $arq = $_FILES[$campo];
    if ($arq['error'] !== UPLOAD_ERR_OK) { $erro = 'Não foi possível enviar a imagem (tamanho máximo: 2 MB).'; return null; }
    if ($arq['size'] > UPLOAD_MAX_BYTES) { $erro = 'A imagem deve ter no máximo 2 MB.'; return null; }

    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($arq['tmp_name']);
    // finfo confere o conteúdo real do arquivo; se a extensão fileinfo estiver desligada, usa o getimagesize
    $mime = class_exists('finfo') ? (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']) : ($info['mime'] ?? '');
    if (!isset($tipos[$mime]) || $info === false) { $erro = 'Envie uma imagem JPG, PNG ou WEBP.'; return null; }

    $dir = __DIR__ . '/../uploads/' . $pasta;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nome = bin2hex(random_bytes(10)) . '.' . $tipos[$mime];
    $destino = $dir . '/' . $nome;
    if (!move_uploaded_file($arq['tmp_name'], $destino)) { $erro = 'Falha ao salvar a imagem no servidor.'; return null; }

    reduzir_imagem($destino, $mime, 1200);
    return 'uploads/' . $pasta . '/' . $nome;
}

/** Reduz imagens grandes (mobile first: carregamento rápido). Só funciona se a extensão GD estiver ativa. */
function reduzir_imagem(string $caminho, string $mime, int $max): void {
    if (!function_exists('imagecreatetruecolor')) return;
    [$w, $h] = getimagesize($caminho);
    if ($w <= $max && $h <= $max) return;
    $escala = $max / max($w, $h);
    $nw = (int)round($w * $escala); $nh = (int)round($h * $escala);
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($caminho),
        'image/png'  => @imagecreatefrompng($caminho),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminho) : false,
        default => false,
    };
    if (!$src) return;
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match ($mime) {
        'image/jpeg' => imagejpeg($dst, $caminho, 82),
        'image/png'  => imagepng($dst, $caminho, 7),
        'image/webp' => imagewebp($dst, $caminho, 82),
    };
}

/** Apaga um arquivo enviado anteriormente (só dentro de uploads/) */
function apagar_upload(?string $caminho): void {
    if (!$caminho || !str_starts_with($caminho, 'uploads/') || str_contains($caminho, '..')) return;
    $arquivo = __DIR__ . '/../' . $caminho;
    if (is_file($arquivo)) @unlink($arquivo);
}

/* ---------------- Ranking ---------------- */

/** Ranking por confrontos (vitória 3, empate 1) de uma modalidade */
function calcular_ranking(PDO $pdo, int $modalidade_id): array {
    $st = $pdo->prepare("SELECT i.equipe_id, i.atleta_id, COALESCE(eq.nome, u.nome) AS nome, COALESCE(eq.logo, u.foto) AS imagem,
              COUNT(*) AS jogos,
              SUM(d.resultado = 'V') AS vitorias, SUM(d.resultado = 'E') AS empates, SUM(d.resultado = 'D') AS derrotas,
              SUM(COALESCE(d.pro, 0)) AS pro, SUM(COALESCE(d.contra, 0)) AS contra,
              SUM(CASE d.resultado WHEN 'V' THEN 3 WHEN 'E' THEN 1 ELSE 0 END) AS pontos
         FROM vw_desempenho d
         JOIN inscricoes i ON i.id = d.inscricao_id
    LEFT JOIN equipes eq ON eq.id = i.equipe_id
    LEFT JOIN usuarios u ON u.id = i.atleta_id
        WHERE d.modalidade_id = ?
     GROUP BY i.equipe_id, i.atleta_id, eq.nome, u.nome, eq.logo, u.foto
     ORDER BY pontos DESC, vitorias DESC, (SUM(COALESCE(d.pro, 0)) - SUM(COALESCE(d.contra, 0))) DESC, nome");
    $st->execute([$modalidade_id]);
    return $st->fetchAll();
}

/** Ranking de provas (natação, atletismo...): pontos por colocação 10-8-6-5-4-3-2-1 */
function calcular_ranking_provas(PDO $pdo, int $modalidade_id): array {
    $st = $pdo->prepare("SELECT i.equipe_id, i.atleta_id, COALESCE(eq.nome, u.nome) AS nome, COALESCE(eq.logo, u.foto) AS imagem,
              COUNT(*) AS provas, SUM(r.colocacao = 1) AS ouro, SUM(r.colocacao = 2) AS prata, SUM(r.colocacao = 3) AS bronze,
              MIN(r.colocacao) AS melhor,
              SUM(CASE r.colocacao WHEN 1 THEN 10 WHEN 2 THEN 8 WHEN 3 THEN 6 WHEN 4 THEN 5 WHEN 5 THEN 4 WHEN 6 THEN 3 WHEN 7 THEN 2 WHEN 8 THEN 1 ELSE 0 END) AS pontos
         FROM resultados r
         JOIN inscricoes i ON i.id = r.inscricao_id
         JOIN campeonatos c ON c.id = i.campeonato_id
    LEFT JOIN equipes eq ON eq.id = i.equipe_id
    LEFT JOIN usuarios u ON u.id = i.atleta_id
        WHERE c.modalidade_id = ?
     GROUP BY i.equipe_id, i.atleta_id, eq.nome, u.nome, eq.logo, u.foto
     ORDER BY pontos DESC, ouro DESC, prata DESC, bronze DESC, nome");
    $st->execute([$modalidade_id]);
    return $st->fetchAll();
}

/** Classificação de um campeonato de pontos corridos (a partir das partidas da categoria) */
function classificacao_pontos_corridos(array $partidas, array $participantes, int $categoria_id): array {
    $tabela = [];
    foreach ($participantes as $p) {
        if ($p['categoria_id'] == $categoria_id && $p['status'] === 'aprovada') {
            $tabela[$p['id']] = ['nome' => $p['nome'], 'imagem' => $p['imagem'], 'jogos' => 0, 'vitorias' => 0, 'empates' => 0, 'derrotas' => 0, 'pro' => 0, 'contra' => 0, 'pontos' => 0];
        }
    }
    foreach ($partidas as $m) {
        if ($m['categoria_id'] != $categoria_id || $m['status'] !== 'finalizada' || !$m['inscricao_a_id'] || !$m['inscricao_b_id']) continue;
        foreach ([['inscricao_a_id', 'placar_a', 'placar_b'], ['inscricao_b_id', 'placar_b', 'placar_a']] as [$lado, $pro, $contra]) {
            $id = $m[$lado];
            if (!isset($tabela[$id])) continue;
            $tabela[$id]['jogos']++;
            $tabela[$id]['pro'] += (int)$m[$pro];
            $tabela[$id]['contra'] += (int)$m[$contra];
            if ($m['vencedor_id'] === null) { $tabela[$id]['empates']++; $tabela[$id]['pontos'] += 1; }
            elseif ((int)$m['vencedor_id'] === (int)$id) { $tabela[$id]['vitorias']++; $tabela[$id]['pontos'] += 3; }
            else $tabela[$id]['derrotas']++;
        }
    }
    uasort($tabela, fn($a, $b) => [$b['pontos'], $b['vitorias'], $b['pro'] - $b['contra'], $a['nome']] <=> [$a['pontos'], $a['vitorias'], $a['pro'] - $a['contra'], $b['nome']]);
    return $tabela;
}

/* ---------------- Gráficos (HTML + CSS, sem bibliotecas) ---------------- */

/**
 * Barras horizontais ordenadas (uma série, uma cor).
 * $itens = [['rotulo' => 'Falcões FC', 'valor' => 9, 'link' => '...'], ...]
 */
function grafico_barras(array $itens, string $unidade = '', string $titulo_acessivel = 'Gráfico de barras'): string {
    if (!$itens) return '<p class="faint" style="font-size:13px;margin:0;">Sem dados para exibir ainda.</p>';
    $max = max(array_map(fn($i) => (float)$i['valor'], $itens)) ?: 1;
    $html = '<div class="bar-chart" role="table" aria-label="' . e($titulo_acessivel) . '">';
    foreach ($itens as $i) {
        $pct = max(2, round(((float)$i['valor'] / $max) * 100, 1));
        $valor = e($i['valor']) . ($unidade ? ' ' . e($unidade) : '');
        $rotulo = isset($i['link']) ? '<a href="' . e($i['link']) . '">' . e($i['rotulo']) . '</a>' : e($i['rotulo']);
        $html .= '<div class="bar-row" role="row" title="' . e($i['rotulo']) . ': ' . $valor . '">'
               . '<span class="bar-label" role="rowheader">' . $rotulo . '</span>'
               . '<span class="bar-track" role="presentation"><span class="bar-fill" style="width:' . $pct . '%"></span></span>'
               . '<span class="bar-value" role="cell">' . $valor . '</span></div>';
    }
    return $html . '</div>';
}

/** Barra segmentada de vitórias / empates / derrotas, com rótulos (não depende só da cor) */
function grafico_vde(int $v, int $e, int $d): string {
    $total = $v + $e + $d;
    if ($total === 0) return '<p class="faint" style="font-size:13px;margin:0;">Nenhuma partida disputada ainda.</p>';
    $seg = '';
    foreach ([['v', $v, 'Vitórias'], ['e', $e, 'Empates'], ['d', $d, 'Derrotas']] as [$cls, $n, $rot]) {
        if ($n === 0) continue;
        $pct = round($n / $total * 100, 1);
        $seg .= '<span class="vde-seg vde-' . $cls . '" style="flex:' . $n . '" title="' . $rot . ': ' . $n . ' (' . round($pct) . '%)"></span>';
    }
    $aprov = round(($v * 3 + $e) / ($total * 3) * 100);
    return '<div class="vde">'
         . '<div class="vde-head"><b>' . $aprov . '%</b><span>de aproveitamento em ' . $total . ' partida' . ($total > 1 ? 's' : '') . '</span></div>'
         . '<div class="vde-bar" role="img" aria-label="' . $v . ' vitórias, ' . $e . ' empates, ' . $d . ' derrotas">' . $seg . '</div>'
         . '<div class="vde-legend">'
         . '<span><i class="vde-v"></i>Vitórias <b>' . $v . '</b></span>'
         . '<span><i class="vde-e"></i>Empates <b>' . $e . '</b></span>'
         . '<span><i class="vde-d"></i>Derrotas <b>' . $d . '</b></span>'
         . '</div></div>';
}

/* ---------------- Organizador ---------------- */

/** Aprova ou recusa uma inscrição de um campeonato do organizador. Retorna erro ou null. */
function responder_inscricao(PDO $pdo, int $inscricao_id, string $resposta, int $organizador_id): ?string {
    $st = $pdo->prepare('SELECT i.*, c.nome AS campeonato, c.vagas, c.organizador_id, COALESCE(eq.nome, u.nome) AS nome
                           FROM inscricoes i JOIN campeonatos c ON c.id = i.campeonato_id
                      LEFT JOIN equipes eq ON eq.id = i.equipe_id LEFT JOIN usuarios u ON u.id = i.atleta_id
                          WHERE i.id = ?');
    $st->execute([$inscricao_id]);
    $i = $st->fetch();
    if (!$i || (int)$i['organizador_id'] !== $organizador_id) return 'Inscrição não encontrada.';
    if (!in_array($resposta, ['aprovada', 'recusada'], true)) return 'Resposta inválida.';

    if ($resposta === 'aprovada') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = ? AND status = 'aprovada'");
        $st->execute([$i['campeonato_id']]);
        if ((int)$st->fetchColumn() >= (int)$i['vagas']) return 'Todas as vagas já foram preenchidas. Aumente o número de vagas para aprovar.';
    }
    $pdo->prepare('UPDATE inscricoes SET status = ? WHERE id = ?')->execute([$resposta, $inscricao_id]);
    $texto = $resposta === 'aprovada' ? 'foi aprovada' : 'foi recusada';
    notificar_inscricao($pdo, $inscricao_id, $resposta === 'aprovada' ? 'Inscrição aprovada' : 'Inscrição recusada',
        'A inscrição de ' . $i['nome'] . ' em ' . $i['campeonato'] . ' ' . $texto . '.', 'detalhes-competicao.php?id=' . $i['campeonato_id']);
    return null;
}

/** Notifica todos os participantes aprovados de um campeonato (ou de uma categoria) */
function notificar_participantes(PDO $pdo, int $campeonato_id, string $titulo, string $mensagem, string $link, ?int $categoria_id = null): void {
    $sql = "SELECT id FROM inscricoes WHERE campeonato_id = ? AND status = 'aprovada'" . ($categoria_id ? ' AND categoria_id = ?' : '');
    $st = $pdo->prepare($sql);
    $st->execute($categoria_id ? [$campeonato_id, $categoria_id] : [$campeonato_id]);
    $avisados = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $insc) {
        foreach (usuarios_da_inscricao($pdo, (int)$insc) as $uid) {
            if (isset($avisados[$uid])) continue;
            $avisados[$uid] = true;
            notificar($pdo, $uid, $titulo, $mensagem, $link);
        }
    }
}
