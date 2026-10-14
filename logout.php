<?php
require __DIR__ . '/db.php';
startLoginSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['logout_token'] ?? '', (string) ($_POST['token'] ?? '')) || empty($_SESSION['logout_token'])) {
    http_response_code(403);
    exit('Permintaan tidak valid.');
}
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
header('Location: index.html');
