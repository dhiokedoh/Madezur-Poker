<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';
$db = database();
$username = '__password_test_' . bin2hex(random_bytes(8));
function registrationRequest(string $endpoint, string $username, string $password): array {
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query(['identity'=>$username,'password'=>$password]), 'ignore_errors'=>true, 'timeout'=>10]]);
    $body = file_get_contents('http://localhost/poker/' . $endpoint, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return [(int)$status[1], json_decode($body, true)];
}
try {
    foreach (['', '12345'] as $invalid) {
        [$status] = registrationRequest('register.php', $username, $invalid);
        if ($status !== 422) { throw new RuntimeException('Invalid password accepted.'); }
    }
    foreach (['1', '2121', 'éééé'] as $password) {
        [$status] = registrationRequest('register.php', $username, $password);
        if ($status !== 201) { throw new RuntimeException('Valid password rejected.'); }
        $query = $db->prepare('SELECT password FROM public."user" WHERE "user" = ?'); $query->execute([$username]);
        if (!password_verify($password, $query->fetchColumn())) { throw new RuntimeException('Stored hash invalid.'); }
        [$status, $result] = registrationRequest('login.php', $username, $password);
        if ($status !== 200 || ($result['redirect'] ?? '') !== 'dashboard.php') { throw new RuntimeException('Login failed.'); }
        $query = $db->prepare('DELETE FROM public."user" WHERE "user" = ?'); $query->execute([$username]);
    }
    echo "Password lengths 1 and 4, Unicode, stored hashes, login, and rejection of empty/5-character passwords passed.\n";
} finally {
    $query = $db->prepare('DELETE FROM public."user" WHERE "user" = ?'); $query->execute([$username]);
}
