Alexa Top 1M dataset tool v0.5
==============================

####Alexa Rank top 1 million sites crawling, data extraction and processing

 Copyright(c) 2014 Gael Abadin
 License: [MIT Expat](http://en.wikipedia.org/wiki/Expat_License)

##This script provides:
 
* Alexa Top 1M dataset retrieval and extractor
* Entry crawler 
* English content filter 
* Title, meta keywords and meta description extractor
* PageRank (google toolbar) request generator/sender and response parser
* Entry main page .png snapshot generator

##How to run (PHP CLI >= 5.3)

```bash
     php main.php
```

or

```bash
     php multipass.php maximum_number_of_extra_passes_for_timed_out_requests
```

(Explore the code for more command line parameters and tuning options, e.g. segment support for running a range of ranks instead of the full 1M or split the whole range on several runs across multiple hosts; proxie list support on `pagerank.php` queries; timeout values for curl requests; input/output filenames... etc.)

##Notes

* Set a high memory limit on `main.php` for proper execution (This script will keep 1 million URLS and ranks in memory, plus a window of up to $window_size (default 100) HTML documents pointed by those URLS. It is, therefore, quite memory hungry)
* If the script runs too slow or fails to retrieve some entries, try tuning `$window_size` on `main.php` and curl timeout parameters on `RollingCurl.php` to properly fit your network and system
* [Selenium server](http://selenium.googlecode.com/files/selenium-server-standalone-2.39.0.jar) must be properly deployed in order to retrieve .png snapshots
* Selenium server requires Mozilla Firefox binaries for rendering URLs provided by this script
* This script was quickly put togheter for a few bucks in a few hours. I am very aware it's not the supreme paradigm of proper software design and good programming practices and conventions, but it gets the work done :-) I will happily work on any cool improvement suggestions (Provided there's enough interest and if and when I can find the extra time)
* If you are interested you are very much welcomed to fork it, improve it, and send me a merge request ;-)
* Please contact me if you find a bug, so I can fix it as soon as possible.

##Changelog

* v0.1 First prototype
* v0.2 Added network/processing capacity tuning parameters and multiple simultaneous requests using curl_multi
* v0.3 Added snapshot support via selenium phpwebdriver and ASCII character pattern based English language filter
* v0.4 Added range support and multiple pass option for reprocessing failed requests
* v0.4.2 Patched minor bugs on parameter parsing and added support for seting `main.php` parameters when invoked from `multipass.php`
* v0.5 Added PageRank, fixed extra 'http://' error on multipass and snapshots and exec -> popen to display the output of invoked scripts and many bugs

##TODO

 * v0.5.2 Multiple simultaneous PageRank requests using curl_multi (can be tricky without sending each request through a different proxy, since toolbarqueries.google.com won't allow so many requests in such a short time)
 * v0.6 Add multiple DNS server query support
 * A lot of refactoring
 * Snapshot stall detection
 * Snapshot performance improvement (multiple selenium servers support, perhaps?)
 
##Donations

I normally opensource my personal projects for the sake of it, but this is a special case: I was scammed into building this project by a contractor who refused to pay when the work was done.

If you find this project useful, you can counter effect the jerkiness of this action by donating the equivalent to 1 or 2 USD to buy me a beer:

bitcoin: 1BLfngCd3ZudERXqRBXTm7FNGqhDRMcesF  
dogecoin: DMACbeZ7oFjzLSHVCNmNakR6BsLg9JwTPc  
paypal: http://goo.gl/7Tv6eQ  

Also, any donators (regardless of the amount) will receive for free a link to download a (roughly) 400000 English entries (almost exclusively: the language filter has an approximate failure rate of 1-10 out of 1000 - mainly entries without any words but the domain name on the title and no keywords or description meta tags) dataset CSV file I compiled using this software, to use and share without any limitations.

Donors so far:

* Anonymous Good Samaritan 1, 1,495.85152445 doge to DG9UD9cfe87A3zZoRL3AaJPTcRSKRYyA1C (old personal address)  
* Anonymous Good Samaritan 2, 448.033564 doge to DG9UD9cfe87A3zZoRL3AaJPTcRSKRYyA1C (old personal address)  
* Decicus (http://muchfact.info, great dogecoin resource directory!), 546 doge to DMACbeZ7oFjzLSHVCNmNakR6BsLg9JwTPc (new crawlexa official address)  

Thanks to all of you guys for your support and appreciation, and to everybody using this code for making it worth my time and effort.

Enjoy.