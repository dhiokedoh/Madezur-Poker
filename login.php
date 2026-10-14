<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['message' => 'Gunakan formulir login.']);
    exit;
}
startLoginSession();
$user = trim((string) ($_POST['identity'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
if ($user === '' || strlen($user) > 100 || $password === '' || strlen($password) > 4096) {
    http_response_code(422);
    echo json_encode(['message' => 'Isi username dan kata sandi dengan benar.']);
    exit;
}
if (($_SESSION['login_attempts'] ?? 0) >= 10 && time() - ($_SESSION['last_attempt'] ?? 0) < 60) {
    http_response_code(429);
    echo json_encode(['message' => 'Terlalu banyak percobaan. Tunggu satu menit.']);
    exit;
}
try {
    $query = database()->prepare('SELECT id, "user", password, role FROM public."user" WHERE "user" = ? LIMIT 1');
    $query->execute([$user]);
    $account = $query->fetch();
    if (!$account || !password_verify($password, $account['password'])) {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt'] = time();
        http_response_code(401);
        echo json_encode(['message' => 'Username atau kata sandi salah.']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $account['id'], 'user' => $account['user'], 'role' => $account['role']];
    echo json_encode(['redirect' => 'dashboard.php']);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['message' => 'Database belum tersedia. Pastikan PostgreSQL aktif dan tabel user sudah dibuat.']);
}
