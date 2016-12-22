<?php
namespace Curl;
require_once 'curl.php';
$url = 'http://www.baidu.com';
//$url = 'https://codeload.github.com/php-mod/curl/zip/master';
$path = 'a.txt';
$curl = Jp_Colopl_Libs_Curl_Curl::init();
$curl->url($url)->setFollowHeader(true)->get()->save('a.html');
print_r($curl->response());


// $curl = new Jp_Colopl_Libs_Curl_Curl();
// $curl->url($url)->get()->save($path);