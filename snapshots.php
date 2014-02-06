<?php
if (!isset($file)){ $file = 'results/rolling_output.csv'; }
echo ("Generating snapshots...\n (This process will take a long time. In case of failure make sure you have Mozilla Firefox browser installed on your system and Selenium server installed, properly set up and running on localhost:4444, and check Selenium log file for more info.)\n");

/**
 * Include selenium php webdriver bindings for website snapshot
 */
require_once "phpwebdriver/WebDriver.php";

$webdriver = new WebDriver("localhost", "4444");
$webdriver->connect("firefox");

$fh = fopen($file,'r');
while (!feof($fh)){
    $line = fgetcsv($fh,0,',','"');
    if (isset($line[1])&&$line[1]!==''){
        $webdriver->get("http://".preg_replace('@http[s]?://@i','',$line[1]));
        $webdriver->getScreenshotAndSaveToFile("results/snapshots/{$line[0]}.png");
    }
}

$webdriver->close();