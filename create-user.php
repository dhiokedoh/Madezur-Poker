<?php
// Run from a terminal only; never expose account creation through HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/db.php';
echo 'Username: ';
$user = trim(fgets(STDIN));
echo 'Password (input terlihat di terminal): ';
$password = rtrim(fgets(STDIN), "\r\n");
if ($user === '' || strlen($user) > 100 || !mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 1 || mb_strlen($password, 'UTF-8') > 4 || str_contains($password, "\0")) {
    fwrite(STDERR, "Username wajib diisi (maks. 100 karakter), password wajib diisi (maks. 4 karakter).\n");
    exit(1);
}
try {
    $query = database()->prepare('INSERT INTO public."user" ("user", password) VALUES (?, ?)');
    $query->execute([$user, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Akun berhasil dibuat.\n";
} catch (PDOException $exception) {
    fwrite(STDERR, "Gagal membuat akun. Pastikan database tersedia dan username belum dipakai.\n");
    exit(1);
}
