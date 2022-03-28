<?
class Amosend {

    const TOKEN_FILE = '/public_html/amocrm/access_tokens/token_info.json';
    public $subdomain       = '';
    private $client_id      = '';
    private $client_secret  = '';
    private $redirect_uri   = '';
    private $code           = '';

    public function __construct(){
    	$this->client_id     = $this->getClientId();
		$this->client_secret = $this->getClientSecret();
		$this->redirect_uri  = $this->getRedirectUri();
		$this->code          = $this->getCode();	
		$this->subdomain     = $this->getSubdomain();

		$token = $this->getToken();
		if (time() >= $token['expires_in']) {
		    $token = $this->saveRefreshToken();
		}
    }
    
    public function getToken(){
    	$accessToken = '';
    	$link        = 'https://' . $this->subdomain . '.amocrm.ru/oauth2/access_token'; 
    	if(file_exists(self::TOKEN_FILE)){
			$accessToken = json_decode(file_get_contents(self::TOKEN_FILE), true);
    	}

	    if (
	        isset($accessToken)
	        && isset($accessToken['access_token'])
	        && isset($accessToken['refresh_token'])
	        && isset($accessToken['expires_in'])
	        && isset($accessToken['token_type'])
	    ) {
	        return $arrToken = [
	            'access_token'  => $accessToken['access_token'],
	            'refresh_token' => $accessToken['refresh_token'],
	            'expires_in'    => $accessToken['expires_in'],
	            'token_type'    => $accessToken['token_type'],
	        ];
	    } else {
			$data = [
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'grant_type'    => 'authorization_code',
				'code'          => $this->code,
				'redirect_uri'  => $this->redirect_uri,
			];
	    	$response               = $this->request($link, $data, 'true');
	    	$response['expires_in'] = time() + $response['expires_in'];
			$this->saveToken($response);

			return $response;
	    }
    }   

    private function getAccessToken(){
    	$token = $this->getToken();
    	return $token['access_token'];
    }   

    private function getRefreshToken(){
    	$token = $this->getToken();
    	return $token['refresh_token'];
    }   

    public function saveRefreshToken(){

		$link = 'https://' . $this->subdomain . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

		/** Соберем данные для запроса */
		$data = [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type'    => 'refresh_token',
			'refresh_token' => $this->getRefreshToken(),
			'redirect_uri'  => $this->redirect_uri,
		];

		$response = $this->request($link, $data, 'true');
		$response['expires_in'] = time() + $response['expires_in'];
		$this->saveToken($response);
		return $response;
    }   


    private function saveToken($token){
	    if (
	        isset($token)
	        && isset($token['access_token'])
	        && isset($token['refresh_token'])
	        && isset($token['expires_in'])
	        && isset($token['token_type'])
	    ) {
	        $data = [
	            'access_token' => $token['access_token'],
	            'expires_in' => $token['expires_in'],
	            'refresh_token' => $token['refresh_token'],
	            'token_type' => $token['token_type'],
	        ];

	        file_put_contents(self::TOKEN_FILE, json_encode($data));
	    } else {
	        exit('Invalid access token ' . var_export($token, true));
	    }
    }   

    public function request($link, $data = false, $auth = false,$type = false){
    	if($auth === 'true') {
    		$httpheader = ['Content-Type:application/json'];
    	} else {
    		$httpheader = ['Authorization: Bearer ' . $this->getAccessToken()];
    	}
    //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/link.txt', print_r($link.PHP_EOL, true) , FILE_APPEND);

	    $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
	    curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
	    curl_setopt($curl,CURLOPT_URL, $link);
	    curl_setopt($curl,CURLOPT_HTTPHEADER,$httpheader);
	    curl_setopt($curl,CURLOPT_HEADER, false);
		if ($auth && $data) {
		    curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
		    curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
		}elseif($type && $data){
     	    curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json','Authorization: Bearer ' . $this->getAccessToken()]);			
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
		    curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
		}elseif($data) {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
	    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
	    curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
	    $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
	    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	    curl_close($curl);

		$this->checkCurlResponse($code);
		$out = ($out) ? json_decode($out, true) : false;
		return $out; 
    }   

	public function checkCurlResponse($code){
		static $iteration = 0;
		$iteration++;
		$code   = (int) $code;
		$errors = array(
			301 => 'Moved permanently',
			400 => 'Bad request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not found',
			500 => 'Internal server error',
			502 => 'Bad gateway',
			503 => 'Service unavailable'
		);
		try {
			#Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
			if ($code != 200 && $code != 204)
				throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
		}
		catch (Exception $E) {
			echo $iteration;
			die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode());

		}
	}

	private function hasExpired($token)	{
		if(time() > $token['expires_in']){

		}
	}

	public function getSubdomain(){
		return $this->subdomain;
	}	

	private function getCode(){
		return $this->code;
	}	

	private function getClientId(){
		return $this->client_id;
	}

	private function getClientSecret(){
		return $this->client_secret;
	}	

	private function getRedirectUri(){
		return $this->redirect_uri;
	}

}