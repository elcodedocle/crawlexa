<?php
$fh = fopen('results/rolling_output_english_with_some_pageranks.csv','r');
$fw = fopen('results/rolling_output_english_with_some_pageranks.csv2','w');
$s=',';
while (!feof($fh)){
    $line = fgetcsv($fh,0,',','"');
    if (count($line)>=5){
        if (!isset($line[5])){
            $line[5]='';
        }
        $sLine = "{$line[0]}{$s}{$line[1]}{$s}\"{$line[2]}\"{$s}\"{$line[3]}\"{$s}\"{$line[4]}\"{$s}{$line[5]}";
        fwrite($fw, $sLine."\n");
    }
}
fclose($fh);
fclose($fw);
unlink('results/rolling_output_english_with_some_pageranks.csv');
rename('results/rolling_output_english_with_some_pageranks.csv2','results/rolling_output_english_with_some_pageranks.csv');