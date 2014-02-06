<?php

/**
 *
 * Reprocess Failed Requests and filter all non English results.
 *
 */

ini_set('memory_limit','1024M');
require_once 'pagerank.php';

$s =','; // csv separator
if ($argc>1) {$extra_iterations = $argv[1];} else {$extra_iterations = 1;}
if ($argc>2) {$file = $argv[2];} else {$file = 'results/rolling_output.csv';}
$outputFile = $file.'.filteredOutput';
$overwrite = false;
if ($argc>3) {
    if ($argv[3]==='--overwrite'||$argv[3]==='-o') {
        // overwrite first pass results
        $overwrite = true;
    } else {
        // set output file name
        $outputFile = $argv[3];
    }
}
if ($argc>4) {$datasetfilename = $argv[4];} else {$datasetfilename = 'datasets/top-1m.csv';}
if ($argc>5) {$segment_start = $argv[5];}
if ($argc>6) {$segment_end = $argv[6];}
if ($argc>7) {
    if ($argv[7]==='--no-snapshots'){
        $nosnapshots = '--no-snapshots';
    }
}

if(!file_exists($file)){
    // set a first pass if no first pass results found
    echo "{$file} not found. Executing first pass of main.php to obtain it (it may take a long time!)...\n";
    $ph = popen("php main.php {$datasetfilename} {$file}".(isset($segment_start)?' '.$segment_start:'').(isset($segment_end)?' '.$segment_end:'').(isset($nosnapshots)?' '.$nosnapshots:'').' 2>&1','r');
    while (!feof($ph)){ echo fgets($ph); }
    pclose($ph);
}
$swapInputFile = $file.'.swpi';
$swapOutputFile = $file.'.swpo';

$i=0;
$pattern = '@^[\x{000a}\x{000d}\x{0020}-\x{007e}\x{2000}-\x{27ff}]*$@u'; //only English (ASCII printable and unicode general extensions) characters
$fw = fopen($outputFile,'w');

for ($i=0;$i<$extra_iterations;$i++){
    $timedoutresponsescount = 0;
    if ($i>0) {
        unlink ($swapInputFile);
        rename ($swapOutputFile,$swapInputFile);
        $fh = fopen($swapInputFile,'r');
        $inputfilename = $swapInputFile;
    } else {
        $fh = fopen($file,'r');
        $inputfilename = $file;
    }
    echo "Parsing timed-out entries and adding missing PageRanks on {$inputfilename} for iteration ".($i+1)." of ".$extra_iterations."\n";
    $fsw = fopen($swapOutputFile,'w');
    $prevLine = "start of {$inputfilename}";
    while (!feof($fh)){
        $line = fgetcsv($fh,0,',','"');
        if (isset($line[1])&&$line[1]!==''){
            if (isset($line[2])&&$line[2]===''){
                $timedoutresponsescount++;
                $sLine = $line[0].$s.$line[1];
                fwrite($fsw, $sLine."\n");
            } else {
                if (!isset($line[4])) {
                    if ($prevLine!==''){
                        echo "[ERROR] Error parsing {$inputfilename} right after ".$prevLine."\n";
                    }
                    $sLine = '';
                } else {
                    if (!isset($line[5])||$line[5]===-1){
                        $line[5] = pagerank('http://'.preg_replace('@http[s]?://@i','',$line[1]));
                    }
                    $sLine = preg_replace('@\t\n@',"\n","{$line[0]}{$s}{$line[1]}{$s}\"{$line[2]}\"{$s}\"{$line[3]}\"{$s}\"{$line[4]}\"{$s}{$line[5]}");
                    if (preg_match($pattern,$sLine)){
                        fwrite($fw, $sLine."\n");
                    }
                }
            }
            $prevLine=$sLine;
        }
    }
    fclose($fsw);
    fclose($fh);
    if (filesize($swapOutputFile)===0){
        echo "No more timed out requests found after repass ".($i+1).".\n Exiting...\n";
        break;
    } else {
        echo "Executing repass ".($i+1)." of ".$extra_iterations." on ".$timedoutresponsescount." timed-out responses from previous pass.\n";
        $ph = popen("php main.php {$swapOutputFile} {$swapOutputFile} -1 -1 ".(isset($nosnapshots)?$nosnapshots:'')." 2>&1",'r'); //reprocess last pass timed-out (empty) responses
        while (!feof($ph)){ echo fgets($ph); }
        pclose($ph);
    }
}
fclose($fw);
if ($overwrite){
    unlink($file);
    rename ($outputFile, $file);
}
