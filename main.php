<?php
/**
 * Alexa top 1M dataset tool v0.3
 * 
 * Copyright(c) 2014 Gael Abadin
 * License: MIT Expat (http://en.wikipedia.org/wiki/Expat_License)
 *
 * This script provides:
 * 
 * Alexa Top 1M retrieval and extractor,
 * Entry crawler, 
 * English content filter, 
 * Title, meta keywords and meta description extractor, 
 * Snapshot generator
 *
 * How to run (PHP CLI >= 5.3):
 *
 *     php main.php
 *
 * Set a high memory limit for proper execution.
 *
 * This script will keep 1 million URLS and ranks in memory, plus a window of up to $window_size (default 100) HTML documents pointed by those URLS. It is, therefore, quite memory hungry.
 *
 * If the script runs too slow or fails to retrieve some entries, try tuning `$window_size` on this file and curl timeout parameters on `RollingCurl.php` to properly fit your network and system.
 *
 * Selenium server (http://selenium.googlecode.com/files/selenium-server-standalone-2.39.0.jar) must be properly deployed in order to retrieve .png snapshots. 
 * 
 * Selenium server requires Mozilla Firefox binaries for rendering URLs provided by this script.
 *
 */
ini_set('memory_limit', '1024M');
$window_size = 5; //reduce to cope with low memory_limit
//error_reporting(E_ALL & ~(E_WARNING|E_NOTICE)); // Because we don't care about DOMDocument parser constantly nagging about non-conformant HTML.

/**
 * Include rolling curl for proper parallel curl request processing using curl_multi
 */
require_once 'RollingCurl.php';

/**
 * Include pagerank processing function
 */
require_once 'pagerank.php';

/**
 * Process input parameters
 */
$inputfilename = 'datasets/top-1m.csv'; // unless otherwise specified on command line parameters
$file = "results/rolling_output.csv"; // unless otherwise specified on command line parameters
$snapshots = true; // unless otherwise specified on command line parameters
$segment_start = 0;
if ($argc >= 2) {
    $inputfilename = $argv[1];
    if ($argc >= 3){
        $file = $argv[2];
    }
    if ($argc >= 4){
        if ($argv[3]!=='-1'){
            $segment_start = $argv[3];
        }
    }
    if ($argc >= 5){
        if ($argv[4]!=='-1'){
            $segment_end = $argv[4];
        }
    }
    if ($argc >= 6){
        if ($argv[5]==='--no-snapshots'){
            $snapshots = false;
        }
    }
}
if ($inputfilename==='datasets/top-1m.csv' && !file_exists($inputfilename)) {
    /**
     * Initialize and retrieve alexa 1M dataset from Amazon S3 alexa-static bucket
     */
    $url = 'http://s3.amazonaws.com/alexa-static/top-1m.csv.zip';
    echo "Retrieving compressed alexa 1M dataset input file from {$url}...\n";
    if (!$curl = curl_init()) { // initialize curl
        echo"[!] Could not initialize curl!\n";
        exit;
    }
    curl_setopt ($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.41 Safari/537.36");
    curl_setopt ($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt ($curl, CURLOPT_REFERER, "http://www.google.com/");
    //curl_setopt ($curl, CURLOPT_PROXY, "http://127.0.0.1:8118/");
    //curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($curl, CURLOPT_URL, $url);
    curl_setopt ($curl, CURLOPT_FILE, fopen('datasets/top-1m.csv.zip','w'));
    curl_exec($curl);
    curl_close ($curl);
    $zip = new ZipArchive;
    $res = $zip->open('datasets/top-1m.csv.zip');
    echo "Decompressing datasets/top-1m.csv.zip on datasets/top-1m.csv...\n";
    if ($res === TRUE) {
        $zip->extractTo('datasets/');
        $zip->close();
        echo "alexa datasets/top-1m.csv file extracted successully from datasets/top-1m.csv.zip\n";
    } else {
        echo "cannot open file to extract\n";
    }
}

/**
 * Process input csv file (rank, url)
 */
echo "Loading input file {$inputfilename}...\n";
$massiveString = file_get_contents($inputfilename); // ~22.5 MB (1 million entries)
//var_dump ( $massiveString );
$massiveArray = explode("\n",$massiveString); // Another ~22.5 MB
//echo "count: ".count($massiveArray);
if (!isset($segment_end)) {
    $segment_end = count($massiveArray)-1;
}
echo ("Generating rank, url, \"title\", \"keywords\", \"description\", PageRank CSV file of English websites listed on {$inputfilename}...\n");

$massiveString = null;
$ips = array();

/**
 * Get output file handler
 */
if (!$fh = fopen($file, 'w')) {
    echo "[!] Could not open file for csv dumping.\n";
    exit;
}
stream_set_write_buffer ($fh, 65536);

function request_callback(
        $response,
        /** @noinspection PhpUnusedParameterInspection */
        $info,
        $request
    ) {
    $s = ','; // CSV output file separator
    $doc = new DOMDocument();
    @$doc->loadHTML($response);
    $titletags = $doc->getElementsByTagName('title');
    if ($titletags->length > 0){
        $titletag = $titletags->item(0);
        $title = preg_replace('@(?<!\\\)"@','\"',$titletag->textContent);
    } else {
        $title = '';
    }
    $metatags = $doc->getElementsByTagName('meta');
    $description = '';
    $keywords = '';
    for ($i=0;$i<$metatags->length;$i++){
        $metatag = $metatags->item($i);
        /** @noinspection PhpUndefinedMethodInspection */
        if (strtolower($metatag->getAttribute('name'))==='description'){
            /** @noinspection PhpUndefinedMethodInspection */
            $description = preg_replace('@(?<!\\\)"@','\"',$metatag->getAttribute('content'));
        } else
            /** @noinspection PhpUndefinedMethodInspection */
            if (strtolower($metatag->getAttribute('name'))==='keywords'){
            /** @noinspection PhpUndefinedMethodInspection */
            $keywords = preg_replace('@(?<!\\\)"@','\"',$metatag->getAttribute('content'));
        }
    }
    // This will work fine on concurrency as long as we're not trying to write more than 64KB (see http://www.php.net/manual/en/function.stream-set-write-buffer.php)
    $line = preg_replace('@\t\n@',"\n","{$request->rank}{$s}{$request->url}{$s}\"{$title}\"{$s}\"{$keywords}\"{$s}\"{$description}\"{$s}".(isset($request->pagerank)?$request->pagerank:pagerank($request->url)));
    $pattern = '@^[\x{000a}\x{000d}\x{0020}-\x{007e}\x{2000}-\x{27ff}]*$@u'; //only English (ASCII printable and unicode general extensions) characters
    if (preg_match($pattern,$line)){
        fwrite($request->outputfilehandler, $line."\n");
    }
    //echo $line."\n";
}
$rc = new RollingCurl("request_callback");
$rc->window_size = $window_size;
$mini = $segment_start;
$maxi = $segment_end;
$time = microtime(true);
for ($i=$mini;$i<=$maxi;$i++){
    $fision = explode(',',$massiveArray[$i]);
    //account for empty lines
    if (!isset($fision[1])){ continue; }
    //$request = new RollingCurlRequest('http://'.preg_replace('@http[s]?://@i','',$ips[$i]));
    $request = new RollingCurlRequest('http://'.preg_replace('@http[s]?://@i','',$fision[1]));
    $request->rank = $fision[0];
    $request->outputfilehandler = $fh;
    if (isset($fision[5])&&$fision[5]!==''&&$fision[5]!=='-1'){
        $request->pagerank = $fision[5];
    }
    $headers = array("Host: ".$fision[1]);
    $request->options = array (CURLOPT_HTTPHEADER=>$headers);
    $rc->add($request);
    if (($i+1)%$window_size===0){
        $rc->execute();
        $rc = new RollingCurl("request_callback");
        $rc->window_size = $window_size;
        if (($sleeptime=($window_size*1100-(($curtime=microtime(true))-$time)))>10000){
            usleep($sleeptime);
        }
        $time = $curtime;
    }
}
if (($maxi+1)%$window_size!==0){
    $rc->execute();
}
fclose($fh);
echo ("rank, url, \"title\", \"keywords\", \"description\", PageRank CSV file of English websites in top Alexa 1M generated in {$file}.\n");

if ($snapshots){
    require_once 'snapshots.php';
}

exit;
