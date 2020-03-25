<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VK Helper</title>
<style>
	body{
		margin:1em auto;
		max-width:40em;
		padding:0.62em;
		font: 1.2em/1.62 -apple-system, BlinkMacSystemFont, /* MacOS and iOS */
	   'avenir next', avenir, /* MacOS and iOS */
	   'Segoe UI', /* Windows */
	   'lucida grande', /* Older MacOS */
	   'helvetica neue', helvetica, /* Older MacOS */
	   'Fira Sans', /* Firefox OS */
	   roboto, noto, /* Google stuff */
	   'Droid Sans', /* Old Google stuff */
	   cantarell, oxygen, ubuntu, /* Linux stuff */
	   'franklin gothic medium', 'century gothic', /* Windows stuff */
	   'Liberation Sans', /* Linux */
	   sans-serif; /* Everything else */;
	}
	h1,h2,h3 {
		line-height:1.2;
	}
	@media print{
		body{
			max-width:none
		}
	}
</style>
<article>
	<?php
		//phpinfo();
		use xPaw\SourceQuery\SourceQuery;
		use Medoo\Medoo;
		echo '<h1>Привет!</h1>';
		echo '<p>Данный файл предназначен для выявления проблем. В конце его выполнения вы увидите: <font color="green">[Проверка пройдена!]</font>, если нет, значит, не всё работает как надо.</p>';
		echo '<p>Предполагается, что вы уже выполнили всё по инструкции <a href=https://hlmod.ru/resources/chat-2-vkontakte.959/>отсюда</a> и прочли <a href=https://hlmod.ru/threads/chat-2-vkontakte.46248/page-11#post-422868>этот пост</a>, и всё равно что-то не работает!</p>';
		echo '<p>Удалите этот файл, когда он вам больше не нужен!</p>';
		
		echo '<h1>Проверка конфига (vk_config.php)</h1>';
		echo '<p>Если дальше этой строчки пусто, значит, в данном файле ошибка. Проверьте правильность заполнения, присутствие всех скобок и запятых, сверьтесь с оригиналом файла..</p>';
		require_once 'vk_config.php';
		if (VK_LOGGING) echo '<p>vk_config открыт успешно. Логгирование включено (VK_LOGGING = true;)</p>';
		else {
			echo '<p>vk_config открыт успешно. Логгирование выключено (VK_LOGGING = false;), включите, затем обновите страницу.</p>';
			die;
		}
		
		echo '<p><b>Ссылка на бота: </b>' . VK_LINK . '</p>';
		echo '<p><b>Версия API VK: </b>' . VK_VERSION . '</p>';
		echo '<p><b>Админы бота: </b>' . print_r(VK_ADMINS, true) . ' - если вы не видите здесь <a href=http://regvk.com/id/>свой ID</a>, а видите 1 и 142805811, значит, вас нет в админах. Без этого не заработают функции с rcon, а так же функции со steam (!tie и !untie). Админы в этой настройке имеют полный доступ к серверу через RCON!</p>';
		
		echo '<h1>Проверка основных функций (vk_class.php)</h1>';
		require_once 'vk_class.php';
		echo '<p>vk_class открыт успешно. Попробуем записать что-нибудь в логи. Откройте папку /vkontakte/logs и найдите файл ' . date('dmY') . '.log</p>';
		echo '<p>Если он отсутствует, надо сделать <a href=https://i.imgur.com/Jha4eOB.png>так</a> и обновить эту стр.</p>';
		$vk = new VKontakte();
		$vk->put('Тест','это лог из vk_helper.php.');
		$vk->put('Ваши сервера',SERVERS);
		echo '<p>Теперь тестируем отправку сообщения в беседы с ID от 1 до 5!</p>';
		for ($i = 1; $i <= 5; $i++) {
			echo '<p><details><summary>Отклик от беседы ' . $i . '</summary>' . $vk->send_vk(2000000000+$i, 'Сообщение в беседу ' . $i, true) . '</details></p>';
		}
		echo '</br><p><b>Теперь объяснение.</b> Response 0 = отлично, сообщение отправлено, а иначе ошибка. Если вы пригласили бота в беседу впервые, её ID будет равен 1 <b>(а в конфиг chat2vk.ini надо писать: 2000000001)</b>. Скорее всего, отправка в беседы 2,3,4,5 не прошла, это нормально, ведь бота в них может и не быть.</p>';
		echo '<p>Internal server error означает, что беседа отсутствует.</p>';
		echo '<p>Если же бот вообще никуда не отправил сообщение, то у вас неправильно заполнен конфиг, или бот просто не приглашён в беседу.</p>';
		
		echo '<h1>Тест вывода онлайна со всех ваших серверов!</h1>';
		echo '<p>Если вы не видите дальше ваших серверов.. возможно, неверный айпи/порт/rcon или rcon_password вообще не настроен. Смотрите в консоль сервера, там должны появиться игроки! Ещё рекомендуется вписать в кфг сервера эти команды (для CSGO) : <i>host_name_store 1;host_info_show 2;host_players_show 2</i></p>';
		echo '<p>Подключаем класс SourceQuery: ../scripts/SourceQuery/bootstrap.php</p>';
		require '../scripts/SourceQuery/bootstrap.php';
		$q = new SourceQuery();
		foreach (SERVERS as $key => $server){
			$q->Connect($server['ip'],$server['port'], 3, SourceQuery::SOURCE);
			$q->SetRconPassword($server['pass']);
			$info = $q->GetInfo();
			$map = $info['Map'];
			if (strpos($map, '/') !== false) $map = trim(explode('/', $map)[2]);
			
			$p = $q->Rcon("sm_web_getplayers");
			$pieces = explode("ArrayEnd", $p);
			$arr = str_replace(",\n]","]",$pieces[0]);
			$players = json_decode($arr, true);
			
			$send = '</br>Сервер: ' . $info['HostName'] . '</br> connect ';
			$send .= $server['ip'] . ':' . $server['port'] . '</br>';
			$send .= count($players) . ' игроков из ' . $info['MaxPlayers'] . ', карта: ' . $map . '</br></br>';
			
			array_multisort(array_column($players, 'k'), SORT_ASC, $players);
			foreach ($players as $key => $player){
				$send .= '</br> [U:1:' . $player['steamid'] . '] [' . $player['k'] . ' / ' . $player['d'] . '] - ' . $player['name'];
			}
			
			echo '<p><details><summary>Онлайн с сервера  ' . $server['ip'] . ':' . $server['port'] . '</summary>' . $send . '</br></br><b>Массив игроков в сыром виде:</b> ' . $pieces[0] . '</details></p>';
		}
			
		echo '<p>Тут выводятся одновременно steamid, k (киллы) и d (смерти), а в беседе вк вам надо писать: !1 steam (чтобы увидеть стимы) или !1 (для K/D)</p>';
		echo '<h1>Тестируем функции со Steam </h1>';
		if (!VK_STEAMBOT) echo '<p>VK_STEAMBOT = false; а значит функции отключены - это нормально, бот работает и без них.</p>';
		else{
			echo '<p>О том что делают эти функции можно прочесть <a href=https://hlmod.ru/threads/chat-2-vkontakte.46248/page-11#post-422868>в этом посте</a>, если вам это не надо, поставьте в vk_config.php VK_STEAMBOT = false;</p>';
			echo '<p>Подключаем класс ../scripts/Medoo/Medoo.php</p>';
			require '../scripts/Medoo/Medoo.php';
			echo '<p>Подключаемся к базе sqlite (если не работает, действуем как <a href=https://i.imgur.com/Jha4eOB.png>тут</a> но с папкой sqlite) и пробуем записать значение.</p>';
			$db = new Medoo(MEDOO_CFG);
			if ($db->has('vk',['steam_id' => 76561198034202275])){
				echo '<p>В базе уже есть steamid 76561198034202275, команда для него: </br>!steam vk.com/id32957931</p>';
			} else {
				$db->insert('vk',[
					'vk_id' => 32957931,
					'steam_id' => 76561198034202275,
					'steam_date' => 1290864008,
				]);
			}
			$arr = $db->select('vk', ['vk_id','steam_id','steam_date'], ['steam_id' => 76561198034202275]);
			echo '<p>Выводим данные одного известного игрока..</p>';
			var_dump($arr);
		}
		
		echo '<h1>Что ж, я сделал всё, что мог!</h1>';
		echo '<p>Осталось потестировать бота в беседе. Если что-то не работает, убедитесь что бот вообще получает входящие сообщения, заполнены все поля, и так далее, <a href=https://imgur.com/a/Qx4FexL>вот альбом с пояснениями..</a> </p>';
		echo '<p><font color="green">[Проверка пройдена!]</font> <hr> Удачи, пишите отзывы и вопросы в тему на HL или мне в личку :3</p>';
		
	?>
</article>