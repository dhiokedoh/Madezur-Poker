<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
startLoginSession();
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { header('Location: index.html'); exit; }
$code = strtoupper(trim(is_string($_GET['room'] ?? null) ? $_GET['room'] : ''));
$rows = [];
$message = '';
$zeroFrom = [];
if (!preg_match('/^[A-F0-9]{8}$/', $code)) { http_response_code(400); $message = 'Kode ruang tidak valid.'; }
else {
    try {
        $query = database()->prepare('SELECT r.round_id, r.user_id, r.rank, r.points, r.played_at, u."user" AS username FROM poker_results r JOIN public."user" u ON u.id = r.user_id WHERE r.room_code = ? ORDER BY r.played_at, r.round_id, r.user_id');
        $query->execute([$code]); $rows = $query->fetchAll();
        $departureQuery = database()->prepare('SELECT user_id, first_zero_round FROM poker_departures WHERE room_code = ?');
        $departureQuery->execute([$code]);
        foreach ($departureQuery as $departure) { $zeroFrom[(int)$departure['user_id']] = (int)$departure['first_zero_round']; }
        if (!$rows) { $message = 'Belum ada ronde selesai di ruang ini. Tabel muncul setelah seluruh peringkat ronde ditentukan.'; }
    } catch (PDOException $exception) { error_log($exception->getMessage()); http_response_code(503); $message = 'Riwayat ruang belum dapat dimuat.'; }
}
$players = []; $rounds = []; $totals = []; $losses = [];
foreach ($rows as $row) {
    $id = (int)$row['user_id'];
    $players[$id] = $row['username'];
    $rounds[$row['round_id']][$id] = (int)$row['points'];
    $totals[$id] = ($totals[$id] ?? 0) + (int)$row['points'];
    $losses[$id] = ($losses[$id] ?? 0) + ((int)$row['points'] === 0 ? 1 : 0);
}
function historyEscape(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Madezur Poker — Hasil Meja <?= historyEscape($code) ?></title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="lobby.css"><link rel="stylesheet" href="widgets.css"><link rel="stylesheet" href="room-history.css"><script src="icons.js" defer></script></head>
<body class="lobby"><header class="lobby-header"><a class="brand" href="dashboard.php"><span class="brand-mark">♠</span> MADEZUR<span class="brand-light">POKER</span></a><a class="logout-button" href="dashboard.php" data-icon="back">Kembali ke lobi</a></header>
<main class="history-main"><div class="eyebrow">HASIL PERMAINAN PER RUANG</div><h2>Meja <?= historyEscape($code) ?></h2><p class="widget-note">Setiap baris adalah satu ronde selesai. Angka pada kolom pemain adalah poin yang didapat pada ronde tersebut.</p>
<?php if ($message !== ''): ?><p class="widget-empty" role="status"><?= historyEscape($message) ?></p>
<?php else: ?><p class="widget-note"><?= count($rounds) ?> ronde selesai · <?= count($players) ?> pemain · Poin 4 pemain: 3, 2, 1, 0.</p>
<div class="history-sheet"><table class="rounds-table"><caption class="sr-only">Poin setiap pemain per ronde untuk meja <?= historyEscape($code) ?></caption><thead><tr><th scope="col">Ronde</th><?php foreach ($players as $name): ?><th scope="col"><?= historyEscape($name) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $roundList = array_values($rounds); for ($i = 0; $i < max(20, count($roundList)); $i++): ?><tr><th scope="row"><?= $i + 1 ?></th><?php foreach ($players as $id => $name): ?><td><?= isset($roundList[$i][$id]) ? $roundList[$i][$id] : (isset($zeroFrom[$id]) && $i + 1 >= $zeroFrom[$id] ? 0 : '') ?></td><?php endforeach; ?></tr><?php endfor; ?>
</tbody><tfoot><tr><th scope="row">Total poin</th><?php foreach ($players as $id => $name): ?><td><?= $totals[$id] ?></td><?php endforeach; ?></tr><tr><th scope="row">Total kalah</th><?php foreach ($players as $id => $name): ?><td><?= $losses[$id] ?></td><?php endforeach; ?></tr></tfoot></table></div>
<p class="widget-note">Baris disediakan sampai ronde 20. Kolom pemain yang berhenti diisi 0 mulai ronde berikutnya; poin ronde yang sudah selesai tetap tersimpan. Total kalah hanya menghitung hasil ronde yang benar-benar dimainkan.</p>
<?php endif; ?></main></body></html>
