<?

//configs
require_once('config.php');

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}
function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}
function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}
function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

$gram=json_decode(file_get_contents('php://input'),true);
if ($gram['message']['reply_to_message']!=null) die();
if($gram!=null){
	$str = trim($gram['message']['text']);
	$str = stripslashes($str);
	$str = htmlspecialchars($str);
	$input=array(
		'chat_id'		=>$gram['message']['chat']['id'],
		'user_id'		=>$gram['message']['from']['id'],
		'username'		=>$gram['message']['from']['username'],
		'message'		=>$str,
		'message_id'	=>$gram['message']['message_id']
	);
}else die();

if (isset($gram['message']['new_chat_participant'])) {
	$a=array("Привет", "Здравствуй", "Приветище", "Здарова", "Шалом", "Не стой на месте, проходи", "ну и хули припёрся?", "Ну и что ты тут забыл?", "ПХП ГАВНО!!!");
	$output=$a[array_rand($a)];
	apiRequestJson("sendMessage", array('chat_id' => $input['chat_id'], "text" => $output, 'reply_to_message_id'=>$input['message_id']));
}

	if (file_exists("promise.".$input['user_id'])){
		$command=null;
		if(FindText($input['message'],'отмен')){
			$a=array("Хорошо", "Хозяин-барин", "Ну как хочешь", "Как скажешь, {m-name}", "Ок", "Ну ладно(((");
			$output=$a[array_rand($a)];
		}	else $command=file_get_contents("promise.".$input['user_id']);
		
		switch($command){
			case 'Password4DB':
				$query="INSERT INTO passwords( p_datetime, pname, pass )  VALUES (
					'".date("Y-m-d H:i:s")."','". $input['message'] ."','".file_get_contents("tmp_pass")."')";
				Query2DB($query);
				$output="Паролю ".file_get_contents("tmp_pass")." присвоено имя \"". $input['message'] ."\".\r\n";
			break;
			case 'SetCalendar':
				$output=ToDoSomething('Scheldure',$input['message']);
			break;
		}
		unlink("promise.".$input['user_id']);
	}else{
		$output.=AnalizeMessage($input['message']);
	}
	if($output!=null) AddInJournal($input);
	$a=array(
		'chat_id'=>$input['chat_id'],
		'text'=>$output,
		'parse_mode'=>'Markdown',
		'reply_to_message_id'=>$input['message_id'],
		'disable_web_page_preview'=>null
	);
        apiRequestJson("sendMessage", $a);


function AnalizeMessage($text){
	$output=null;
	$keywords=array(
		'Greeterings'=>array('доброе','добрый','приве','здравств','дрям','здоров','здаров'),
		'WorkWithJournal'=>array('журнал'),
		'Passwords'=>array('пароль'),
		'MarkKing'=>array('#узбек'),
		'Amen'=>array('гавн', 'говн', 'govn'),
		'IntuitLink'=>array('курсы', 'изуч'),
		'Scheldure'=>array('напомни','напоминай')
	);
	foreach ($keywords as $key => $value)
		foreach ($value as $word)
			if(mb_stripos($text,$word,0,"utf-8")!==false)
				$output .= ToDoSomething($key,$text);

	return $output;
}

function ToDoSomething($keyword,$text){
	global $input;
	$output=null;
	switch($keyword){
		case 'Greeterings':
			$a=array("Привет", "Здравствуй", "Приветище", "Здарова");
			if(rand(1,2)==1){
				$curhour=date("G");
				switch ($curhour){
					case ($curhour<6):
					case ($curhour>=23):
						$output="Доброй ночи";
					break;
					case ($curhour>=6 && $curhour<12):
						$output="Доброе утро";
					break;
					case ($curhour>=12 && $curhour<18):
						$output="Добрый день";
					break;
					case ($curhour>=18 && $curhour<23):
						$output="Добрый вечер";
					break;
				}
			}else{
				$output=$a[array_rand($a)];
			}
		break;
		case 'WorkWithJournal':
			$mysqli = new mysqli('localhost', MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
			if ($mysqli->connect_errno) {
				$output="Не удалось подключиться к MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			}
			if(FindText($text,array('очисти','удали','снеси'))){
				$query="truncate journal";
				if ($result = $mysqli->query($query)) {
					$output= "Журнал очищен, {m-name}.\r\n";
				}
			}else{
				$output="Последние записи из журнала:\r\n";
				if (!$mysqli->query("SET NAMES 'utf8'")){
					$output="Не удалось подключиться: (" . $mysqli->errno . ") " . $mysqli->error;
				}
				$mysqli->query("SET SESSION collation_connection = 'utf8_general_ci'");

				$query="select * from journal ORDER BY id DESC LIMIT 10";
				if ($result = $mysqli->query($query)) {
					while ($row = mysqli_fetch_assoc($result)) $output.= "{$row['username']} ({$row['chat_id']}) в {$row['m_datetime']}: {$row['message']} \r\n";
				}
			}
			$mysqli->close();
		break;
		case 'Passwords':
			if(FindText($text,array('генер','дела','придум'))){
				$chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
				if(mb_stripos($text,"сложн",0,"utf-8")!==false) $chars.="~!@#$%^&*_+/.,<>[]{};:-=";
				$max=8;
				$size=StrLen($chars)-1;
				$output=null; 
				while($max--) $output.=$chars[rand(0,$size)];
				
				file_put_contents("tmp_pass", $output);
			}elseif(FindText($text,array('запомни','сохрани'))){
				file_put_contents("promise.".$input['user_id'], "Password4DB");
				$a=array("Как обозвать?", "Как назвать?", "Какой ярлык присвоить?", "Какое имя ему дать?", "Как назовём?", "Какой псевдоним ему присвоить?");
				$output=$a[array_rand($a)];
			}elseif(FindText($text,array('покажи','выведи','закешь','показ'))){
				$mysqli = new mysqli('localhost', MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
				$mysqli->query("SET NAMES 'utf8'");
				$mysqli->query("SET SESSION collation_connection = 'utf8_general_ci'");
				if(FindText($text,'парол')){
					$output="Список паролей:\r\n";
					$query="select * from passwords";
				}elseif(preg_match('/пароль ([a-zA-Z а-яА-Я0-9ёЁ]+)/ui', $text, $matches)){
					$query="select * from passwords where pname='".$matches[1]."'";
				}
				$flag=false;
				if ($result = $mysqli->query($query)) while ($row = mysqli_fetch_assoc($result)){
					$output.= "{$row['pname']} ({$row['p_datetime']}): {$row['pass']} \r\n";
					$flag=true;
				}
				if (!$flag) $output="Список пуст.\r\n";
				$mysqli->close();
			}elseif(FindText($text,array('очисти','удали','снеси'))){
				if(FindText($text,'пароли')){
					$query="truncate passwords"; 
					$tmp="Пароли удалены";
				}elseif(preg_match('/пароль ([a-zA-Z а-яА-Я0-9ёЁ]+)/ui', $text, $matches)){
					$query="delete from passwords where pname='".$matches[1]."'";
					$tmp="Пароль удалён";
					echo $query;
				}
				Query2DB($query);
				$a=array("Готово", "Задание выполнено", "Сделано", $tmp.", {m-name}", "Сделала", "Выполнено");
				$output=$a[array_rand($a)];
			}
		break;
		case 'IntuitLink':
		  if(FindText($text,array('пхп','php','пехепе'))){
			  $output="http://www.intuit.ru/studies/courses/42/42/info или http://www.specialist.ru/dictionary/definition/php";
	    }
		break;
		case 'Amen':
		  if(FindText($text,array('пхп','php','пехепе'))){
		    $a=array("Аминь!", "Воистину гавно!", "Базаришь");
			  $output=$a[array_rand($a)];
	    }
		break;
		
		case 'MarkKing':
		  if(preg_match('/(@[A-Za-z0-9_]{5,}) #узбек/ui', $text, $matches)){
			  $query="insert into kings(username) values ('".$matches[1]."')";
			  $output="Отметила";
		  }
		break;

	}	
	return $output;
}

function AddInJournal($input){
	$query="INSERT INTO journal( m_datetime, chat_id, user_id, username, message )  VALUES (
		'".date("Y-m-d H:i:s")."',
		'".$input['chat_id']."',
		'".$input['user_id']."',
		'".$input['username']."',
		'".$input['message']."'
	)";
	return Query2DB($query);
}

function FindText($text,$a){
	$flag=false;
	if(gettype($a)=='array')
	foreach($a as $item){
		if(mb_stripos($text,$item,0,"utf-8")!==false) $flag=true;
	} else if(mb_stripos($text,$a,0,"utf-8")!==false) $flag=true;
	return $flag;
}

function Query2DB($query){
  $mysqli = new mysqli('localhost', MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
	if ($mysqli->connect_errno) {
		$output="Не удалось подключиться к MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	}
	$mysqli->query("SET SESSION collation_connection = 'utf8_general_ci'");
	if (!$mysqli->query("SET NAMES 'utf8'")){
		$output="Не удалось подключиться: (" . $mysqli->errno . ") " . $mysqli->error;
	}
  $a=array();
  if ($result = $mysqli->query($query)) {
    while ($row = mysqli_fetch_assoc($result)) foreach($row as $value) array_push($a,$value);
	}

	$mysqli->close();
	return $a;
}

?>