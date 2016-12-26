<?php

$mapping = array(
    'Curl\CaseInsensitiveArray' => __DIR__ . '/CaseInsensitiveArray.php',
    'Curl\CurlResponse'         => __DIR__ . '/CurlResponse.php',
	'Curl\Curl'					=> __DIR__ . '/Curl.php'
);


spl_autoload_register(function ($class) use ($mapping){
    if (isset($mapping[$class])) {
        require $mapping[$class];
    }
}, true);