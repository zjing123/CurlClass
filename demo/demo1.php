<?php
require_once 'autoloader.php';

use Curl\Curl;

$url = 'http://www.baidu.com';

$path = 'a.txt';
$curl = Curl::init();
$curl->url($url)->get()->response();

echo $curl->response();
