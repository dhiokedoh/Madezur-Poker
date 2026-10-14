<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';
$db = database(); $ids = []; $cookie = ''; $code = strtoupper(bin2hex(random_bytes(4)));
function historyCheck(bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); }
function historyHttp(string $path, string &$cookie, ?array $body = null): string {
    $options = ['ignore_errors'=>true, 'timeout'=>10, 'header'=>$cookie === '' ? '' : 'Cookie: ' . $cookie . "\r\n"];
    if ($body !== null) { $options['method']='POST'; $options['header'].='Content-Type: application/x-www-form-urlencoded'; $options['content']=http_build_query($body); }
    $response = file_get_contents('http://localhost/poker/' . $path, false, stream_context_create(['http'=>$options]));
    foreach ($http_response_header as $header) { if (stripos($header,'Set-Cookie: PHPSESSID=') === 0) $cookie=explode(';',substr($header,12))[0]; }
    return $response;
}
try {
    $names = [];
    for ($i=0; $i<4; $i++) {
        $name = '__history_' . bin2hex(random_bytes(5)) . ($i === 0 ? '<test>' : ''); $names[]=$name;
        $query=$db->prepare('INSERT INTO public."user" ("user", password) VALUES (?, ?) RETURNING id'); $query->execute([$name,password_hash('21',PASSWORD_DEFAULT)]); $ids[]=(int)$query->fetchColumn();
    }
    historyHttp('login.php',$cookie,['identity'=>$names[0],'password'=>'21']);
    $query=$db->prepare('INSERT INTO poker_rooms(code,state) VALUES (?,CAST(? AS jsonb))');
    $query->execute([$code,json_encode(['members'=>[['id'=>$ids[0],'name'=>$names[0]]],'game'=>null,'playerCount'=>4,'deckCount'=>1])]);
    $query=$db->prepare('INSERT INTO poker_presence(room_code,user_id) VALUES (?,?)'); $query->execute([$code,$ids[0]]);
    $page=historyHttp('room-history.php?room='.$code,$cookie);
    historyCheck(!str_contains($page,'class="rounds-table"') && str_contains($page,'Belum ada ronde selesai'), 'No table before completion.');
    $lobby=json_decode(historyHttp('lobby-api.php',$cookie),true);
    $room=array_values(array_filter($lobby['rooms'],fn($r)=>$r['code']===$code))[0];
    historyCheck(!$room['hasHistory'], 'Room name has no history before a round completes.');
    foreach ([[3,2,1,0],[0,1,2,3]] as $scores) {
        $round=bin2hex(random_bytes(16));
        foreach ($ids as $index=>$id) {
            $query=$db->prepare('INSERT INTO poker_results(round_id,user_id,room_code,player_count,rank,points) VALUES (?,?,?,4,?,?)');
            $query->execute([$round,$id,$code,4-$scores[$index],$scores[$index]]);
        }
    }
    $page=historyHttp('room-history.php?room='.$code,$cookie);
    $doc=new DOMDocument(); @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$page); $xpath=new DOMXPath($doc);
    historyCheck($xpath->query('//table[@class="rounds-table"]/thead/tr/th')->length===5,'Round plus four player columns.');
    historyCheck($xpath->query('//table[@class="rounds-table"]/tbody/tr')->length===20,'Twenty displayed rounds.');
    foreach ([[3,2,1,0],[0,1,2,3]] as $index=>$scores) {
        $cells=$xpath->query('//table[@class="rounds-table"]/tbody/tr['.($index+1).']/td');
        foreach ($scores as $i=>$score) historyCheck($cells->item($i)->textContent===(string)$score,'Points including zero aligned to player.');
    }
    historyCheck(str_contains($page,htmlspecialchars($names[0],ENT_QUOTES,'UTF-8')) && !str_contains($page,$names[0]),'Escape player names.');
    $lobby=json_decode(historyHttp('lobby-api.php',$cookie),true);
    $room=array_values(array_filter($lobby['rooms'],fn($r)=>$r['code']===$code))[0]; historyCheck($room['hasHistory'],'Online room links to history after completion.');
    $history=array_values(array_filter($lobby['histories'],fn($r)=>$r['code']===$code))[0]; historyCheck($history['rounds']===2,'Room lists both completed rounds.');
    $query=$db->prepare('DELETE FROM poker_rooms WHERE code=?'); $query->execute([$code]);
    $page=historyHttp('room-history.php?room='.$code,$cookie); historyCheck(str_contains($page,'class="rounds-table"'),'History survives closing the room.');
    echo "History tests passed: hidden before completion, room link, 20-row matrix, two round scores including zero, escaped names, and history after closing room.\n";
} finally {
    $query=$db->prepare('DELETE FROM poker_rooms WHERE code=?'); $query->execute([$code]);
    foreach ($ids as $id) { $query=$db->prepare('DELETE FROM public."user" WHERE id=?'); $query->execute([$id]); }
}
