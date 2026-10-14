<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
startLoginSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['message'=>'Silakan login kembali.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['message'=>'Metode tidak didukung.']); exit; }
session_write_close();
try {
    $db = database();
    $query = $db->query("SELECT r.code, r.state, COUNT(p.user_id) AS online, EXISTS (SELECT 1 FROM poker_results results WHERE results.room_code = r.code) AS has_history FROM poker_rooms r JOIN poker_presence p ON p.room_code = r.code AND p.last_seen > NOW() - INTERVAL '30 seconds' GROUP BY r.id ORDER BY r.updated_at DESC LIMIT 50");
    $rooms = [];
    foreach ($query as $row) {
        $state = json_decode($row['state'], true);
        $game = $state['game'];
        $finished = $game !== null && ($game['finished'] ?? ($game['winner'] !== null));
        $capacity = $state['playerCount'] ?? 4;
        $activeMembers = array_values(array_filter($state['members'], fn($member) => !($member['stopped'] ?? false)));
        $rooms[] = ['code'=>$row['code'], 'host'=>$state['members'][0]['name'], 'players'=>count($state['members']), 'capacity'=>$capacity, 'decks'=>$state['deckCount'] ?? 1, 'online'=>(int)$row['online'], 'status'=>$game === null ? 'Menunggu pemain' : ($finished ? 'Ronde selesai' : 'Sedang bermain'), 'joinable'=>$game === null && count($state['members']) < $capacity, 'member'=>in_array((int)$_SESSION['user_id'], array_column($state['members'], 'id'), true)];
        $rooms[array_key_last($rooms)]['hasHistory'] = (bool)$row['has_history'];
        $rooms[array_key_last($rooms)]['players'] = count($activeMembers);
        $rooms[array_key_last($rooms)]['member'] = in_array((int)$_SESSION['user_id'], array_column($activeMembers, 'id'), true);
        if ($state['halted'] ?? false) {
            $rooms[array_key_last($rooms)]['status'] = 'Permainan dihentikan';
            $rooms[array_key_last($rooms)]['joinable'] = false;
        }
    }
    $statsSql = 'SELECT u.id, u."user" AS username, COALESCE(SUM(r.points), 0)::int AS points, COUNT(r.round_id)::int AS games, COUNT(r.round_id) FILTER (WHERE r.rank = 1)::int AS wins, COUNT(r.round_id) FILTER (WHERE r.rank = r.player_count)::int AS losses FROM public."user" u LEFT JOIN poker_results r ON r.user_id = u.id GROUP BY u.id';
    $leaderboard = $db->query($statsSql . ' ORDER BY losses DESC, games DESC, username ASC LIMIT 50')->fetchAll();
    $points = $db->query($statsSql . ' ORDER BY points DESC, wins DESC, username ASC LIMIT 50')->fetchAll();
    $histories = $db->query('SELECT room_code AS code, COUNT(DISTINCT round_id)::int AS rounds FROM poker_results GROUP BY room_code ORDER BY MAX(played_at) DESC, room_code LIMIT 50')->fetchAll();
    echo json_encode(['rooms'=>$rooms, 'histories'=>$histories, 'leaderboard'=>$leaderboard, 'points'=>$points], JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    error_log($exception->getMessage()); http_response_code(503);
    echo json_encode(['message'=>'Data ruang dan peringkat belum tersedia.']);
}
