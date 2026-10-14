<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/poker-engine.php';
require __DIR__ . '/poker-room.php';
startLoginSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['message' => 'Silakan login kembali.']); exit; }
$db = null;
try {
    $db = database();
    $userId = (int) $_SESSION['user_id'];
    $member = ['id' => $userId, 'name' => $_SESSION['user']];
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';
    if (!$post && $_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); throw new RuntimeException('Metode tidak didukung.'); }
    if ($post && (!is_string($_POST['token'] ?? null) || empty($_SESSION['poker_token']) || !hash_equals($_SESSION['poker_token'], $_POST['token']))) { http_response_code(403); throw new RuntimeException('Permintaan tidak valid. Muat ulang halaman.'); }
    $action = $post ? ($_POST['action'] ?? '') : 'state';
    $code = $_SESSION['poker_room'] ?? null;
    if ($action === 'create') {
        if ($code) { throw new RuntimeException('Anda masih berada di ruang. Keluar dari ruang terlebih dahulu.'); }
        $playerCount = filter_var($_POST['playerCount'] ?? 4, FILTER_VALIDATE_INT);
        $deckCount = filter_var($_POST['deckCount'] ?? 1, FILTER_VALIDATE_INT);
        if ($playerCount === false || $playerCount < 4 || $playerCount > 20) { throw new RuntimeException('Pilih jumlah pemain 4–20.'); }
        if ($deckCount === false || $deckCount < 1 || $deckCount > 10) { throw new RuntimeException('Pilih jumlah deck 1–10.'); }
        $code = strtoupper(bin2hex(random_bytes(4)));
        $room = ['members' => [$member], 'playerCount' => $playerCount, 'deckCount' => $deckCount, 'game' => null, 'revision' => 0];
        $query = $db->prepare('INSERT INTO poker_rooms (code, state) VALUES (?, CAST(? AS jsonb))');
        $query->execute([$code, json_encode($room)]);
        $_SESSION['poker_room'] = $code;
    } elseif ($action === 'join') {
        if ($code) { throw new RuntimeException('Anda masih berada di ruang. Keluar dari ruang terlebih dahulu.'); }
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if (!preg_match('/^[A-F0-9]{8}$/', $code)) { throw new RuntimeException('Kode ruang harus terdiri dari 8 karakter.'); }
    }
    if (!$code) { echo json_encode(['room' => null]); exit; }
    $db->beginTransaction();
    $query = $db->prepare('SELECT state FROM poker_rooms WHERE code = ? FOR UPDATE');
    $query->execute([$code]);
    $stored = $query->fetchColumn();
    if (!$stored) { unset($_SESSION['poker_room']); throw new RuntimeException('Ruang tidak ditemukan.'); }
    $room = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    $room['playerCount'] ??= 4;
    $room['deckCount'] ??= 1;
    $seat = array_search($userId, array_column($room['members'], 'id'), true);
    if ($action === 'join' && $seat === false) {
        $departureQuery = $db->prepare('SELECT 1 FROM poker_departures WHERE room_code = ? AND user_id = ?');
        $departureQuery->execute([$code, $userId]);
        if ($departureQuery->fetchColumn()) { throw new RuntimeException('Anda sudah berhenti dari ruang ini. Buat atau gabung ruang lain.'); }
        if ($room['game'] !== null || count($room['members']) >= $room['playerCount']) { throw new RuntimeException('Ruang sudah penuh atau permainan sedang berlangsung.'); }
        $room['members'][] = $member;
        $seat = count($room['members']) - 1;
    }
    if ($seat === false) { unset($_SESSION['poker_room']); throw new RuntimeException('Anda bukan pemain di ruang ini.'); }
    if ($room['members'][$seat]['stopped'] ?? false) { unset($_SESSION['poker_room']); throw new RuntimeException('Anda sudah berhenti dari ruang ini.'); }
    $activeMembers = pokerRoomActive($room);
    $host = $activeMembers[0]['id'] === $userId;
    $leaving = $action === 'stop' || $action === 'leave';
    if ($action === 'start') {
        if (!$host) { throw new RuntimeException('Hanya pembuat ruang yang dapat memulai.'); }
        if ($room['halted'] ?? false) { throw new RuntimeException('Permainan sudah dihentikan.'); }
        if ($room['game'] !== null && pokerFinished($room['game'])) { throw new RuntimeException('Pilih Lanjut atau Berhenti setelah ronde selesai.'); }
        if ($room['game'] !== null && !pokerFinished($room['game'])) { throw new RuntimeException('Permainan masih berlangsung.'); }
        if (count($room['members']) !== $room['playerCount']) { throw new RuntimeException('Menunggu ' . $room['playerCount'] . ' pemain bergabung sebelum memulai.'); }
        $room['game'] = pokerNew(array_column($room['members'], 'name'), $room['deckCount']);
        $room['roundId'] = bin2hex(random_bytes(16));
        $room['scoreRecorded'] = false;
        $room['decisions'] = [];
    } elseif ($action === 'continue' || $action === 'stop' || ($action === 'leave' && $room['game'] !== null && pokerFinished($room['game']))) {
        $choice = $action === 'continue' ? 'continue' : 'stop';
        if ($choice === 'stop') {
            // Preserve completed scores; every following row for this player becomes zero.
            $departureQuery = $db->prepare('INSERT INTO poker_departures (room_code, user_id, first_zero_round) SELECT ?, ?, COUNT(DISTINCT round_id) + 1 FROM poker_results WHERE room_code = ? ON CONFLICT DO NOTHING');
            // Validate the state before recording the departure.
            if ($room['game'] === null || !pokerFinished($room['game'])) { throw new RuntimeException('Berhenti tersedia setelah ronde selesai.'); }
            $departureQuery->execute([$code, $userId, $code]);
        }
        pokerRoomDecision($room, $userId, $choice);
        $seat = array_search($userId, array_column($room['members'], 'id'), true);
    } elseif (in_array($action, ['play', 'pass'], true)) {
        if ($room['game'] === null || $room['game']['turn'] !== $seat) { throw new RuntimeException('Tunggu giliran Anda.'); }
        if ((string) ($room['revision']) !== (string) ($_POST['revision'] ?? '')) { throw new RuntimeException('Giliran telah berubah. Silakan pilih kartu lagi.'); }
        $id = $_POST['card'] ?? null;
        if ($action === 'play' && !is_string($id)) { throw new RuntimeException('Pilih kartu terlebih dahulu.'); }
        pokerMove($room['game'], $action === 'pass' ? null : $id);
    } elseif ($action === 'leave') {
        if ($room['game'] !== null && !pokerFinished($room['game'])) { throw new RuntimeException('Permainan masih berlangsung. Selesaikan permainan sebelum keluar dari ruang.'); }
        array_splice($room['members'], $seat, 1);
        $room['game'] = null;
    } elseif (!in_array($action, ['state', 'create', 'join'], true)) { throw new RuntimeException('Aksi tidak valid.'); }
    $recordedNow = false;
    if ($room['game'] !== null && pokerFinished($room['game']) && isset($room['roundId']) && !($room['scoreRecorded'] ?? false)) {
        $results = pokerResults($room['game']);
        if (count($results) !== count($room['members'])) { throw new RuntimeException('Peringkat ronde belum lengkap.'); }
        $scoreQuery = $db->prepare('INSERT INTO poker_results (round_id, user_id, room_code, player_count, rank, points) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING');
        foreach ($results as $result) {
            $scoreQuery->execute([$room['roundId'], $room['members'][$result['seat']]['id'], $code, count($room['members']), $result['rank'], $result['points']]);
        }
        $room['scoreRecorded'] = true;
        $recordedNow = true;
    }
    if ($post || $recordedNow) {
        $room['revision']++;
        if (!$room['members']) {
            $query = $db->prepare('DELETE FROM poker_rooms WHERE code = ?'); $query->execute([$code]);
        } else {
            $query = $db->prepare('UPDATE poker_rooms SET state = CAST(? AS jsonb), updated_at = NOW() WHERE code = ?');
            $query->execute([json_encode($room), $code]);
        }
    }
    if ($leaving) {
        $query = $db->prepare('DELETE FROM poker_presence WHERE room_code = ? AND user_id = ?');
        $query->execute([$code, $userId]);
    } else {
        $query = $db->prepare('INSERT INTO poker_presence (room_code, user_id) VALUES (?, ?) ON CONFLICT (room_code, user_id) DO UPDATE SET last_seen = NOW()');
        $query->execute([$code, $userId]);
    }
    $db->commit();
    if ($action === 'join') { $_SESSION['poker_room'] = $code; }
    if ($leaving) { unset($_SESSION['poker_room']); echo json_encode(['room' => null, 'message' => ($room['halted'] ?? false) ? 'Anda berhenti. Permainan dihentikan karena pemain kurang dari 4. Riwayat ruang tetap tersedia.' : 'Anda berhenti. Kolom Anda di ronde berikutnya diisi 0.']); exit; }
    echo json_encode(['room' => $code, 'members' => $room['members'], 'activeCount' => count(pokerRoomActive($room)), 'halted' => $room['halted'] ?? false, 'decision' => $room['decisions'][(string)$userId] ?? null, 'decisions' => $room['decisions'] ?? [], 'playerCount' => $room['playerCount'], 'deckCount' => $room['deckCount'], 'host' => $host, 'revision' => $room['revision'], 'game' => $room['game'] === null ? null : pokerView($room['game'], $seat)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($db && $db->inTransaction()) { $db->rollBack(); }
    if ($exception instanceof PDOException) {
        error_log($exception->getMessage()); http_response_code(503);
        echo json_encode(['message' => 'Database permainan belum tersedia.']);
    } else {
        if (http_response_code() === 200) { http_response_code(422); }
        echo json_encode(['message' => $exception->getMessage()]);
    }
}
