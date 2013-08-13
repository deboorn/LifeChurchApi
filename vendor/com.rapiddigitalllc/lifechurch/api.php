<?php 


/**
 * LifeChurch API
 * @author Daniel Boorn - daniel.boorn@gmail.com
 * @copyright Daniel Boorn
 * @license Creative Commons Attribution-NonCommercial 3.0 Unported (CC BY-NC 3.0)
 * @namespace LifeChurch
 */

/**
 * The class uses a JSON api_path.js file that defines the API endpoints and paths. 
 * This class is chainable! Please see examples before use.
 * 
*/


namespace LifeChurch;

class Exception extends \Exception{
	
	public $response;
	public $extra;
		
	public function __construct($message, $code = 0, $response=null, $extra=null, \OAuthException $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->response = $response;
		$this->extra = $extra;
	}
	
	public function getResponse(){
		return $this->response;
	}
	
	public function getExtra(){
		return $this->extra;
	}
	
}

class API{
	

	const TOKEN_CACHE_FILE = 0;
	const TOKEN_CACHE_SESSION = 1;
	const TOKEN_CACHE_CUSTOM = 2;
		

	public $debug = false;
	public $error = null;
	public $paths;
	
	public $tokenPaths = array(
		'tokenCache'=> 'tokens/',
		'accessToken'=>'/accesstoken',
	);
	
	public $contentType = 'json';
		
	protected $settings = array(
		'key'=>'',
		'secret'=>'',
		'username'=>'',
		'password'=>'',
		'baseUrl'=>'',
	);
	
	protected $endpointId;
	protected $pathIds = array();
	protected $response;
	
	
	/**
	 * construct
	 * @param array $settings=null
	 * @returns void
	 * @throws \Exception
	 */
	public function __construct($settings=null){
		$this->settings = $settings ? (object) $settings : (object) $this->settings;
		$this->loadApiPaths();
	}
	
	/**
	 * forge factory
	 * @param array $settings=null
	 * @returns void
	 */
	public function forge($settings=null){
		$self = new self($settings);
		if($self->settings->username && $self->settings->password){
			$self->login2ndParty($self->settings->username, $self->settings->password);
		}
		return $self;
	}	
	
	/**
	 * deboug output
	 * @returns void
	 */
	public function d($obj){
		if($this->debug) var_dump($obj);
	}
	
	/**
	 * magic method for building chainable api path with trigger to invoke api method
	 * @param string $name
	 * @param array $args
	 * @returns $this
	 */
	public function __call($name, $args){
		$this->endpointId .= $this->endpointId ? "_{$name}" : $name;
		$this->d($this->endpointId);
		$this->d($args);
		if(count($args)>0 && gettype($args[0]) != "array" && gettype($args[0]) != "object") $this->pathIds[] = array_shift($args);
		if(isset($this->paths[$this->endpointId])){
			$r = $this->invoke($this->endpointId, $this->paths[$this->endpointId]['verb'],$this->paths[$this->endpointId]['path'],$this->pathIds,current($args));
			$this->reset();
			return $r;
		}
		return $this;		
	}
	
	/**
	 * clear properties used by chain requests
	 * @returns void
	 */
	public function reset(){
		$this->endpointId = null;
		$this->pathIds = array();
	}
	
	/**
	 * set content type to xml
	 */
	public function xml(){
		$this->contentType = 'xml';
		return $this;
	}
	
	/**
	 * set content type to json
	 */
	public function json(){
		$this->contentType = 'json';
		return $this;
	}
	
	/**
	 * returns parsed path with ids (if any)
	 * @param string $path
	 * @param array $ids
	 * @returns string
	 * @throws \Exception
	 */
	protected function parsePath($path, $ids){
		$parts = explode("/",ltrim($path,'/'));
		for($i=0; $i<count($parts); $i++){
			if($parts[$i]{0}=="{"){
				if(count($ids)==0) throw new \Exception("Api Endpont Path is Missing 1 or More IDs [path={$path}].");
				$parts[$i] = array_shift($ids);
			}
		}
		return '/'.implode("/",$parts);
	}
	
	/**
	 * invoke api endpoint method
	 * @param string $id
	 * @param string $verb
	 * @param string $path
	 * @param array $ids=null
	 * @param mixed $params=null
	 */
	public function invoke($id, $verb, $path, $ids=null, $params=null){
		$path = $this->parsePath($path, $ids);
		$this->d("Invoke[$id]: {$verb} {$path}",$params);
		$url = "{$this->settings->baseUrl}{$path}.{$this->contentType}";
		$this->response = $this->fetch($url,$params,$verb);
		$this->d($this->response);
		return $this;
	}

	/**
	 * return phpQuery document from xml
	 * @param string $xml
	 * @requires phpQuery
	 * @returns phpQuery
	 */
	public function getDoc($xml){
		return \phpQuery::newDocumentXML($xml);
	}
	
	/**
	 * return api response
	 * @returns object|boolean
	 */
	public function get(){
		if($this->contentType=='json') return $this->response;
		return $this->getDoc($this->response);
	}
	

	
	/**
	 * return error data
	 * @returns object
	 */
	public function error(){
		return $this->response['data'];//error_code, error_message
	}
	
	/**
	 * loads api paths list from json file
	 * @returns void
	 */
	protected function loadApiPaths(){
		$filename = __DIR__ . "/api_paths.json";
		$this->paths = json_decode(file_get_contents($filename),true);
	}
	
	/**
	 * BEGIN: OAuth Functions
	 */
	
	/**
	 * get person information by login credentials
	 * @param string $username
	 * @param string $password
	 * @return array|boolean
	 */
	public function getPersonByCredentials($username,$password){
		//try portal first
		$token = $this->obtainCredentialsBasedAccessToken($username,$password,true);
		if(!$token){//if false, try weblink user
			$token = $this->obtainCredentialsBasedAccessToken($username,$password,true,false);
		}
		if(!$token) return false;
	
		$url = $token->headers['Content-Location'].".{$this->contentType}";
		return $this->fetch($url);
	}
	
	/**
	 * fetches JSON request on LifeChurch, parses and returns response
	 * @param string $url
	 * @param string|array $data
	 * @param const $method
	 * @param string $contentType
	 * @param boolean $returnHeaders
	 * @return void
	 */
	public function fetch($url,$data=null,$method=OAUTH_HTTP_METHOD_GET,$contentType=null,$returnHeaders=false){
		if($method==OAUTH_HTTP_METHOD_GET && is_array($data)){
			$url .= "?" . http_build_query($data);
			$data = null;
		}
		if(($method==OAUTH_HTTP_METHOD_PUT || $method==OAUTH_HTTP_METHOD_POST) && (gettype($data)=="array" || gettype($data)=="object")){
			$data = json_encode($data);
		}
		if(!$contentType) $contentType = "application/$this->contentType";
		
		try{
			$o = new \OAuth($this->settings->key, $this->settings->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
			$o->debug = $this->debug;
			$o->disableSSLChecks();
			$o->setToken($this->accessToken->oauth_token, $this->accessToken->oauth_token_secret);
			$headers = array('Content-Type' => $contentType,);
			if($o->fetch($url, $data, $method, $headers)){
 				//print_r($o->debugInfo);die();
				if(str_replace("json","",$contentType)!=$contentType){
					if(!$returnHeaders) return json_decode($o->getLastResponse(),true);
					return array('response'=>json_decode($o->getLastResponse(),true),'headers'=>self::http_parse_headers($o->getLastResponseHeaders()));
				}else{
					if(!$returnHeaders) return $o->getLastResponse();
					return array('response'=>$o->getLastResponse(),'headers'=>self::http_parse_headers($o->getLastResponseHeaders()));
				}
			}
		}catch(\OAuthException $e){
			$extra = array(
				'data'=>$data,
				'url'=>$url,
				'method'=>$method,
				'headers'=>$o->getLastResponseHeaders(),
			);
			throw new Exception($e->getMessage(),$e->getCode(),$o->getLastResponse(), $extra, $e);			
		}
	}
	
	/**
	 * get access token file name from username
	 * @param string $username
	 * @return string
	 */
	protected function getAccessTokenFileName($username){
		$hash = md5($username);
		return $this->tokenPaths['tokenCache'] . ".lc_{$hash}.accesstoken";
	}
	
	/**
	 * get access token from file by username
	 * @param string $username
	 * @return array|NULL
	 */
	protected function getFileAccessToken($username){
		$fileName = $this->getAccessTokenFileName($username);
		if(file_exists($fileName)){
			return json_decode(file_get_contents($fileName));
		}
		return null;
	}
	
	
	/**
	 * get access token from session by username
	 * @param string $username
	 * @return array|NULL
	 */
	protected function getSessionAccessToken($username){
		if(isset($_SESSION['LifeChurchAccessToken'])){
			//be sure to return object with "oauth_token" and "oauth_token_secret" properties
			return (object) $_SESSION['LifeChurchAccessToken'];
		}
		return null;
	}
	
	/**
	 * get cached access token by username
	 * @param string $username
	 * @param const $cacheType
	 * @return array|NULL
	 */
	protected function getAccessToken($username,$cacheType,$custoHandlers){
		switch($cacheType){
			case self::TOKEN_CACHE_FILE:
				$token = $this->getFileAccessToken($username);
				break;
			case self::TOKEN_CACHE_SESSION:
				$token = $this->getSessionAccessToken($username);
				break;
			case self::TOKEN_CACHE_CUSTOM:
				if($username){
					$token = call_user_func($custoHandlers['getAccessToken'],$username);
				}else{
					$token = call_user_func($custoHandlers['getAccessToken']);
				}
		}
		if($token) return $token;
	}
	
	/**
	 * save access token to file by username
	 * @param string $username
	 * @param array $token
	 * @return void
	 */
	protected function saveFileAccessToken($username,$token){
		$fileName = $this->getAccessTokenFileName($username);
		file_put_contents($fileName,json_encode($token));
	}
	
	/**
	 * save access token to session
	 * @param array $token
	 * @return void
	 */
	protected function saveSessionAccessToken($token){
		$_SESSION['LifeChurchAccessToken'] = (object) $token;
	}
	
	/**
	 * save access token by session or file
	 * @param string $username
	 * @param array $token
	 * @param const $cacheType
	 * @return void
	 */
	protected function saveAccessToken($username,$token,$cacheType,$custoHandlers){
			
		switch($cacheType){
			case self::TOKEN_CACHE_FILE:
				$this->saveFileAccessToken($username,$token);
				break;
			case self::TOKEN_CACHE_SESSION:
				$this->saveSessionAccessToken($token);
				break;
			case self::TOKEN_CACHE_CUSTOM:
				if($username){
					call_user_func($custoHandlers['setAccessToken'],$username,$token);
				}else{
					call_user_func($custoHandlers['setAccessToken'],$token);
				}
		}
	}
	
	/**
	 * 2nd Party credentials based authentication
	 * @param string $username
	 * @param string $password
	 * @param const $cacheType
	 * @return boolean
	 */
	public function login2ndParty($username,$password,$cacheType=self::TOKEN_CACHE_SESSION,$custoHandlers=NULL){
		$token = $this->getAccessToken($username,$cacheType,$custoHandlers);
		if(!$token){
			$token = $this->obtainCredentialsBasedAccessToken($username,$password);
			$this->saveAccessToken($username,$token,$cacheType,$custoHandlers);
		}
		$this->accessToken = $token;
			
		return true;
	
	}
	
	/**
	 * parse header string to array
	 * @source http://php.net/manual/en/function.http-parse-headers.php#77241
	 * @param string $header
	 * @return array $retVal
	 */
	public static function http_parse_headers( $header ){
		$retVal = array();
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
		foreach( $fields as $field ) {
			if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
				$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
				if( isset($retVal[$match[1]]) ) {
					$retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
				} else {
					$retVal[$match[1]] = trim($match[2]);
				}
			}
		}
		return $retVal;
	}
	
	/**
	 * obtain credentials based access token from API
	 * @param string $username
	 * @param string $password
	 * @param boolean $returnHeaders=false
	 * @param boolean $portalUser=true
	 * @return array
	 */
	protected function obtainCredentialsBasedAccessToken($username,$password,$returnHeaders=false,$portalUser=true){
		try{
			$url = $this->settings->baseUrl . $this->tokenPaths['accessToken'] . ".json?username={$username}&password={$password}";
			$o = new \OAuth($this->settings->key, $this->settings->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
			$o->debug=true;
			$token = $o->getAccessToken($url);
			if($returnHeaders) $token['headers'] = self::http_parse_headers($o->getLastResponseHeaders());
			return (object) $token;
		}catch(\OAuthException $e){
			throw new Exception($e->getMessage(), $e->getCode(), $o->getLastResponse(), array('url'=>$url), $e);
		}
	}
	
	
}
