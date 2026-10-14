<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/poker-engine.php';
require dirname(__DIR__) . '/db.php';
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function rejects(callable $action, string $message): void { try { $action(); } catch (RuntimeException $e) { return; } throw new RuntimeException($message); }
$deck = pokerDeck();
check(count($deck) === 54 && count(array_unique(array_column($deck, 'id'))) === 54, 'Deck must contain 54 unique cards.');
$byId = array_column($deck, null, 'id');
check($byId['K-clubs']['value'] < $byId['A-clubs']['value'] && $byId['A-clubs']['value'] < $byId['2-clubs']['value'], 'Rank ordering.');
check($byId['joker-black']['value'] > $byId['2-clubs']['value'] && $byId['joker-red']['value'] === $byId['joker-black']['value'], 'Both jokers share the highest rank.');
$jokerGame = ['opening' => false, 'table' => $byId['2-clubs']];
check(pokerLegal($jokerGame, $byId['joker-black']) && pokerLegal($jokerGame, $byId['joker-red']), 'Both jokers beat 2.');
$jokerGame['table'] = $byId['joker-black'];
foreach ($deck as $card) { check(!pokerLegal($jokerGame, $card), 'No card beats a joker.'); }
$legacyRed = $byId['joker-red']; $legacyRed['value'] = 14;
check(!pokerLegal($jokerGame, $legacyRed), 'Existing rooms use equal joker values.');
rejects(fn() => pokerDeck(0), 'Reject zero decks.');
rejects(fn() => pokerDeck(11), 'Reject more than 10 decks.');
rejects(fn() => pokerNew(['A','B','C']), 'Reject fewer than 4 players.');
rejects(fn() => pokerNew(range(1,21)), 'Reject more than 20 players.');
for ($count = 4; $count <= 20; $count++) {
    for ($decks = 1; $decks <= 10; $decks++) {
        $deal = pokerNew(range(1, $count), $decks);
        $allCards = array_merge(...array_column($deal['players'], 'hand'));
        $sizes = array_map(fn($p) => count($p['hand']), $deal['players']);
        check(count($allCards) === 54 * $decks && count(array_unique(array_column($allCards, 'id'))) === 54 * $decks, 'Every card copy has a unique ID.');
        check(max($sizes) - min($sizes) <= 1, 'Fair multi-deck distribution.');
        check(array_column(pokerPlayable($deal), 'id') === ['4-clubs'], 'Only deck 1 opens.');
    }
    for ($iteration = 0; $iteration < 3; $iteration++) {
        $deckCount = [1,2,10][$iteration];
        $game = pokerNew(array_map(fn($i) => 'Player ' . $i, range(1, $count)), $deckCount);
        $sizes = array_map(fn($p) => count($p['hand']), $game['players']);
        check(array_sum($sizes) === 54 * $deckCount && max($sizes) - min($sizes) <= 1, 'Deal all cards fairly.');
        check(array_column(pokerPlayable($game), 'id') === ['4-clubs'], 'Opening requires 4 clubs.');
        rejects(function () use (&$game) { pokerMove($game, null); }, 'Cannot pass opening.');
        for ($turns = 0; !pokerFinished($game) && $turns < 12000; $turns++) {
            $cards = pokerPlayable($game);
            $previous = $game;
            $id = $cards ? $cards[0]['id'] : null;
            pokerMove($game, $id);
            $active = pokerActive($previous);
            $required = count($active) - (in_array($previous['leader'], $active, true) ? 1 : 0);
            if ($id === null && $previous['passes'] === $required - 1) {
                $expectedLeader = in_array($previous['leader'], $active, true) ? $previous['leader'] : pokerNext($previous, $previous['leader']);
                check($game['turn'] === $expectedLeader && $game['table'] === null, 'All passes clear table and choose an active leader.');
                check(count(pokerPlayable($game)) === count($game['players'][$game['turn']]['hand']), 'Leader may play any card.');
            }
            if ($id !== null) { check($game['passes'] === 0, 'New play resets consecutive passes.'); }
            $view = pokerView($game, 0);
            foreach ($view['players'] as $p) { check(!isset($p['hand']), 'Do not expose other players hands.'); }
        }
        check($game['winner'] !== null && !$game['players'][$game['winner']]['hand'], 'Game ends when hand is empty.');
        check(pokerFinished($game) && count($game['ranking']) === $count && count(array_unique($game['ranking'])) === $count, 'Every player gets one final rank.');
        check(array_column(pokerResults($game), 'points') === range($count - 1, 0), 'Points are player count minus rank.');
        check(array_sum(array_column(pokerResults($game), 'points')) === $count * ($count - 1) / 2, 'Correct total awarded points.');
    }
}
echo "170 player/deck combinations and 51 complete games passed, including 20 players and 10 decks.\n";

function http(string $path, array &$client, ?array $body = null): array {
    $headers = $client['cookie'] ? 'Cookie: ' . $client['cookie'] . "\r\n" : '';
    $options = ['ignore_errors' => true, 'header' => $headers, 'timeout' => 10];
    if ($body !== null) { $options['method'] = 'POST'; $options['header'] .= 'Content-Type: application/x-www-form-urlencoded'; $options['content'] = http_build_query($body); }
    $response = file_get_contents('http://localhost/poker/' . $path, false, stream_context_create(['http' => $options]));
    foreach ($http_response_header as $header) { if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) { $client['cookie'] = explode(';', substr($header, 12))[0]; } }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return [(int) $status[1], $response];
}
function api(array &$client, string $action, array $fields = []): array {
    [$status, $response] = http('poker-api.php', $client, ['action' => $action, 'token' => $client['token']] + $fields);
    return [$status, json_decode($response, true, 512, JSON_THROW_ON_ERROR)];
}
$db = database(); $clients = []; $ids = []; $roomCode = null; $roomCodes = [];
try {
    for ($i = 0; $i < 21; $i++) {
        $name = '__test_poker_' . bin2hex(random_bytes(6)); $password = bin2hex(random_bytes(12));
        $query = $db->prepare('INSERT INTO public."user" ("user", password) VALUES (?, ?) RETURNING id');
        $query->execute([$name, password_hash($password, PASSWORD_DEFAULT)]); $ids[] = $query->fetchColumn();
        $client = ['cookie' => '', 'token' => ''];
        [$status] = http('login.php', $client, ['identity' => $name, 'password' => $password]); check($status === 200, 'Test account login.');
        [$status, $page] = http('poker.php', $client); check($status === 200, 'Game page loads.');
        preg_match('/data-token="([a-f0-9]+)"/', $page, $match); $client['token'] = $match[1]; $clients[] = $client;
    }
    foreach ([['playerCount'=>3], ['playerCount'=>21], ['deckCount'=>0], ['deckCount'=>11], ['playerCount'=>'4.5'], ['deckCount'=>'abc']] as $invalid) {
        [$status] = api($clients[0], 'create', $invalid); check($status === 422, 'Reject invalid room settings.');
    }
    [$status, $state] = api($clients[0], 'create', ['playerCount'=>4, 'deckCount'=>2]); check($status === 200 && $state['playerCount'] === 4 && $state['deckCount'] === 2, 'Create configured room.'); $roomCode = $state['room'];
    $roomCodes[] = $roomCode;
    [$status, $lobbyBody] = http('lobby-api.php', $clients[1]); $lobby = json_decode($lobbyBody, true);
    $listed = array_values(array_filter($lobby['rooms'], fn($r) => $r['code'] === $roomCode));
    check($status === 200 && count($listed) === 1 && $listed[0]['online'] === 1 && $listed[0]['joinable'], 'Online room listed without private cards.');
    [$status] = api($clients[0], 'start'); check($status === 422, 'Cannot start alone.');
    foreach ([1,2,3] as $i) { [$status] = api($clients[$i], 'join', ['code' => $roomCode]); check($status === 200, 'Join room.'); }
    [$status] = api($clients[1], 'start'); check($status === 422, 'Non-host cannot start.');
    [$status, $state] = api($clients[0], 'start'); check($status === 200, 'Host starts room.');
    check($state['game']['totalCards'] === 108 && array_sum(array_column($state['game']['players'], 'count')) === 108, 'Two decks dealt through API.');
    [$status] = api($clients[($state['game']['turn'] + 1) % 4], 'pass', ['revision' => $state['revision']]); check($status === 422, 'Reject out of turn.');
    for ($moves = 0; $moves < 2000 && !$state['game']['finished']; $moves++) {
        $seat = $state['game']['turn'];
        [$status, $response] = http('poker-api.php', $clients[$seat]); $state = json_decode($response, true);
        check($status === 200 && $state['game']['seat'] === $seat, 'Correct seat view.');
        foreach ($state['game']['players'] as $p) { check(!isset($p['hand']), 'API hides opponents cards.'); }
        $legal = $state['game']['legal'];
        $revision = $state['revision'];
        [$status] = api($clients[$seat], $legal ? 'play' : 'pass', ['card' => $legal[0] ?? '', 'revision' => $revision - 1]);
        check($status === 422, 'Reject stale revision.');
        if ($legal) { [$status] = api($clients[$seat], 'pass', ['revision' => $revision]); check($status === 422, 'Cannot pass when playable.'); }
        [$status, $state] = api($clients[$seat], $legal ? 'play' : 'pass', ['card' => $legal[0] ?? '', 'revision' => $revision]);
        check($status === 200, 'Legal move accepted.');
    }
    check($state['game']['finished'] && count($state['game']['results']) === 4, 'Online game determines all ranks.');
    check(array_column($state['game']['results'], 'points') === [3,2,1,0], 'Four-player scoring.');
    $query = $db->prepare('SELECT rank, points, user_id FROM poker_results WHERE room_code = ? ORDER BY rank'); $query->execute([$roomCode]); $savedResults = $query->fetchAll();
    check(count($savedResults) === 4 && array_column($savedResults, 'points') === [3,2,1,0], 'Results persisted to PostgreSQL.');
    for ($i = 0; $i < 4; $i++) { [$status] = http('poker-api.php', $clients[$i]); check($status === 200, 'Refresh finished round.'); }
    $query->execute([$roomCode]); check(count($query->fetchAll()) === 4, 'Refreshing does not award points twice.');
    [$status, $lobbyBody] = http('lobby-api.php', $clients[0]); $lobby = json_decode($lobbyBody, true);
    $lastUser = (int)$savedResults[3]['user_id'];
    $lastStats = array_values(array_filter($lobby['leaderboard'], fn($u) => (int)$u['id'] === $lastUser));
    check(count($lastStats) === 1 && $lastStats[0]['losses'] === 1 && $lastStats[0]['points'] === 0, 'Last place records one loss.');
    $winningUser = (int)$savedResults[0]['user_id'];
    $winningStats = array_values(array_filter($lobby['points'], fn($u) => (int)$u['id'] === $winningUser));
    check(count($winningStats) === 1 && $winningStats[0]['wins'] === 1 && $winningStats[0]['points'] === 3, 'Winner has three points and one win.');
    echo "Four-player full rankings, points 3/2/1/0, persistent statistics, and refresh idempotency passed.\n";
    foreach ([0,1,2,3] as $i) { [$status] = api($clients[$i], 'leave'); check($status === 200, 'Leave completed room.'); }
    [$status, $state] = api($clients[0], 'create', ['playerCount'=>20, 'deckCount'=>10]); check($status === 200, 'Create largest room.'); $roomCode = $state['room'];
    $roomCodes[] = $roomCode;
    for ($i = 1; $i < 19; $i++) { [$status] = api($clients[$i], 'join', ['code'=>$roomCode]); check($status === 200, 'Join large room.'); }
    [$status] = api($clients[0], 'start'); check($status === 422, 'Require all selected players.');
    [$status] = api($clients[19], 'join', ['code'=>$roomCode]); check($status === 200, 'Twentieth player joins.');
    [$status] = api($clients[20], 'join', ['code'=>$roomCode]); check($status === 422, 'Reject twenty-first player.');
    [$status, $state] = api($clients[0], 'start'); check($status === 200, 'Start largest room.');
    check(count($state['game']['players']) === 20 && array_sum(array_column($state['game']['players'], 'count')) === 540 && count($state['game']['hand']) === 27, 'Deal 540 cards to 20 players.');
    echo "HTTP tests passed: two-deck complete game; 20-player room, 10 decks, limits, permissions, and 540-card distribution.\n";
} finally {
    foreach ($roomCodes as $savedCode) { $q = $db->prepare('DELETE FROM poker_rooms WHERE code = ?'); $q->execute([$savedCode]); }
    foreach ($ids as $id) { $q = $db->prepare('DELETE FROM public."user" WHERE id = ?'); $q->execute([$id]); }
}
