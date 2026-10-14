<?php
declare(strict_types=1);

function pokerDeck(int $deckCount = 1): array {
    if ($deckCount < 1 || $deckCount > 10) { throw new RuntimeException('Pilih 1–10 deck.'); }
    $deck = [];
    foreach (['clubs' => '♣', 'diamonds' => '♦', 'hearts' => '♥', 'spades' => '♠'] as $suit => $symbol) {
        foreach (['3','4','5','6','7','8','9','10','J','Q','K','A','2'] as $value => $rank) {
            $deck[] = ['id' => $rank . '-' . $suit, 'rank' => $rank, 'suit' => $suit, 'symbol' => $symbol, 'value' => $value, 'red' => in_array($suit, ['diamonds','hearts'], true)];
        }
    }
    foreach (['black', 'red'] as $color) {
        $deck[] = ['id' => 'joker-' . $color, 'rank' => 'JOKER', 'suit' => $color, 'symbol' => '★', 'value' => 13, 'red' => $color === 'red'];
    }
    $cards = [];
    for ($number = 1; $number <= $deckCount; $number++) {
        foreach ($deck as $card) {
            $card['deck'] = $number;
            if ($number > 1) { $card['id'] .= '-deck-' . $number; }
            $cards[] = $card;
        }
    }
    return $cards;
}

function pokerNew(array $names, int $deckCount = 1): array {
    if (count($names) < 4 || count($names) > 20) { throw new RuntimeException('Permainan membutuhkan 4–20 pemain.'); }
    $deck = pokerDeck($deckCount);
    shuffle($deck);
    $players = [];
    foreach ($names as $player) { $players[] = ['name' => $player, 'hand' => []]; }
    foreach ($deck as $index => $card) { $players[$index % count($names)]['hand'][] = $card; }
    $turn = 0;
    foreach ($players as $index => &$player) {
        usort($player['hand'], fn($a, $b) => $a['value'] <=> $b['value'] ?: strcmp($a['suit'], $b['suit']));
        foreach ($player['hand'] as $card) { if ($card['id'] === '4-clubs') { $turn = $index; } }
    }
    unset($player);
    return ['players' => $players, 'deckCount' => $deckCount, 'totalCards' => count($deck), 'turn' => $turn, 'opening' => true, 'table' => null, 'leader' => null, 'passes' => 0, 'winner' => null, 'finished' => false, 'ranking' => [], 'log' => [count($deck) . ' kartu dari ' . $deckCount . ' deck dibagikan. Pemegang 4♣ deck 1 memulai permainan.']];
}

function pokerFinished(array $game): bool {
    return $game['finished'] ?? ($game['winner'] !== null);
}

function pokerActive(array $game): array {
    return array_values(array_diff(array_keys($game['players']), $game['ranking'] ?? []));
}

function pokerNext(array $game, int $seat): int {
    $active = pokerActive($game);
    for ($offset = 1; $offset <= count($game['players']); $offset++) {
        $candidate = ($seat + $offset) % count($game['players']);
        if (in_array($candidate, $active, true)) { return $candidate; }
    }
    throw new RuntimeException('Tidak ada pemain aktif.');
}

function pokerResults(array $game): array {
    $results = [];
    foreach ($game['ranking'] ?? [] as $index => $seat) {
        $results[] = ['seat' => $seat, 'name' => $game['players'][$seat]['name'], 'rank' => $index + 1, 'points' => count($game['players']) - $index - 1];
    }
    return $results;
}

function pokerLegal(array $game, array $card): bool {
    if ($game['opening']) { return $card['id'] === '4-clubs'; }
    // Normalize jokers here too so rooms created before this change follow the same rule.
    $value = $card['rank'] === 'JOKER' ? 13 : $card['value'];
    $tableValue = $game['table'] === null ? null : ($game['table']['rank'] === 'JOKER' ? 13 : $game['table']['value']);
    return $tableValue === null || $value > $tableValue;
}

function pokerPlayable(array $game): array {
    return array_values(array_filter($game['players'][$game['turn']]['hand'], fn($card) => pokerLegal($game, $card)));
}

function pokerMove(array &$game, ?string $cardId): void {
    if (pokerFinished($game)) { throw new RuntimeException('Permainan sudah selesai.'); }
    $game['ranking'] ??= [];
    $game['finished'] ??= false;
    $turn = $game['turn'];
    $name = $game['players'][$turn]['name'];
    if ($cardId === null) {
        if (pokerPlayable($game)) { throw new RuntimeException('Anda masih memiliki kartu yang dapat dimainkan.'); }
        $game['passes']++;
        $game['log'][] = $name . ' pass.';
        $active = pokerActive($game);
        $requiredPasses = count($active) - (in_array($game['leader'], $active, true) ? 1 : 0);
        if ($game['passes'] === $requiredPasses) {
            $game['table'] = null;
            $game['passes'] = 0;
            $game['turn'] = in_array($game['leader'], $active, true) ? $game['leader'] : pokerNext($game, $game['leader']);
            $game['log'][] = $game['players'][$game['turn']]['name'] . ' bebas membuka dengan kartu apa saja.';
        } else { $game['turn'] = pokerNext($game, $turn); }
    } else {
        $position = array_search($cardId, array_column($game['players'][$turn]['hand'], 'id'), true);
        if ($position === false || !pokerLegal($game, $game['players'][$turn]['hand'][$position])) { throw new RuntimeException('Kartu tidak valid. Awali dengan 4♣, lalu mainkan angka yang lebih tinggi.'); }
        $card = $game['players'][$turn]['hand'][$position];
        array_splice($game['players'][$turn]['hand'], $position, 1);
        $game['opening'] = false;
        $game['table'] = $card;
        $game['leader'] = $turn;
        $game['passes'] = 0;
        $game['log'][] = $name . ' memainkan ' . $card['rank'] . $card['symbol'] . ($card['rank'] === 'JOKER' ? ($card['red'] ? ' merah' : ' hitam') : '') . '.';
        if (!$game['players'][$turn]['hand']) {
            $game['ranking'][] = $turn;
            $game['winner'] ??= $turn;
            $game['log'][] = $name . ' selesai di peringkat ' . count($game['ranking']) . '.';
            $active = pokerActive($game);
            if (count($active) === 1) {
                $game['ranking'][] = $active[0];
                $game['finished'] = true;
                $game['log'][] = 'Ronde selesai. ' . $game['players'][$active[0]]['name'] . ' di peringkat terakhir.';
            }
        }
        if (!$game['finished']) { $game['turn'] = pokerNext($game, $turn); }
    }
    $game['log'] = array_slice($game['log'], -12);
}

function pokerView(array $game, int $seat): array {
    $finished = pokerFinished($game);
    $legal = !$finished && $game['turn'] === $seat ? array_column(pokerPlayable($game), 'id') : [];
    $game['hand'] = $game['players'][$seat]['hand'];
    foreach ($game['players'] as &$player) { $player['count'] = count($player['hand']); unset($player['hand']); }
    unset($player);
    $game['legal'] = $legal;
    $game['seat'] = $seat;
    $game['finished'] = $finished;
    $game['results'] = pokerResults($game);
    $game['canPass'] = !$finished && $game['turn'] === $seat && !$legal;
    return $game;
}
