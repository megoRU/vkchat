<?php

/* Код Chat2VK.  */

require_once 'vk_config.php';
use xPaw\SourceQuery\SourceQuery;
use Medoo\Medoo;

class VKontakte {
	
	/*	
		Вызов в функции пределах этого класса:
		
		$this->функция(параметр1, параметр2);
		
		В остальных классах:
		
		require 'vk_class.php';
		$vk = new VKontakte();
		$vk->функция(параметр1, параметр2);
		
	*/
	
	
	//Логгирование в /logs/дата.log
	public function put($title, $data){
		if (VK_LOGGING) {
			file_put_contents(__DIR__ . '/logs/' . date('dmY') . '.log' , $title. ': ' . print_r($data, true) . PHP_EOL, FILE_APPEND);
		}
	}
	
	//Посылает сообщение. Если $peerid больше 2000000000, значит, мы посылаем сообщение в чат. Если меньше - значит, пользователю в личку. $peerid - это либо беседа, либо пользователь (если в лс). $userid - тот, кто вызвал бота
	public function send_vk($peerid, $str, $return = false){
		
		if ($this->is_personal($peerid)) $user = 'user_id=' . $peerid;
		else $user = 'chat_id=' . ($peerid -= 2000000000);
		
		$str = str_replace("#", "%23", $str);
		$str = str_replace(" ", "%20", $str);
		$str = str_replace("&", "%26", $str);
		$str = 'https://api.vk.com/method/messages.send?dont_parse_links=1&' . $user . '&access_token=' . VK_TOKEN . '&v=' . VK_VERSION . '&random_id=' . rand(0, 32767) . '&message=' . $str;
		$this->put('Отправка сообщения', $str);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $str);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if ($return) {
			$ret = curl_exec($ch);
			curl_close($ch);
			return $ret;
		}
		else {
			curl_exec($ch);
			curl_close($ch);
		}
	}
	
	//Посылает клавиатуру
	public function keyboard($peerid, $str, $keyboard){
		$params = [
            'random_id' => rand(0, 32767),
            'message' => $str,
            'keyboard' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            'access_token' => VK_TOKEN,
            'v' => VK_VERSION,
		];
		
		if ($this->is_personal($peerid)) $params['user_id'] = $peerid;
		else $params['chat_id'] = $peerid -= 2000000000;

        $get_params = http_build_query($params);
		$this->put('Отправка клавиатуры', 'https://api.vk.com/method/messages.send?' . $get_params);
        file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
	}
	
	//Создаёт кнопку. Цвета: primary (синий), secondary (белый), negative (красный), positive (зелёный)
	//Подробнее тут https://vk.com/dev/bots_docs_3?f=4.3.+%D0%9E%D1%82%D0%BF%D1%80%D0%B0%D0%B2%D0%BA%D0%B0+%D0%BA%D0%BB%D0%B0%D0%B2%D0%B8%D0%B0%D1%82%D1%83%D1%80%D1%8B
	public function button($text, $color){
		return ['action' => ['type' => 'text', 'payload' => ['button' => $text], 'label' => $text], 'color' => $color]; 
	}
	
	//Получает игроков с сервера + посылает их в вк. Если $steam - true, посылает SteamID игроков, если false, то убийства/смерти
	public function get($peerid, $key, $steam = false){
		$server = SERVERS[$key];
		require '../scripts/SourceQuery/bootstrap.php';
		$q = new SourceQuery();
		try {
			$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
			$q->SetRconPassword($server['pass']);
			$info = $q->GetInfo();
			$map = $info['Map'];
			if (strpos($map, '/') !== false) $map = trim(explode('/', $map)[2]);
			$p = $q->Rcon("sm_web_getplayers");
			$pieces = explode("ArrayEnd", $p);
			$arr = str_replace(",\n]","]",$pieces[0]);
			$players = json_decode($arr, true);
			
			$send = 'Сервер: ' . $info['HostName'] . '%0A connect ';
			$send .= $server['ip'] . ':' . $server['port'] . '%0A';
			$send .= count($players) . ' игроков из ' . $info['MaxPlayers'] . ', карта: ' . $map . '%0A%0A';
			
			if($steam){
				array_multisort(array_column($players, 'steamid'), SORT_ASC, $players);
				foreach ($players as $key => $player){
					$send .= '%0A [U:1:' . $player['steamid'] . '] - ' . $player['name'];
				}
			} else {
				array_multisort(array_column($players, 'k'), SORT_DESC, $players);
				foreach ($players as $key => $player){
					$send .= '%0A [' . $player['k'] . ' / ' . $player['d'] . '] - ' . $player['name'];
				}
			}
			$this->send_vk($peerid, $send);
		}
		catch(Exception $e){
			$ex = $e->getMessage();
			$this->put('Исключение',$ex);
			$this->send_vk($peerid, 'Какая-то ошибка: ' . $ex);
		} finally {
			$q->Disconnect();
		}
	}
	
	//Получает игроков со всех серверов + посылает их в вк.
	public function get_all($peerid){
		$send = '';
		require '../scripts/SourceQuery/bootstrap.php';
		$q = new SourceQuery();
		$now = 0;
		$total = 0;
		foreach (SERVERS as $key => $server){
			try{
				$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
				$info = $q->GetInfo();
				$map = $info['Map'];
				if (strpos($map, '/') !== false) $map = trim(explode('/', $map)[2]);
				$send .= '%0A %0A' . $info['HostName'] . '%0A connect ';
				$send .= $server['ip'] . ':' . $server['port'] . '%0A';
				$send .= $info['Players'] . ' из ' . $info['MaxPlayers'] . ' игроков, карта: ' . $map;
				
				$now += $info['Players'];
				$total += $info['MaxPlayers'];
			} catch(Exception $e){
				$ex = $e->getMessage();
				$this->put('Исключение',$ex);
				$send .= '%0A %0A' . $ex . ' (не работает) %0A connect ';
				$send .= $server['ip'] . ':' . $server['port'];
			} finally {
				$q->Disconnect();
			}
		}
		$send .= '%0A----------------------------------------%0A Онлайн: ' . $now . ' из ' . $total . ' игроков -> ' . number_format((($now/$total)*100), 0) . '%';
		$this->send_vk($peerid, $send);
	}
	
	//Посылает на сервер сообщение
	public function send($peerid, $userid, $key, $message){
		if($this->is_personal($peerid)) $this->send_vk($peerid, 'Команда недоступна в ЛС!');
		else{
			$server = SERVERS[$key];
			require '../scripts/SourceQuery/bootstrap.php';
			$q = new SourceQuery();
			try {
				$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
				$q->SetRconPassword($server['pass']);
				$userinfo = json_decode(file_get_contents('https://api.vk.com/method/users.get?user_ids=' . $userid . '&v=' . VK_VERSION . '&access_token=' . VK_TOKEN));
				$user_name = $userinfo->response[0]->first_name;
				$last_name = $userinfo->response[0]->last_name;
				$q->Rcon("sm_send $user_name $last_name&$message");
			} catch(Exception $e) {
				$ex = $e->getMessage();
				$this->put('Исключение',$ex);
			} finally {
				$q->Disconnect();
			}
		}
	}
	
	//Посылает на все сервера сообщение
	public function send_all($peerid, $userid, $message){
		if($this->is_personal($peerid)) $this->send_vk($peerid, 'Команда недоступна в ЛС!');
		else{
			$userinfo = json_decode(file_get_contents('https://api.vk.com/method/users.get?user_ids=' . $userid . '&v=' . VK_VERSION . '&access_token=' . VK_TOKEN));
			$user_name = $userinfo->response[0]->first_name;
			$last_name = $userinfo->response[0]->last_name;
			require '../scripts/SourceQuery/bootstrap.php';
			$q = new SourceQuery();
			foreach (SERVERS as $key => $server){
				try{
					$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
					$q->SetRconPassword($server['pass']);
					$q->Rcon("sm_send $user_name $last_name&$message");
				} catch(Exception $e){
					$ex = $e->getMessage();
					$this->put('Исключение',$ex);
				} finally {
					$q->Disconnect();
				}
			}
		}
	}
	
	//Исполняет команду на сервере
	public function execute($peerid, $key, $command){
		$server = SERVERS[$key];
		require '../scripts/SourceQuery/bootstrap.php';
		$q = new SourceQuery();
		try {
			$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
			$q->SetRconPassword($server['pass']);
			$q->Rcon($command);
		} catch(Exception $e) {
			$ex = $e->getMessage();
			$this->put('Исключение',$ex);
		} finally {
			$q->Disconnect();
		}
	}
	
	//Исполняет команду на всех серверах
	public function execute_all($peerid, $command){
		require '../scripts/SourceQuery/bootstrap.php';
		$q = new SourceQuery();
		foreach (SERVERS as $key => $server){
			try{
				$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
				$q->SetRconPassword($server['pass']);
				$q->Rcon($command);
			} catch(Exception $e){
				$ex = $e->getMessage();
				$this->put('Исключение',$ex);
			} finally {
				$q->Disconnect();
			}
		}
	}
	
	//Получает ID из пересланного сообщения. Если сообщений много, то из первого. Если их вообще нет, то 0
	public function get_forwarded_id($data){
		if(isset($data->object->reply_message)){
			return $data->object->reply_message->from_id;
		} else if (!empty($data->object->fwd_messages)){
			return $data->object->fwd_messages[0]->from_id;
		} else return 0;
	}
	
	//Проверяет, является ли новое сообщение личным (true), или оно получено из чата (false). 2000000000 - магическое число от ВК. Видимо, больше 2 млрд юзеров вк не ждёт.
	public function is_personal($peerid){
		if ($peerid >= 2000000000) return false;
		else return true;
	}
	
	//Получает ВК по SteamID
	public function get_vk($s){
		$s = $this->steam64($s);
		return $this->search(1, $s);
	}
	
	//Получает SteamID по ВК
	public function get_steam($s){
		if (strpos($s, 'vk.com/id') !== false) {
			$s = explode('/id', $s)[1];
			return $this->search(2, $s);
		} else if (strpos($s, 'vk.com/') !== false) {
			$s = explode('vk.com/', $s)[1];
			$url = 'https://api.vk.com/method/utils.resolveScreenName?screen_name=' . $s . '&access_token=' . VK_TOKEN . '&v=' . VK_VERSION;
			$this->put('URL',$url);
			$json = file_get_contents($url);
			$obj = json_decode($json);
			$s = $obj->response->object_id;
			if (!empty($s)) return $this->search(2, $s);
			else return [];
		} else return $this->search(2, $s);
	}
	
	//Привязывает $s (SteamID) и $fwd (UserID VK)
	public function tie($peerid, $s, $fwd){
		require '../scripts/Medoo/Medoo.php';
		$db = new Medoo(MEDOO_CFG);
		if ($db->has('vk',['AND' => ['steam_id' => $s, 'vk_id' => $fwd]])){
			$this->send_vk($peerid, 'Эти SteamID и VK уже связаны.');
		} else {
			$json = file_get_contents('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . STEAM_API['apikey'] . '&steamids=' . $s);
			$obj = json_decode($json);
			$date = $obj->response->players[0]->timecreated or 0;
			
			$db->insert('vk',[
				'vk_id' => $fwd,
				'steam_id' => $s,
				'steam_date' => $date,
			]);
			
			$this->send_vk($peerid, 'Успешно связали vk.com/id' . $fwd . ' и steamcommunity.com/profiles/' . $s);
		}
	}
	
	//Развязывает $fwd (UserID VK) и принадлежащие ему SteamID. (Отвязку по SteamID пока не сделал)
	public function untie($peerid, $fwd){
		require '../scripts/Medoo/Medoo.php';
		$db = new Medoo(MEDOO_CFG);
		if ($db->has('vk',['vk_id' => $fwd])){
			$db->delete('vk',['vk_id' => $fwd]);
			$this->send_vk($peerid, 'vk.com/id' . $fwd . ' удалён из бота.');
		} else {
			$this->send_vk($peerid, 'vk.com/id' . $fwd . ' ещё не привязан к аккаунту Steam.');
		}
	}
	
	//Поиск по базе, $s - значение, $type 1 - поиск по Steam, 2 - поиск по ВК
	public function search($type, $s){
		require '../scripts/Medoo/Medoo.php';
		$db = new Medoo(MEDOO_CFG);
		if ($type == 1) return $db->select('vk', ['vk_id','steam_id','steam_date'], ['steam_id' => $s]);
		else return $db->select('vk', ['vk_id','steam_id','steam_date'], ['vk_id' => $s]);
	}
	
	//Преобразовывает хрен-пойми-какой SteamID в Community SteamID (64). Если не удалось, возвращает 0
	public function steam64($s){
		if (is_numeric($s)){
			return '765' . (intval($s)+61197960265728);
		} else if (strpos($s, 'STEAM_') === 0) {
			$myarray = explode(":",$s,3);
			$id3 = 0;
			if ($myarray[1] === "1") $id3 = intval($myarray[2]) * 2 + 1;
			else $id3 = intval($myarray[2]) * 2;
			return '765' . (intval($id3)+61197960265728);
		} else if (strpos($s, '[U:1:') === 0) {
			$s = str_replace("[U:1:","",$s);
			$s = str_replace("]","",$s);
			return '765' . (intval($s)+61197960265728);
		} else if (strpos($s, 'steamcommunity.com/id') !== false) {
			$s = explode('/id/', $s)[1];
			$s = str_replace("/","",$s);
			$json = file_get_contents('https://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key=' . STEAM_API['apikey'] . '&vanityurl=' . $s);
			$obj = json_decode($json);
			if ($obj->response->success === 42) return 0;
			else return $obj->response->steamid;
		} else if (strpos($s, 'steamcommunity.com/profiles') !== false) {
			$s = explode('/profiles/', $s)[1];
			return str_replace("/","",$s);
		} else return 0;
	}
	
	//SteamID64 в SteamID3
	public function steam64to3($s){
		$a = substr(decbin($s), -32);
		$id = substr($a, 0, -1);
		$bit = substr($a, -1);
		return bindec($id)*2+bindec($bit);
	}
	
	//Кик пользователя из беседы
	public function kick($peerid, $userid){
		$peerid -= 2000000000;
		$info = json_decode(file_get_contents('https://api.vk.com/method/messages.removeChatUser?chat_id=' . $peerid . '&user_id=' . $userid . '&member_id=' . $userid . '&v=' . VK_VERSION . '&access_token=' . VK_TOKEN));
		$this->put('Результат кика', $info);
	}
}