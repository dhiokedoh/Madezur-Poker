<?php
declare(strict_types=1);
require_once __DIR__ . '/poker-engine.php';

function pokerRoomActive(array $room): array {
    return array_values(array_filter($room['members'], fn($member) => !($member['stopped'] ?? false)));
}

function pokerRoomDecision(array &$room, int $userId, string $choice): void {
    if ($room['game'] === null || !pokerFinished($room['game'])) { throw new RuntimeException('Pilihan tersedia setelah ronde selesai.'); }
    $seat = array_search($userId, array_column($room['members'], 'id'), true);
    if ($seat === false || ($room['members'][$seat]['stopped'] ?? false)) { throw new RuntimeException('Anda sudah berhenti dari ruang ini.'); }
    if ($choice === 'continue' && ($room['halted'] ?? false)) { throw new RuntimeException('Permainan dihentikan karena pemain kurang dari 4.'); }
    $room['decisions'] ??= [];
    $room['decisions'][(string)$userId] = $choice;
    if ($choice === 'stop') { $room['members'][$seat]['stopped'] = true; }
    $active = pokerRoomActive($room);
    if (count($active) < 4) { $room['halted'] = true; return; }
    foreach ($active as $member) { if (($room['decisions'][(string)$member['id']] ?? null) !== 'continue') return; }
    $room['members'] = $active;
    $room['playerCount'] = count($active);
    $room['game'] = pokerNew(array_column($active, 'name'), $room['deckCount']);
    $room['roundId'] = bin2hex(random_bytes(16));
    $room['scoreRecorded'] = false;
    $room['decisions'] = [];
}
