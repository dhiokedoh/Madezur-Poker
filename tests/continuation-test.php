<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';
require dirname(__DIR__) . '/poker-room.php';
function continuationCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function continuationHttp(string $path, array &$client, ?array $body = null): array {
    $options=['ignore_errors'=>true,'timeout'=>10,'header'=>empty($client['cookie'])?'':'Cookie: '.$client['cookie']."\r\n"];
    if ($body!==null) {$options['method']='POST';$options['header'].='Content-Type: application/x-www-form-urlencoded';$options['content']=http_build_query($body);}
    $text=file_get_contents('http://localhost/poker/'.$path,false,stream_context_create(['http'=>$options]));
    foreach ($http_response_header as $header) if(stripos($header,'Set-Cookie: PHPSESSID=')===0) $client['cookie']=explode(';',substr($header,12))[0];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
    return [(int)$match[1],$text];
}
function continuationApi(array &$client,string $action,array $fields=[]): array {
    [$status,$body]=continuationHttp('poker-api.php',$client,['action'=>$action,'token'=>$client['token']]+$fields);
    return [$status,json_decode($body,true)];
}
function completedFixture(PDO $db,array $members,string $code): void {
    $game=pokerNew(array_column($members,'name'));
    $game['ranking']=array_keys($members);$game['winner']=0;$game['finished']=true;
    $round=bin2hex(random_bytes(16));
    $room=['members'=>$members,'game'=>$game,'playerCount'=>count($members),'deckCount'=>1,'roundId'=>$round,'scoreRecorded'=>true,'revision'=>1];
    $q=$db->prepare('INSERT INTO poker_rooms(code,state) VALUES (?,CAST(? AS jsonb))');$q->execute([$code,json_encode($room)]);
    foreach($members as $seat=>$member){$q=$db->prepare('INSERT INTO poker_results(round_id,user_id,room_code,player_count,rank,points) VALUES (?,?,?,?,?,?)');$q->execute([$round,$member['id'],$code,count($members),$seat+1,count($members)-$seat-1]);}
}
$db=database();$ids=[];$codes=[];$clients=[];$members=[];
try {
    for($i=0;$i<5;$i++){
        $name='__continue_'.bin2hex(random_bytes(6));
        $q=$db->prepare('INSERT INTO public."user" ("user",password) VALUES (?,?) RETURNING id');$q->execute([$name,password_hash('21',PASSWORD_DEFAULT)]);$id=(int)$q->fetchColumn();$ids[]=$id;$members[]=['id'=>$id,'name'=>$name];
        $client=['cookie'=>'','token'=>''];continuationHttp('login.php',$client,['identity'=>$name,'password'=>'21']);[$status,$page]=continuationHttp('poker.php',$client);preg_match('/data-token="([a-f0-9]+)"/',$page,$match);$client['token']=$match[1];$clients[]=$client;
    }
    $code=strtoupper(bin2hex(random_bytes(4)));$codes[]=$code;completedFixture($db,$members,$code);
    for($i=0;$i<5;$i++){[$status]=continuationApi($clients[$i],'join',['code'=>$code]);continuationCheck($status===200,'Resume completed room.');}
    [$status,$state]=continuationApi($clients[0],'stop');continuationCheck($status===200&&$state['room']===null,'Stop returns to entry.');
    for($i=1;$i<5;$i++){[$status,$state]=continuationApi($clients[$i],'continue');continuationCheck($status===200,'Continue accepted.');if($i<4)continuationCheck($state['game']['finished'],'Wait for every decision.');}
    continuationCheck(!$state['game']['finished']&&count($state['members'])===4&&$state['playerCount']===4,'Five minus one starts four-player round.');
    continuationCheck(!in_array($ids[0],array_column($state['members'],'id'),true),'Stopped player not dealt cards.');
    [$status]=continuationApi($clients[1],'stop');continuationCheck($status===422,'Cannot stop during active round.');
    [$status]=continuationApi($clients[0],'join',['code'=>$code]);continuationCheck($status===422,'Stopped player cannot rejoin.');
    [$status,$page]=continuationHttp('room-history.php?room='.$code,$clients[0]);
    $doc=new DOMDocument();@$doc->loadHTML($page);$xp=new DOMXPath($doc);
    continuationCheck($xp->query('//table[@class="rounds-table"]/tbody/tr[1]/td')->item(0)->textContent==='4','Prior points preserved.');
    continuationCheck($xp->query('//table[@class="rounds-table"]/tbody/tr[2]/td')->item(0)->textContent==='0','Stopped player next row is zero.');
    $code=strtoupper(bin2hex(random_bytes(4)));$codes[]=$code;completedFixture($db,array_slice($members,1),$code);
    for($i=1;$i<5;$i++){
        continuationHttp('login.php',$clients[$i],['identity'=>$members[$i]['name'],'password'=>'21']);[$status,$page]=continuationHttp('poker.php',$clients[$i]);preg_match('/data-token="([a-f0-9]+)"/',$page,$match);$clients[$i]['token']=$match[1];
        [$status]=continuationApi($clients[$i],'join',['code'=>$code]);continuationCheck($status===200,'Join four-player fixture.');
    }
    [$status]=continuationApi($clients[1],'stop');continuationCheck($status===200,'One of four stops.');
    [$status,$body]=continuationHttp('poker-api.php',$clients[2]);$state=json_decode($body,true);continuationCheck($state['halted']&&$state['activeCount']===3,'Fewer than four halts room.');
    [$status]=continuationApi($clients[2],'continue');continuationCheck($status===422,'Halted room cannot continue.');
    [$status]=continuationApi($clients[2],'start');continuationCheck($status===422,'Host cannot bypass halt.');
    [$status,$body]=continuationHttp('lobby-api.php',$clients[2]);$data=json_decode($body,true);$listed=array_values(array_filter($data['rooms'],fn($r)=>$r['code']===$code))[0];continuationCheck($listed['status']==='Permainan dihentikan'&&!$listed['joinable'],'Lobby displays halted room.');
    echo "Continuation tests passed: wait for decisions, 5-to-4 restart, stopped-player zero with prior points preserved, no stop during play, and 4-to-3 halt.\n";
} finally {
    foreach($codes as $code){$q=$db->prepare('DELETE FROM poker_rooms WHERE code=?');$q->execute([$code]);}
    foreach($ids as $id){$q=$db->prepare('DELETE FROM public."user" WHERE id=?');$q->execute([$id]);}
}
