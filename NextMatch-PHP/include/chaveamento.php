<?php
/* =====================================================
   NextMatch — Chaveamento automático
   - Mata-mata: sorteia os participantes, completa a chave até a
     próxima potência de 2 (quem fica sem adversário avança direto)
     e cria todas as fases até a final.
   - Pontos corridos: todos contra todos (método do círculo).
   - Registrar resultado: atualiza a partida e, no mata-mata,
     leva o vencedor para a próxima fase.
   ===================================================== */

/** Gera as partidas de uma categoria. Retorna mensagem de erro ou null se deu certo. */
function gerar_chaveamento(PDO $pdo, array $campeonato, int $categoria_id): ?string {
    if ($campeonato['formato'] === 'classificacao') return 'Este campeonato é por classificação (provas) e não usa chaveamento.';

    $st = $pdo->prepare("SELECT id FROM inscricoes WHERE categoria_id = ? AND campeonato_id = ? AND status = 'aprovada'");
    $st->execute([$categoria_id, $campeonato['id']]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) < 2) return 'São necessários pelo menos 2 participantes aprovados nesta categoria.';

    shuffle($ids); // sorteio

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM partidas WHERE categoria_id = ?')->execute([$categoria_id]);
        $inserir = $pdo->prepare('INSERT INTO partidas (campeonato_id, categoria_id, rodada, posicao, inscricao_a_id, inscricao_b_id, data_hora, local)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $inicio = strtotime($campeonato['data_inicio'] . ' 09:00:00');
        $local = $campeonato['local'];

        if ($campeonato['formato'] === 'mata_mata') {
            $n = count($ids);
            $tamanho = 1;
            while ($tamanho < $n) $tamanho *= 2;
            $rodadas = (int)log($tamanho, 2);
            $metade = $tamanho / 2;

            // 1ª fase: A = primeiros sorteados; B = restantes (vazio = avança direto)
            for ($pos = 1; $pos <= $metade; $pos++) {
                $a = $ids[$pos - 1];
                $b = $ids[$pos - 1 + $metade] ?? null;
                $inserir->execute([$campeonato['id'], $categoria_id, 1, $pos, $a, $b, date('Y-m-d H:i:s', $inicio + ($pos - 1) * 5400), $local]);
            }
            // fases seguintes vazias, uma por semana
            for ($r = 2; $r <= $rodadas; $r++) {
                $jogos = $tamanho / (2 ** $r);
                for ($pos = 1; $pos <= $jogos; $pos++) {
                    $data = $inicio + ($r - 1) * 7 * 86400 + ($pos - 1) * 5400;
                    $inserir->execute([$campeonato['id'], $categoria_id, $r, $pos, null, null, date('Y-m-d H:i:s', $data), $local]);
                }
            }
            // quem não tem adversário avança direto
            $st = $pdo->prepare('SELECT * FROM partidas WHERE categoria_id = ? AND rodada = 1 AND inscricao_b_id IS NULL');
            $st->execute([$categoria_id]);
            foreach ($st->fetchAll() as $partida) {
                $pdo->prepare("UPDATE partidas SET vencedor_id = inscricao_a_id, status = 'finalizada' WHERE id = ?")->execute([$partida['id']]);
                $partida['vencedor_id'] = $partida['inscricao_a_id'];
                avancar_vencedor($pdo, $partida);
            }
        } else {
            // pontos corridos — método do círculo
            $lista = $ids;
            if (count($lista) % 2) $lista[] = null; // folga
            $n = count($lista);
            for ($r = 1; $r < $n; $r++) {
                $pos = 0;
                for ($i = 0; $i < $n / 2; $i++) {
                    $a = $lista[$i];
                    $b = $lista[$n - 1 - $i];
                    if ($a === null || $b === null) continue;
                    if ($r % 2 === 0) [$a, $b] = [$b, $a]; // alterna mando
                    $pos++;
                    $data = $inicio + ($r - 1) * 7 * 86400 + ($pos - 1) * 5400;
                    $inserir->execute([$campeonato['id'], $categoria_id, $r, $pos, $a, $b, date('Y-m-d H:i:s', $data), $local]);
                }
                // gira todos menos o primeiro
                $fixo = array_shift($lista);
                array_unshift($lista, array_pop($lista));
                array_unshift($lista, $fixo);
            }
        }
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }
    return null;
}

/** No mata-mata, coloca o vencedor na partida da fase seguinte */
function avancar_vencedor(PDO $pdo, array $partida): void {
    $proxima_rodada = (int)$partida['rodada'] + 1;
    $proxima_pos = (int)ceil($partida['posicao'] / 2);
    $lado = ($partida['posicao'] % 2 === 1) ? 'inscricao_a_id' : 'inscricao_b_id';
    $st = $pdo->prepare("UPDATE partidas SET $lado = ? WHERE categoria_id = ? AND rodada = ? AND posicao = ? AND status = 'agendada'");
    $st->execute([$partida['vencedor_id'], $partida['categoria_id'], $proxima_rodada, $proxima_pos]);
}

/**
 * Lança (ou corrige) o resultado de uma partida.
 * $vencedor_escolhido só é usado em empate no mata-mata (pênaltis / desempate).
 * Retorna mensagem de erro ou null.
 */
function registrar_resultado(PDO $pdo, array $partida, string $formato, int $placar_a, int $placar_b, ?int $vencedor_escolhido): ?string {
    if (!$partida['inscricao_a_id'] || !$partida['inscricao_b_id']) return 'Esta partida ainda não tem os dois participantes definidos.';
    if ($placar_a < 0 || $placar_b < 0) return 'O placar não pode ser negativo.';

    if ($placar_a > $placar_b) $vencedor = (int)$partida['inscricao_a_id'];
    elseif ($placar_b > $placar_a) $vencedor = (int)$partida['inscricao_b_id'];
    else $vencedor = null;

    if ($formato === 'mata_mata') {
        if ($vencedor === null) {
            if (!in_array($vencedor_escolhido, [(int)$partida['inscricao_a_id'], (int)$partida['inscricao_b_id']], true)) {
                return 'No mata-mata não pode haver empate: escolha quem venceu no desempate (pênaltis, tie-break...).';
            }
            $vencedor = $vencedor_escolhido;
        }
        // não deixa mudar o vencedor se a fase seguinte já foi jogada
        $st = $pdo->prepare("SELECT status FROM partidas WHERE categoria_id = ? AND rodada = ? AND posicao = ?");
        $st->execute([$partida['categoria_id'], $partida['rodada'] + 1, (int)ceil($partida['posicao'] / 2)]);
        $prox = $st->fetchColumn();
        if ($prox === 'finalizada' && (int)$partida['vencedor_id'] !== $vencedor) {
            return 'Não é possível trocar o vencedor: a fase seguinte já tem resultado.';
        }
    }

    $st = $pdo->prepare("UPDATE partidas SET placar_a = ?, placar_b = ?, vencedor_id = ?, status = 'finalizada' WHERE id = ?");
    $st->execute([$placar_a, $placar_b, $vencedor, $partida['id']]);

    if ($formato === 'mata_mata') {
        $partida['vencedor_id'] = $vencedor;
        avancar_vencedor($pdo, $partida);
    }
    return null;
}

/** Desenha a chave do mata-mata de uma categoria */
function html_chave(array $partidas, array $participantes, bool $mostrar_datas = true): string {
    if (!$partidas) return '';
    $por_rodada = [];
    foreach ($partidas as $p) $por_rodada[(int)$p['rodada']][] = $p;
    ksort($por_rodada);
    $total = count($por_rodada);
    $html = '<div class="bracket">';
    foreach ($por_rodada as $r => $jogos) {
        $html .= '<div class="bracket-round"><div class="r-title">' . e(nome_rodada($r, $total)) . '</div><div class="r-matches">';
        foreach ($jogos as $j) {
            $bye = $j['status'] === 'finalizada' && (!$j['inscricao_a_id'] || !$j['inscricao_b_id']);
            $html .= '<div class="match' . ($j['status'] === 'finalizada' ? ' done' : '') . '">';
            foreach ([['inscricao_a_id', 'placar_a'], ['inscricao_b_id', 'placar_b']] as [$lado, $placar]) {
                $id = $j[$lado];
                $nome = $id ? ($participantes[$id]['nome'] ?? '—') : ($bye ? 'Avança direto' : 'A definir');
                $venceu = $id && $j['vencedor_id'] && (int)$j['vencedor_id'] === (int)$id;
                $valor = $j['status'] === 'finalizada' && !$bye ? (int)$j[$placar] : '—';
                $html .= '<div class="m-team' . ($venceu ? ' win' : '') . ($id ? '' : ' tbd') . '"><span>' . e($nome) . '</span><span>' . $valor . '</span></div>';
            }
            if ($mostrar_datas && !$bye) {
                $html .= '<div class="m-date">' . e($j['status'] === 'finalizada' ? 'Encerrada · ' . data_br($j['data_hora'], false) : data_hora_br($j['data_hora'])) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }
    return $html . '</div>';
}

/** Lista de jogos (usada em pontos corridos e no painel) */
function html_lista_jogos(array $partidas, array $participantes, int $total_rodadas, string $formato): string {
    $por_rodada = [];
    foreach ($partidas as $p) {
        if (!$p['inscricao_a_id'] || !$p['inscricao_b_id']) continue;
        $por_rodada[(int)$p['rodada']][] = $p;
    }
    ksort($por_rodada);
    $html = '';
    foreach ($por_rodada as $r => $jogos) {
        $html .= '<h4 class="round-title">' . e(nome_rodada($r, $total_rodadas, $formato)) . '</h4>';
        foreach ($jogos as $j) {
            $a = $participantes[$j['inscricao_a_id']]['nome'] ?? '—';
            $b = $participantes[$j['inscricao_b_id']]['nome'] ?? '—';
            $fim = $j['status'] === 'finalizada';
            $placar = $fim ? (int)$j['placar_a'] . ' × ' . (int)$j['placar_b'] : '×';
            $html .= '<div class="game-row">'
                   . '<span class="g-team' . ($fim && (int)$j['vencedor_id'] === (int)$j['inscricao_a_id'] ? ' win' : '') . '">' . e($a) . '</span>'
                   . '<span class="g-score' . ($fim ? '' : ' pending') . '">' . $placar . '</span>'
                   . '<span class="g-team right' . ($fim && (int)$j['vencedor_id'] === (int)$j['inscricao_b_id'] ? ' win' : '') . '">' . e($b) . '</span>'
                   . '<span class="g-date">' . e($fim ? 'Encerrada' : data_hora_br($j['data_hora'])) . '</span>'
                   . '</div>';
        }
    }
    return $html;
}
