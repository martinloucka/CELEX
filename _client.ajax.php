<?php 

	/*
	 * File _client.ajax.php
	 * NOT PART OF NETTE FRAMEWORK
	 * Copyright (c) 2016, Martin Loučka
	 * Licensed under BSD 3, see /bsd.txt for details
	 * ==============================================
	 * 
	 * If included, provides handy function for obtaining data from third party server 
	 * that shall success in situations when file_get_contents does not. Supports caching.
	 * 
	 * If run with address parameters, will execute function directly and print result.
	 */

	function get_data($url, $cacheFile = null, $expirationHours = null, $returnHeaderCode = false)
	{
		// check cache
        if ( $cacheFile ) {
			// if cached, return data
            if ( file_exists( 'curl_cache/' . $cacheFile . '.html' ) ) {
				if ( $expirationHours === null || !is_int($expirationHours) ) {
					// no expiration period
					return file_get_contents( 'curl_cache/' . $cacheFile . '.html' );
				} else {
					// may be expired..
					// get period that passed after last modification (not file creation)
					$diff = date_diff(date_create(date('Y-m-d H:i:s',filemtime('curl_cache/' . $cacheFile . '.html'))),date_create('now'));
					if ( $diff->h + $diff->d*24 > $expirationHours ) {
						// expired, no need to unlink, will be rewritten below
					} else {
						// cache still valid, return either "header code" or body
						return $returnHeaderCode === true ? 200 : file_get_contents( 'curl_cache/' . $cacheFile . '.html' );
					}
				}
            }
        }
        
		// set curl agent to something believable
        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        
		// gather data for curl request and get remote server's response
		// reponse stored in $result
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL,$url);
        $result=curl_exec($ch);
		
		// get information from response
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($result, 0, $header_size);
		$body = substr($result, $header_size);

		curl_close($ch);
        
		// shall we cache?
        if ( $cacheFile ) {
			// is the response OK?
            if ( $httpcode == 200 ) {
                file_put_contents('curl_cache/' . $cacheFile . '.html', $result);
            }
        }
		
		// return either header code or body
        return $returnHeaderCode === true ? $httpcode : $body; 
	}
	
	// if run directly with address parameters, gather relevant parameters, call the function and print the result
	if ( isset( $_GET['url']) ) {
		echo get_data(
			str_replace(" ","+",$_GET['url']), // call URL
			isset($_GET['cache'])?$_GET['cache']:null, // cache file (will enable caching)
			null, // no expiration 
			isset($_GET['process'])?true:false // return body (standard, false), or header code (true)?
		);
	}
