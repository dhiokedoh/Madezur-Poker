<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['message' => 'Gunakan formulir pendaftaran.']);
    exit;
}
$user = $_POST['identity'] ?? null;
$password = $_POST['password'] ?? null;
if (!is_string($user) || !is_string($password)) {
    http_response_code(422);
    echo json_encode(['message' => 'Isi username dan kata sandi.']);
    exit;
}
$user = trim($user);
if ($user === '' || strlen($user) > 100 || preg_match('/[\x00-\x1F\x7F]/', $user)) {
    http_response_code(422);
    echo json_encode(['message' => 'Username wajib diisi, maksimal 100 byte, tanpa karakter kontrol.']);
    exit;
}
if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 1 || mb_strlen($password, 'UTF-8') > 4 || str_contains($password, "\0")) {
    http_response_code(422);
    echo json_encode(['message' => 'Kata sandi wajib diisi, maksimal 4 karakter.']);
    exit;
}
try {
    $query = database()->prepare('INSERT INTO public."user" ("user", password) VALUES (?, ?)');
    $query->execute([$user, password_hash($password, PASSWORD_DEFAULT)]);
    http_response_code(201);
    echo json_encode(['message' => 'Akun berhasil dibuat. Silakan masuk dengan username dan kata sandi Anda.']);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23505') {
        http_response_code(409);
        echo json_encode(['message' => 'Username sudah dipakai. Pilih username lain.']);
    } else {
        error_log($exception->getMessage());
        http_response_code(503);
        echo json_encode(['message' => 'Database belum tersedia. Silakan coba lagi.']);
    }
}
