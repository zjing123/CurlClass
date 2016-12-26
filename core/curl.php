<?php

namespace Curl;

class Curl
{
	const VERSION = '7.1.0';
	private $baseUrl = null;
    private $url = null;
    private $urlPrefix = 'http';
    private $curl = null;
    private $data = null;

    /**
     * 在curl抓取页面时，如果页面会发生301,302跳转：
     * 自动进行跳转抓取
     * 
     * @var boolean
     */
    private $follow_redirects = true;
    
    /**
     * 最多允许跳转次数
     * 可通过Curl->set_max_redirs($max)进行设置
     * 
     * @var int
     */
    private $max_redirs = 3;
    
    /**
     * 设置HTTP_REFERER
     * @var string
     */
    private $referer;
    
    /**
     * 返回内容需要response header
     * 通过 setFollowHeader()设置
     * 
     * @var boolean
     */
    private $follow_header = false;
    
    /**
     * 返回内容不需要response body
     * 通过setFollowNobody()设置
     * 
     * @var boolean
     */
    private $follow_nobody = false;
    
    /**
     * 请求的user-agent
     * 
     * @var $userAgent
     */
    private $userAgent;
    
    private $post;

    private $retry   = 0;

    private $defaultOptions = array();
    private $options = array();
    
    private $info     = array();

    private $error = false;
    private $errorCode = 0;
    private $errorMessage = null;

    private $http_status_code = 0;
    private $httpError = 0;
    private $httpErrorMessage = '';

    private $curlError    = null;
    private $curlErrorCode = 0;
    private $curlErrorMessage = '';

    private $cookies = array();
    private $cookieString = null;
    private $cookieFile = '';
    private $cookieJar = '';
    private $responseCookies = array();
    
    private $headers = array();
    private $defaultHeaders = array(
    	"Content-Type" => " text/xml; CHARSET=utf-8",
    	"Expect"       => " 100-continue"		
    );
    
    private $requestHeaders = null;
    
    private $rawResponse = null;
    private $response = null;
    private $responseHeaders = null;
    //没有处理过的Headers
    private $rawResponseHeaders = '';
    
    /**
     * 对返回的内容使用的解码器 json | xml | default
     * 
     * @var $defaultDecoder 默认解码器
     */
    private $defaultDecoder = null;
    private $jsonPattern = null;
    private $jsonDecoder = null;
    private $xmlPattern = null;
    private $xmlDecoder = null;
    
    private $fileName = '';

    public static $instance = null;

    public function __construct($url = null)
    {
        if (!empty($url)){
            $this->url($url);
        }
        $this->curl = curl_init($this->url);
        $this->defaultOptions = array(
	        CURLOPT_HEADER         => 0,
	        CURLOPT_TIMEOUT        => 30,
	        CURLOPT_ENCODING       => 'utf-8', /*设置编码为Utf-8*/
	        CURLOPT_IPRESOLVE      => 1,
	        CURLOPT_RETURNTRANSFER => true,
	        CURLOPT_SSL_VERIFYPEER => false,
	        CURLOPT_CONNECTTIMEOUT => 10, /*curl连接超时时间*/
        	CURLINFO_HEADER_OUT    => true
    	);
        
        $this->_init();
    }
    
    public function __destruct() {
    	$this->close();
    }

    /**
     * Instance
     * @return self
     */
    public static function init()
    {
        if (self::$instance === null){
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 初始化
     */
    private function _init() {
    	$this->setJsonPattern();
    	$this->setDefaultJsonDecoder();
    	$this->setXmlPattern();
    	$this->setDefaultXmlDecoder();
    	$this->setDefaultHeader();//加载默认header
    	$this->setOpts($this->defaultOptions);//加载默认设置
    }
    
    /**
     *
     * @return string
     */
    public function get($url = null, $data = array())
    {
    	if (is_array($url)) {
    		$data = $url;
    		$url = $this->baseUrl;
    	}
    	
    	if (!empty($this->data)) {
    		$data = array_merge($data, $this->data);
    	}
    	
    	if (is_string($url)){
    		$url = $this->converUrl($url);
    		$this->setUrl($url, $data);
    	}
    	
    	$this->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');
    	$this->setOpt(CURLOPT_HTTPGET, true);
    	
    	return $this;
    	 
    }
    
    /**
     * Set POST data
     * @param array|string $data
     * @param null|string $value
     * @return self
     */
    public function post($data = null, $value = null)
    {
    	if (is_array($data)){
    		foreach ($data as $key => $value) {
    			$this->post[$key] = $value;
    		}
    	} else {
    		if ($value === null) {
    			$this->post = $data;
    		} else {
    			$this->post[$data] = $value;
    		}
    	}
    	
    	curl_setopt($this->curl, CURLOPT_POST, true);
    	
    	$data = array();
    	
    	if (!empty($this->data)) {
    		$data = $this->data;
    	}
    	
    	if (!empty($this->post)) {
    		$data = array_merge($this->post, $data);
    		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->convert($data));
    	}
    
    	return $this;
    }
    
    /**
     * File upload
     * 
     * @access public
     * 
     * @param string $field
     * @param string $path
     * @param string $type
     * @param string $name
     * 
     * @return self
     */
    public function file($field, $path, $type, $name)
    {
    	$name = basename($name);
    	$path = realpath($path);
    	
    	if (class_exists('CURLFile')) {
    		/*PHP5.5以后使用这个函数*/
    		$this->setOpt(CURLOPT_SAFE_UPLOAD, true);
    		$file = curl_file_create($path, $type, $name);
    	} else {
    		$file = "@{$pah};type={$type};filename={$name}";
    	}
    
    	return $this->post($field, $file);
    }
    
    /**
     * Save file
     * 
     * @param string $path
     * @return self
     * 
     * @throws Exception
     */
    public function save($path = '', $name = '')
    {
    	if ($this->error) {
    		throw new \Exception($this->message, $this->error);
    	}
    
    	if (empty($path)) {    		
    		$this->fileName = $this->getUrlName();
    	} else {
    		if ($this->checkPath($path)) {
    			if (empty($name)) {
    				$this->fileName = realpath($path) . DIRECTORY_SEPARATOR . $this->getUrlName();
    			} else {
    				$this->fileName = realpath($path) . DIRECTORY_SEPARATOR . $name;
    			}
    		} else {
    			if (empty($name)) {
    				$this->fileName = $this->getUrlName();
    			} else {
    				$this->fileName = $name;
    			}
    		}
    	}
    	echo $this->fileName;exit;
    	try{
    		$fp = fopen($this->fileName, 'w');
    		fwrite($fp, $this->response);
    		$return = '写入文件成功';
    	} catch (Exception $e) {
    		throw new Exception('Failed to save the content', 500);
    		$return = '写入文件失败';
    	}
    	fclose($fp);
    
    	return $return;
    }

    /**
     * Task info
     * @return array
     */
    public function info()
    {
        return $this->info;
    }

    /**
     * curl_getinfo方法和参数获取相关信息
     *
     * @param curl_getinfo params $opt
     * @return mixed | string
     */
    public function getInfo($opt = NULL){
        $args = array();
        $args[] = $this->curl;

        if (func_num_args()) {
            $args[] = $opt;
        }

        return call_user_func_array('curl_getinfo', $args);
    }

    /**
     * Response Data
     * @return string | null
     */
    public function response()
    {
        return $this->response;
    }
    
    /**
     * 根据传入的key返回相应的header数据
     * 如果没有传入key则返回所有的header数据
     * 
     * @param string $key
     * @return string|array
     */
    public function responseHeader($key = null){
    	if (!empty($key) && isset($this->responseHeaders[$key])) {
    		return $this->responseHeaders[$key];
    	}
    	
    	return $this->responseHeaders;
    }

    /**
     * Get Http Status Code
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->http_status_code;
    }

    /**
     * Curl Error status
     * @return int
     */
    public function getCurlError()
    {
        return $this->curlError;
    }

    /**
     * Get Curl Error Message
     *
     * @return string
     */
    public function getCurlErrorMessage()
    {
        return $this->curlErrorMessage;
    }

    /**
     * Get Http Error status
     *
     * @return int
     */
    public function getHttpError()
    {
        return $this->httpError;
    }

    /**
     * Get Http Error Message
     *
     * @return string
     */
    public function getHttpMessage()
    {
        return $this->httpErrorMessage;
    }

    /**
     * Error message
     * @return string
     */
    public function getMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Error
     *
     * @return boolean
     */
    public function getError(){
    	return $this->error;
    }
    
    /**
     * 设置请求发送的数据 post|get|put|delete...
     * 
     * @access public
     * 
     * @param array $data
     * 
     * @return self
     */
    public function data(array $data) {
    	$this->data = $data;
    	return $this;
    }
    
    /**
     * 设置UserAgent
     * @param string $userAgent
     */
    public function setUserAgent($userAgent) {
        $this->userAgent = $userAgent;
    	$this->setOpt(CURLOPT_USERAGENT, $userAgent);
    }
    
    /**
     * 通过键值对[$key=>$value]的方式设置Header头
     *
     *@access public
     * 
     * @param string $key
     * @param string $value
     * 
     * @return self
     */
    public function setHeader($key, $value) 
    {
    	$this->headers[$key] = $value;
    	$headers = array();
    	foreach ($this->headers as $key => $value) {
    		$headers[] = $key . ': ' . $value;
    	}
    	$this->setOpt(CURLOPT_HTTPHEADER, $headers);
    	
    	return $this;
    }
    
    /**
     * 通过数组的方式批量设置Header头
     * 
     * @access public
     * 
     * @param array $headers
     * 
     * @return self
     */
    public function setHeaders($headers)
    {
    	foreach ($headers as $key => $value) {
    		$this->headers[$key] = $value;
    	}
    	$headers = array();
    	foreach ($this->headers as $key => $value) {
    		$headers[] = $key . ': ' . $value;
    	}
    	$this->setOpt(CURLOPT_HTTPHEADER, $headers);
    	
    	return $this;
    }
    
    /**
     * 伪造ip地址
     * 
     * @access public
     * 
     * @param string $ip
     * 
     * @return self
     */
    public function setClientIp($ip)
    {
    	$this->setHeader('CLIENT-IP', $ip);
    	$this->setHeader('X-FORWARDED-FOR', $ip);
    	return $this;
    }
    
    public function setUserPwd($user, $password) {
    	$this->setOpt(CURLOPT_USERPWD, $user . ':' . $password);
    }
    
    /**
     * 获取设置的Curl属性值
     * 
     * @access public
     * 
     * @param  $option
     * @param  $value
     * 
     * @return NULL|mixed
     */
    public function getOpt($option) {
    	return isset($this->options[$option]) ? $this->options[$option] : null;
    }
    
    /**
     * 通过键值对的方式设置Curl的属性值
     * 
     * @param Curl $option
     * @param string|boolean|number $value
     * 
     * @return self
     */
    public function setOpt($option, $value)
    {
    	if (in_array($option, array_keys($this->defaultOptions), true) && !($value === true)) {
    		unset($this->defaultOptions[$option]);/*删除默认的option设置 */
    	}
    	$success = curl_setopt($this->curl, $option, $value);
    	if ($success) {
    		$this->options[$option] = $value;
    	}
    	return $this;
    }
    
    /**
     * 通过数组方式批量设置Curl属性值
     * 
     * @access public
     * 
     * @param array $options
     * 
     * @return self
     */
    public function setOpts(array $options)
    {
    	foreach ($options as $option => $value){
    		$this->setOpt($option, $value);
    	}
	
    	return $this;
    }
    
    /**
     * 设置返回内容是否包含header内容
     * 默认不返回header内容
     * 
     * @access public
     * 
     * @param boolean $follow
     * 
     * @return self
     */
    public function setFollowHeader($follow = false) {
    	$this->follow_header = $follow;
    	return $this;
    }
    
    /**
     * 设置返回内容是否包含body
     * 默认包含
     * 
     * @access public
     * 
     * @param boolean $follow
     * 
     * @return self
     */
    public function setFollowNobody($follow = false) {
    	$this->follow_nobody = $follow;
    	return $this;
    }
    
	/**
	 * 设置是否重定向及重定向跳转的最大次数
	 * 
	 * @access public
	 * 
	 * @param boolean $follow 是否重定向
	 * @param number $redirs  最大跳转次数
	 * 
	 * @return self
	 */
    public function setFollowRedirects($follow = false, $redirs = 3) {
    	$this->follow_redirects = $follow;
    	$this->setMaxRedirs($redirs);
    	return $this;
    }
    
    /**
     * 设置重定向跳转的最大次数
     * 
     * @access public
     * 
     * @param number $redirs
     * 
     * @return self
     */
    public function setMaxRedirs($redirs) {
    	$this->max_redirs = $redirs;
    	return $this;
    }
    
    /**
     * 设置HTTP_REFERER
     * 
     * @access public
     * 
     * @param string $referer
     * 
     * @return self
     */
    public function setReferer($referer) {
    	if(is_string($referer)){
    		$this->referer = $referer;
    	}
    	return $this;
    }
    
    /**
     * 设置 json Content-type的正则
     * 
     * @access public
     * 
     * @param string $pattern
     * 
     * @return self
     */
    public function setJsonPattern($pattern = null) {
    	if (!empty($pattern) && is_string($pattern)) {
    		$this->jsonPattern = $pattern;
    	} else {
    		$this->jsonPattern = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';
    	}
    	
    	return $this;
    }
    
    /**
     * 设置json数据的默认解码方法
     * 
     * @access public
     * 
     * @return self
     */
    public function setDefaultJsonDecoder(){
    	$args = func_get_args();
    	$this->jsonDecoder = function ($response) use ($args) {
    		array_unshift($args, $response);
    		
    		if (version_compare(PHP_VERSION, '5.4.0', '<')){
    			$args = array_splice($args, 0, 3);
    		}
    		
    		$json_obj = call_user_func_array('json_decode', $args);
    		if (!($json_obj === null)){
    			$response = $json_obj;
    		}
    		
    		return $response;
    	};
    	
    	return $this;
    }
    
    /**
     * 设置 xml Content-type的正则
     * 
     * @access public
     *
     * @param string $pattern
     * @return self
     */
    public function setXmlPattern($pattern = null) {
    	if (!empty($pattern) && is_string($pattern)) {
    		$this->xmlPattern = $pattern;
    	} else {
    		$this->xmlPattern = '/^(?:text\/|application\/(?:atom\+|rss\+)?)xml/i';
    	}
    	
    	return $this;
    }
    
    /**
     * 设置curl连接超时时间
     * 默认设置为30秒
     * 
     * @access public
     * 
     * @param int $seconds
     * 
     * @return self
     */
    public function setConnectTimeOut($seconds = 30) {
    	$this->setOpt(CURLOPT_CONNECTTIMEOUT, $seconds);
    	return $this;
    }
    
    /**
     * 设置xml内容的解析方法
     * 
     * @access public
     * 
     * @return self
     */
    public function setDefaultXmlDecoder() {
    	$this->xmlDecoder = function ($response) {
    		$xml_obj = @simplexml_load_string($response);
    		if (!($xml_obj === false)){
    			$response = $xml_obj;
    		}
    		
    		return $response;
    	};
    	
    	return $this;
    }
    
    /**
     * 获取response内容中的cookie
     * 
     * @access public
     * 
     * @param string $key
     * 
     * @return string
     */
    public function getResCookie($key) {
    	return $this->getResponseCookie($key);
    }
    
    /**
     * 获取返回的cookie
     * 
     * @access public
     * 
     * @param string $key
     * 
     * @return string
     */
    public function getResponseCookie($key){
    	return isset($this->responseCookies[$key]) ? $this->responseCookies[$key] : null;
    }
    
    /**
     * 通过key获取设置的cookie变量
     * 
     * @access public
     * 
     * @param string $key
     * 
     * @return mixed
     */
    public function getCookie($key){
    	return $this->cookies[$key];
    }
    
   /**
    * 设置curl发送的cookie数据
    * 
    * @access public
    * 
    * @param string $key
    * @param $value
    * 
    * @return self
    */
    public function setCookie($key, $value){
    	$this->cookies[$key] = $value;
    	$this->setOpt(CURLOPT_COOKIE, http_build_query($this->cookies, '', '; '));
    	return $this;
    }
    
    /**
     * 返回所有的cookie信息
     * @access public
     * 
     * @return array
     */
    public function getCookies()
    {
    	return $this->cookies;
    }
    
    /**
     * 批量设置cookie数据
     * 
     * @access public
     * 
     * @param array $data
     * 
     * @return self
     */
    public function setCookies($data) {
    	if (is_array($data) && !empty($data)) {
    		foreach ($data as $key => $value) {
    			$this->setCookie($key, $value);
    		}
    	}
    	
    	return $this;
    }
    
    /**
     * 获取cookieString
     * 
     * @return sting
     */
    public function getCookieString() {
    	return $this->cookieString;
    }
    
    /**
     * 设置由cookie数据组成的字符串
     * 
     * @param sting $cookieString
     */
    public function setCookieString($cookieString) {
    	$this->cookieString = $cookieString;
    	$this->setOpt(CURLOPT_COOKIE, $this->cookieString);
    	return $this;
    }
    
    /**
     * 获取cookieFile
     * 
     * @return string;
     */
    public function getCookieFile() {
    	return $this->cookieFile;
    }
    
    /**
     * 设置cookie文件
     * 
     * @param string $cookieFile 文件路径
     * 
     * @return self
     */
    public function setCookieFile($cookieFile){
    	$this->cookieFile = $cookieFile;
    	$this->setOpt(CURLOPT_COOKIEFILE, $this->cookieFile);
    	return $this;
    }

    /**
     * 获取cookieJar
     * 
     * @return string
     */
    public function getCookieJar() {
    	return $this->cookieJar;
    }
    
    /**
     * 设置保存cookie文件
     * @param string $cookieJar
     * @return self
     */
    public function setCookieJar($cookieJar) {
    	$this->cookieJar = $cookieJar;
    	$this->setOpt(CURLOPT_COOKIEJAR, $this->cookieJar);
    	return $this;
    }

    /**
     * Request URL
     * @param string $url | http://www.xxx.com
     * @return self
     * @throws Exception
     */
    public function url($url)
    {
    	$url = $this->converUrl($url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $this->setUrl($url); 
            return $this;
        } else {
        	throw new Exception('Target URL is Required.', 500);
        }
    }
    
    /**
     * 设置curl请求端口
     * @param int $port
     */
    public function setPort($port = 80){
    	$this->setOpt(CURLOPT_PORT, $port);
    }
    
    /**
     * 设置URL的前缀   http | https | ftp ......
     * 
     * @access public
     * 
     * @param string $prefix
     * 
     * @return self
     */
    public function setUrlPrefix($prefix = 'http') {
    	$this->urlPrefix = $prefix;
    	return $this;
    }

    /**
     * get url
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
    
    public function getFileName()
    {
    	return $this->fileName;
    }

    /**
     * Set retry times
     * @param int $times
     * @return self
     */
    public function retry($times = 0)
    {
        $this->retry = $times;
        return $this;
    }
    
    public function exec($retry = 0)
    {
    	$this->process();
    	$this->rawResponse = (string)curl_exec($this->curl);
    	$this->info = $this->getInfo();
    	
    	//curl产生的错误
    	$this->curlErrorCode = curl_errno($this->curl);
    	$this->curlError = !($this->curlErrorCode === 0);
    	$this->curlErrorMessage = $this->setCurlErrorMessage();
    
    	//http错误
    	$this->http_status_code = $this->getInfo(CURLINFO_HTTP_CODE);
    	$this->httpError = in_array(floor($this->http_status_code / 100), array(4, 5));
    	
    	//全局的错误
    	$this->error = $this->curlError || $this->httpError;
    	$this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->http_status_code) : 0;
    
    	//解析请求时的header头
    	if ($this->getOpt(CURLINFO_HEADER_OUT)) {
    		$this->requestHeaders = $this->parseRequestHeaders($this->getInfo(CURLINFO_HEADER_OUT));
    	}
    	
    	$this->responseHeaders = $this->parseResponseHeaders($this->rawResponseHeaders);

    	$curlResponse = new CurlResponse($this->rawResponse);
    	$this->response = $this->parseResponse($this->responseHeaders, $curlResponse->__toString());
    	
    	$this->httpErrorMessage = $this->setHttpErrorMessage();//设置http错误信息
		//错误信息
    	$this->errorMessage = $this->curlError ? $this->curlErrorMessage : $this->httpErrorMessage;
    
    	if ($this->curlError && $retry < $this->retry) {
    		$this->exec($retry + 1);
    	}
    	
    	$this->close();
    
    	return $this;
    }

    private function process()
    {
        //设置返回内容
        $this->setOpt(CURLOPT_HEADER, $this->follow_header);
        $this->setOpt(CURLOPT_NOBODY, $this->follow_nobody);
        
        if ($this->referer) $this->setOpt(CURLOPT_REFERER, $this->referer);
        
        //设置重定向
        if ($this->follow_redirects) {
        	$this->setOpt(CURLOPT_MAXREDIRS, $this->max_redirs);
        	$this->setOpt(CURLOPT_FOLLOWLOCATION, $this->follow_redirects);
        }
        
        //如果允许返回header，则设置处理header的回调函数
        if($this->follow_header) {
        	$this->setOpt(CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));
        }
    }
    
    /**
     * 构建URL数据
     * @param string $url
     * @param array $data
     * @return string
     */
    private function bulidUrl($url, $data)
    {
    	return $url . ((empty($data)) ? '' : '?' . http_build_query($data, '', '&'));
    }
    
    /**
     * 如果传入的地址不存在http|https|ftp... 自动添加上
     * 默认添加http://
     *
     * @access private
     *
     * @param string $url
     * @param string $replace
     *
     * @return string $rplace . $url
     */
    private function converUrl($url) 
    {
    	if (!preg_match("/^(http|ftp|https):/", $url)) {
    		return $this->urlPrefix . '://' .$url;
    	}
    	 
    	return $url;
    }
    
    /**
     * 解析传入的URL地址
     * 
     * @access private
     * 
     * @return mixed|NULL
     */
    private function parseUrl() {
    	$path = parse_url($this->baseUrl, PHP_URL_PATH);
    	if (!empty($path)) {
    		$pathInfo = pathinfo($path);
    		return $pathInfo;
    	}
    	
    	return null;
    }
    
    /**
     * 通过对应的key来获取
     * @param unknown $key
     */
    private function getParseUrlInfo($key) 
    {
    	$pathinfo = $this->parseUrl();
    	if ($pathinfo !== null && isset($pathinfo[$key])) {
    		return $pathinfo[$key];
    	}
    	
    	return '';
    }
    
    private function getUrlName() 
    {
    	return $this->getParseUrlInfo('basename');
    }
    
    /**
     * 通过传入的url获取扩展名
     * 
     * @return NULL
     */
    private function getExtension() 
    {
    	return $this->getParseUrlInfo('extension');
    }
    /**
     * 处理header的回调方法
     * 
     * @param curl $ch
     * @param curl returned $header
     */
    private function headerCallback($ch, $header) {
    	//将返回的cookies获取到，并写入变量
    	if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $header, $cookie) === 1) {
    		$this->responseCookies[$cookie[1]] = trim($cookie[2], " \n\r\t\0\x0B");
    	}
    	
    	//把header添加到变量
    	$this->rawResponseHeaders .= $header;
    	return strlen($header); //如果return 0;则关闭curl
    }
    
    /**
     * 解析header字符串
     * 
     * @param string $rawHeaders
     * @return string[]|mixed[]|\Curl\CaseInsensitiveArray[]|unknown[]
     */
    private function parseHeaders($rawHeaders){
    	$rawHeaders = preg_split('/\r\n/', $rawHeaders, null, PREG_SPLIT_NO_EMPTY);
    	$httpHeaders = new CaseInsensitiveArray();
    	
    	$rawHeadersCount = count($rawHeaders);
    	for ($i = 1; $i < $rawHeadersCount; $i++) {
    		list($key, $value) = explode(':', $rawHeaders[$i], 2);
    		$key = trim($key);
    		$value = trim($value);
    		
    		if (isset($httpHeaders[$key])) {
    			$httpHeaders[$key] .= ',' . $value;
    		} else {
    			$httpHeaders[$key] = $value;
    		}
    	}
    	
    	return array(isset($rawHeaders[0]) ? $rawHeaders[0] : '', $httpHeaders);
    }
    
    /**
     * 解析发出请求的header
     * 
     * @param  $rawHeaders
     * @return array
     */
    private function parseRequestHeaders($rawHeaders){
    	$requestHeaders = new CaseInsensitiveArray();
    	list($firstLine, $headers) = $this->parseHeaders($rawHeaders);
    	$requestHeaders['Request-Line'] = $firstLine;
    	foreach ($headers as $key => $value) {
    		$requestHeaders[$key] = $value;
    	}
    	return $requestHeaders;	
    }
    
    /**
     * 解析返回的header
     * 
     * @param string $rawResponseHeaders
     * @return \Curl\CaseInsensitiveArray|\Curl\string[]|\Curl\mixed[]|\Curl\CaseInsensitiveArray[]|\Curl\unknown[]
     */
    private function parseResponseHeaders($rawResponseHeaders){
    	$response_header_array = explode('\r\n\r\n', $rawResponseHeaders);
    	$response_header = '';
    	for ($i = count($response_header_array) - 1; $i >= 0; $i--){
    		if (stripos($response_header_array[$i], 'HTTP/') === 0){
    			$response_header = $response_header_array[$i];
    			break;
    		}
    	}
    	
    	$response_headers = new CaseInsensitiveArray();
    	list($firstLine, $headers) = $this->parseHeaders($response_header);
    	$response_headers['Status-Line'] = $firstLine;
    	foreach ($headers as $key => $value) {
    		$response_headers[$key] = $value;
    	}
    	
    	return $response_headers;
    }
    
    private function parseResponse($response_headers, $raw_response){
    	$response = $raw_response;
    	if (isset($response_headers['Content-Type'])) {
    		if (preg_match($this->jsonPattern, $response_headers['Content-Type'])) {
    			$json_decoder = $this->jsonDecoder;
    			if (is_callable($json_decoder)) {
    				$response = $json_decoder($response);
    			}
    		} elseif (preg_match($this->xmlPattern, $response_headers['Content-Type'])) {
    			$xml_decoder = $this->xmlDecoder;
    			if (is_callable($xml_decoder)) {
    				$response = $xml_decoder($response);
    			}
    		} else {
    			$decoder = $this->defaultDecoder;
    			if (is_callable($decoder)) {
    				$response = $decoder($response);
    			}
    		}
    	}
    	
    	return $response;
    }
    
    /**
     * 生成带参数的url
     * 
     * @param string $url
     * @param array $data
     * @return Jp_Colopl_Libs_Curl_Curl
     */
    private function setUrl($url, $data = array())
    {
    	$this->baseUrl = $url;
    	$this->url = $this->bulidUrl($url, $data);
    	return $this->setOpt(CURLOPT_URL, $this->url);
    }

    /**
     * 设置Curl Error message
     * 
     * @return string
     */
    private function setCurlErrorMessage(){
    	$curlErrorMessage = '';
    	
        if ($this->curlError && function_exists('curl_strerror')) {
            $curlErrorMessage = curl_strerror($this->curlErrorCode).
                (
                    empty($this->curlErrorMessage) ? '' : ':' . $this->curlErrorMessage
                );
        }
        
        return $curlErrorMessage;
    }
    
    /**
     * 设置Http Error message
     */
    private function setHttpErrorMessage(){
    	$httpErrorMessage = '';
    	
    	if ($this->error) {
    		if (isset($this->responseHeaders['Status-Line'])) {
    			$httpErrorMessage = $this->responseHeaders['Status-Line'];
    		}
    	}
    	
    	return $httpErrorMessage;
    }

    /**
     * 设置默认的UserAgent
     */
    private function setDefaultUserAgent() {
    	$user_agent = 'PHP-Curl-Class/' . self::VERSION . ' (+https://github.com/jing/curl)';
    	$user_agent .= ' PHP/' . PHP_VERSION;
    	$curl_version = curl_version();
    	$user_agent .= ' curl/' . $curl_version['version'];
    	$this->setUserAgent($user_agent);
    }
    
    /**
     * Set Http Header
     * @param array $headers
     * @return self
     */
    private function setDefaultHeader()
    {
    	if (empty($this->headers)) {
    		$this->setHeaders($this->defaultHeaders);
    	}
    }
    
    /**
     * 检测文件夹是否存在
     * 如果$autoCreate === true 则自动创建文件夹
     *
     *@access private
     * 
     * @param string $path
     * @param string $autoCreate
     * 
     * @return boolean
     */
    private function checkPath($path, $autoCreate = true) {
    	if (!file_exists($path)){
    		if ($autoCreate) {
    		    return $this->createDir($path);
    		}
    		return false;
    	}
    	
    	return true;
    }
    
    /**
     * 递归创建文件夹
     * 
     * @access private
     * 
     * @param string $path
     * @param number $chmod
     * 
     * @return boolean
     */
    private function createDir($path, $chmod = 0777) 
    {
    	if (empty($path)) {
    		return false;
    	}
    	
    	if (!file_exists($path)){
    		$this->createDir(dirname($path), $chmod);
    		if (!mkdir($path, $chmod)) {
    			return false;
    		}
    	}
    	
    	return true;
    }
    
    /**
     * close Curl
     */
    private function close()
    {
    	if(is_resource($this->curl)){
        	curl_close($this->curl);
    	}
        $this->post  = array();
        $this->defaultOptions = array();
        $this->retry = 0;
    }

    /**
     * Convert array
     * @param array $input
     * @param null|string $pre
     * @return array
     */
    private function convert($input, $pre = null)
    {
        if (is_array($input)) {
            $output = array();
            foreach ($input as $key => $value) {
                $index = is_null($pre) ? $key : "{$pre}[{$key}]";
                if (is_array($value)) {
                    $output = array_merge($output, $this->convert($value, $index));
                } else {
                    $output[$index] = $value;
                }
            }
            return $output;
        }

        return $input;
    }
}