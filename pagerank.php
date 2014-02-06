<?php

define ('PAGERANK_SERVER_URI', 'toolbarqueries.google.com');
define ('PAGERANK_SERVER_TIMEOUT', 10);
define ('PAGERANK_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36');

function pagerank($url, $proxies = false, $ind = 0){
    if ($proxies===null){
        $proxies = array(
            '163.125.97.101:8888',
            '110.164.65.19:8080',
            '200.75.51.151:8080',
            '163.125.99.177:9999',
            '103.28.115.41:8080',
            '201.75.2.74:8080',
            '110.170.137.26:80',
            '190.232.45.32:8080',
        );
    }
    //for ($i=0;$i<count($proxies);$i++){
    if (!isset($url)||$url===''){ return ''; }
    $request = 'http://'.PAGERANK_SERVER_URI.'/tbr?client=navclient-auto&ch='.checkSum($url).'&features=Rank&q=info:'.$url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request);
    curl_setopt($ch, CURLOPT_USERAGENT, PAGERANK_USER_AGENT);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($proxies!==false&&($i=mt_rand($ind,count($proxies)-1))>-1){
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $proxies[$i]);
    }
    //curl_setopt($ch, CURLOPT_PROXY, $proxies[$i]);
    curl_setopt($ch, CURLOPT_TIMEOUT, PAGERANK_SERVER_TIMEOUT);
    $result = curl_exec($ch);
    if ($error=curl_error($ch)) {
        echo "Received error when querying pagerank server".((isset($i)&&$i>-1)?" with proxy {$proxies[$i]}":'').":\n {$error}\n";
        if (!isset($i)||$i<0){
            echo "Maybe too many requests? Waiting 3610 seconds before continuing...\n";
            sleep(3610);
            echo "resuming process...\n";
        }
    }
    //}
    if (empty($result)) {
        return -1;
    }
    return intval(substr($result, strrpos($result, ':')+1));
}

function checkSum($url){
    $hash = jenkins_hash($url);
    $ch = jenkins_hash_check($hash);
    return $ch;
}

/**
 * PHP implementation of Bob Jenkins hash function taken from some answer I can't find anymore on stackoverflow.com
 */
function jenkins_hash($inputString){
    $firstCheck = str2num($inputString, 0x1505, 0x21);
    $secondCheck = str2num($inputString, 0, 0x1003F);

    $firstCheck >>= 2;
    $firstCheck = (($firstCheck >> 4) & 0x3FFFFC0 ) | ($firstCheck & 0x3F);
    $firstCheck = (($firstCheck >> 4) & 0x3FFC00 ) | ($firstCheck & 0x3FF);
    $firstCheck = (($firstCheck >> 4) & 0x3C000 ) | ($firstCheck & 0x3FFF);

    $t1 = (((($firstCheck & 0x3C0) << 4) | ($firstCheck & 0x3C)) <<2 ) | ($secondCheck & 0xF0F );
    $t2 = (((($firstCheck & 0xFFFFC000) << 4) | ($firstCheck & 0x3C00)) << 0xA) | ($secondCheck & 0xF0F0000 );

    return ($t1 | $t2);
}

function jenkins_hash_check($hash32uint){
    $checkByte = 0;
    $flag = 0;

    $hashStr = sprintf('%u', $hash32uint) ;
    $length = strlen($hashStr);

    for ($i = $length - 1;  $i >= 0;  $i --) {
        $re = $hashStr{$i};
        if (1 === ($flag % 2)) {
            $re += $re;
            $re = (int)($re / 10) + ($re % 10);
        }
        $checkByte += $re;
        $flag ++;
    }

    $checkByte %= 10;
    if (0 !== $checkByte) {
        $checkByte = 10 - $checkByte;
        if (1 === ($flag % 2) ) {
            if (1 === ($checkByte % 2)) {
                $checkByte += 9;
            }
            $checkByte >>= 1;
        }
    }
    return '7'.$checkByte.$hashStr;
}

function str2num($str,$check,$magic){
    $Int32Unit = 4294967296;  // 2^32

    $length = strlen($str);
    for ($i = 0; $i < $length; $i++) {
        $check *= $magic;
        if ($check >= $Int32Unit) {
            $check = ($check - $Int32Unit * (int) ($check / $Int32Unit));
            //if the check less than -2^31
            $check = ($check < -2147483648) ? ($check + $Int32Unit) : $check;
        }
        $check += ord($str{$i});
    }
    return $check;
}
